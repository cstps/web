#!/usr/bin/env python3
"""
정답 코드를 기존 채점기와 같은 조건으로 렌더링하여 목표 PNG를 생성한다.
격리된 Docker 컨테이너 안에서 실행하는 용도다.
DB를 조회하거나 수정하지 않는다.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import time
from io import BytesIO
from pathlib import Path

sys.dont_write_bytecode = True

from PIL import Image
from playwright.sync_api import sync_playwright
from course_simulation_render_grade import render_code


def positive_integer(payload: dict, key: str) -> int:
    value = payload.get(key)

    if type(value) is not int or value <= 0:
        raise RuntimeError("양의 정수 입력이 필요합니다: " + key)

    return value


def validate_reference_regions(pixels, payload: dict):
    config = payload.get("grading_config")

    # 기존 목표 이미지 생성 요청도 계속 지원한다.
    if config is None:
        return None

    if not isinstance(config, dict):
        raise RuntimeError("채점 설정은 JSON 객체여야 합니다.")

    mode = config.get("scoring_mode", "metrics_only")
    if mode not in ("metrics_only", "whole_image", "regions"):
        raise RuntimeError("지원하지 않는 점수 산정 방식입니다.")

    if mode != "regions":
        return None

    if (
        config.get("metric") != "symmetric_foreground_color_coverage"
        or config.get("neighborhood") != "euclidean_disk"
        or config.get("scoring_method") != "region_coverage_linear_v1"
    ):
        raise RuntimeError("지원하지 않는 영역별 채점 설정입니다.")

    max_score = payload.get("reference_max_score")
    if not isinstance(max_score, str):
        raise RuntimeError("영역 검사에 필요한 전체 배점이 없습니다.")

    from course_simulation_image_metrics import (
        symmetric_foreground_color_coverage,
    )
    from course_simulation_region_metrics import (
        weighted_region_color_coverage,
    )

    # 정답 그림 자체를 비교하여 영역 구성의 유효성을 검사한다.
    comparison = weighted_region_color_coverage(
        pixels,
        pixels,
        regions=config.get("regions"),
        max_score=max_score,
        position_tolerance_px=config.get("position_tolerance_px"),
        color_tolerance=config.get("color_tolerance"),
    )

    tolerance = symmetric_foreground_color_coverage(
        pixels,
        pixels,
        position_tolerance_px=config.get("position_tolerance_px"),
        color_tolerance=config.get("color_tolerance"),
    )

    if comparison["final_coverage"] != 100.0:
        raise RuntimeError("정답 그림의 영역별 충족률이 100%가 아닙니다.")

    return {
        "comparison": comparison,
        "tolerance": tolerance,
    }



def positive_id_string(payload: dict, key: str) -> str:
    value = payload.get(key)
    if (
        not isinstance(value, str)
        or re.fullmatch(r"[1-9][0-9]{0,18}", value) is None
        or int(value) > 9223372036854775807
    ):
        raise RuntimeError("문제은행 식별자가 올바르지 않습니다: " + key)
    return value


def main() -> None:
    parser = argparse.ArgumentParser(
        description="정답 코드에서 학생용 목표 PNG 생성"
    )
    parser.add_argument("--input-json", required=True, type=Path)
    parser.add_argument("--p5", required=True, type=Path)
    parser.add_argument("--output-png", required=True, type=Path)
    args = parser.parse_args()

    payload = json.loads(
        args.input_json.read_text(encoding="utf-8")
    )

    if not isinstance(payload, dict):
        raise RuntimeError("입력 JSON은 객체여야 합니다.")

    problem_id = positive_id_string(payload, "problem_id")
    version_id = positive_id_string(payload, "version_id")
    version_no = positive_integer(payload, "version_no")
    width = positive_integer(payload, "canvas_width")
    height = positive_integer(payload, "canvas_height")

    if width != 400 or height != 400:
        raise RuntimeError("현재 목표 그림 생성은 400×400만 지원합니다.")

    if payload.get("p5_version") != "2.3.4":
        raise RuntimeError("현재 p5.js 2.3.4만 지원합니다.")

    code = payload.get("reference_code")
    if not isinstance(code, str) or not code.strip():
        raise RuntimeError("정답 코드가 비어 있거나 올바르지 않습니다.")

    if args.output_png.exists() or args.output_png.is_symlink():
        raise RuntimeError("출력 파일이 이미 있습니다. 덮어쓰지 않습니다.")

    if not args.output_png.parent.is_dir():
        raise RuntimeError("출력 디렉터리가 없습니다.")

    p5_bytes = args.p5.read_bytes()
    p5_source = p5_bytes.decode("utf-8", errors="replace")

    started = time.perf_counter()

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-dev-shm-usage",
            ],
        )

        try:
            pixels = render_code(
                browser,
                p5_source,
                code,
                "정답",
                width,
                height,
            )
        finally:
            browser.close()

    if not (pixels != 255).any():
        raise RuntimeError("정답 그림에 전경 도형이 없습니다.")

    # 영역 검사가 실패하면 PNG 파일을 생성하지 않는다.
    region_validation = validate_reference_regions(pixels, payload)

    buffer = BytesIO()
    Image.fromarray(pixels).save(buffer, format="PNG")
    png = buffer.getvalue()

    # 기존 파일을 덮어쓰지 않고 새 파일만 생성한다.
    output_created = False
    try:
        with args.output_png.open("xb") as output:
            output_created = True
            output.write(png)
    except BaseException:
        if output_created:
            args.output_png.unlink(missing_ok=True)
        raise

    result = {
        "status": "ok",
        "comparison_mode": "problem-bank-static-image-v1",
        "problem_id": problem_id,
        "version_id": version_id,
        "version_no": version_no,
        "canvas_width": width,
        "canvas_height": height,
        "p5_version": "2.3.4",
        "reference_source_sha256": hashlib.sha256(
            code.encode("utf-8")
        ).hexdigest(),
        "p5_file_sha256": hashlib.sha256(p5_bytes).hexdigest(),
        "png_sha256": hashlib.sha256(png).hexdigest(),
        "png_bytes": len(png),
        "elapsed_seconds": time.perf_counter() - started,
    }

    if region_validation is not None:
        result["region_validation"] = region_validation

    print(json.dumps(result, ensure_ascii=False, allow_nan=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print("목표 그림 생성 실패: " + str(error), file=sys.stderr)
        raise SystemExit(1)
