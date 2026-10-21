#!/usr/bin/env python3
"""
Course p5.js 시뮬레이션 정적 그림 렌더링 비교 dry-run 스크립트.

이 스크립트는 운영 DB를 직접 수정하지 않는다.
입력 JSON에 담긴 정답 코드와 제출 코드를 렌더링하여 비교 결과를 출력한다.

입력 JSON 예:
{
  "activity_id": 67,
  "reference_no": 2,
  "reference_code": "...",
  "submission_id": 6,
  "submission_no": 6,
  "student_code": "...",
  "canvas_width": 400,
  "canvas_height": 400
}
"""

from __future__ import annotations

import argparse
import base64
import json
import time
from io import BytesIO
from pathlib import Path
from typing import Any, Dict, Tuple

import numpy as np
from PIL import Image
from playwright.sync_api import sync_playwright


def load_payload(path: Path) -> Dict[str, Any]:
    text = path.read_text(encoding="utf-8").strip()

    if not text:
        raise RuntimeError("입력 JSON이 비어 있습니다.")

    payload = json.loads(text)

    required = (
        "activity_id",
        "reference_no",
        "reference_code",
        "submission_id",
        "submission_no",
        "student_code",
        "canvas_width",
        "canvas_height",
    )

    missing = [key for key in required if key not in payload]
    if missing:
        raise RuntimeError("입력 JSON 필드 누락: " + ", ".join(missing))

    return payload


def build_html(p5_source: str, code: str) -> str:
    safe_code = str(code).replace("</script", "<\\/script")

    return """<!doctype html>
<meta charset="utf-8">
<script>
window.addEventListener("error", function (event) {
    window.__render_error = String(event.message || "실행 오류");
});
window.addEventListener("unhandledrejection", function (event) {
    window.__render_error = String(event.reason || "비동기 실행 오류");
});
</script>
<script>
P5_SOURCE
</script>
<script>
CODE
</script>
<script>
setTimeout(function () {
    try {
        const canvas = document.querySelector("canvas");
        if (!canvas) {
            window.__render_error = "캔버스가 생성되지 않았습니다.";
            return;
        }
        window.__canvas_png = canvas.toDataURL("image/png");
    } catch (error) {
        window.__render_error = String(error && error.message || error);
    }
}, 300);
</script>
""".replace("P5_SOURCE", p5_source).replace("CODE", safe_code)


def render_code(
    browser: Any,
    p5_source: str,
    code: str,
    label: str,
    width: int,
    height: int,
) -> np.ndarray:
    page = browser.new_page(
        viewport={"width": width, "height": height},
        device_scale_factor=1,
    )

    try:
        page.set_content(build_html(p5_source, code), wait_until="load")
        page.wait_for_function(
            "window.__canvas_png || window.__render_error",
            timeout=10000,
        )

        error = page.evaluate("window.__render_error || null")
        if error:
            raise RuntimeError(label + " 렌더링 오류: " + str(error)[:300])

        data_url = page.evaluate("window.__canvas_png")
        prefix = "data:image/png;base64,"
        if not isinstance(data_url, str) or not data_url.startswith(prefix):
            raise RuntimeError(label + " PNG 데이터 URL을 받지 못했습니다.")

        png = base64.b64decode(data_url[len(prefix):])
        with Image.open(BytesIO(png)) as image:
            pixels = np.array(image.convert("RGB"))

        expected_shape = (height, width, 3)
        if pixels.shape != expected_shape:
            raise RuntimeError(
                label + " 캔버스 크기 불일치: " + str(pixels.shape)
            )

        return pixels
    finally:
        page.close()


def compare(reference: np.ndarray, student: np.ndarray) -> Dict[str, float]:
    reference_mask = np.any(reference != 255, axis=2)
    student_mask = np.any(student != 255, axis=2)

    union = reference_mask | student_mask
    intersection = reference_mask & student_mask
    different = np.any(reference != student, axis=2)

    union_count = int(union.sum())

    result: Dict[str, float] = {
        "reference_foreground": float(reference_mask.sum()),
        "student_foreground": float(student_mask.sum()),
        "different_pixels": float(different.sum()),
    }

    if union_count == 0:
        result.update(
            {
                "full_match": 100.0,
                "foreground_match": 100.0,
                "foreground_overlap": 100.0,
                "foreground_color_error": 0.0,
            }
        )
        return result

    color_error = np.abs(
        reference.astype(np.int16) - student.astype(np.int16)
    )

    result.update(
        {
            "full_match": float(100 * (1 - different.mean())),
            "foreground_match": float(100 * (1 - different[union].mean())),
            "foreground_overlap": float(
                100 * int(intersection.sum()) / union_count
            ),
            "foreground_color_error": float(color_error[union].mean()),
        }
    )

    return result


def render_and_compare(
    payload: Dict[str, Any],
    p5_path: Path,
) -> Tuple[Dict[str, Any], float]:
    width = int(payload["canvas_width"])
    height = int(payload["canvas_height"])

    if width != 400 or height != 400:
        raise RuntimeError("현재 dry-run은 400×400 캔버스만 지원합니다.")

    p5_source = p5_path.read_text(encoding="utf-8", errors="replace")

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
            reference = render_code(
                browser,
                p5_source,
                payload["reference_code"],
                "정답",
                width,
                height,
            )
            student = render_code(
                browser,
                p5_source,
                payload["student_code"],
                "제출",
                width,
                height,
            )
        finally:
            browser.close()

    metrics: Dict[str, Any] = dict(compare(reference, student))

    # 서버가 채점 설정을 명시한 경우에만 허용 오차 비교를 추가한다.
    if "grading_config" in payload:
        config = payload["grading_config"]

        if not isinstance(config, dict):
            raise RuntimeError("채점 설정은 JSON 객체여야 합니다.")

        if config.get("metric") != "symmetric_foreground_color_coverage":
            raise RuntimeError("지원하지 않는 허용 오차 비교 방식입니다.")

        if config.get("neighborhood") != "euclidean_disk":
            raise RuntimeError("지원하지 않는 주변 픽셀 탐색 방식입니다.")

        # 기존 실행과 목표 PNG 생성에 불필요한 의존성이 생기지 않도록
        # 허용 오차 비교를 요청한 경우에만 가져온다.
        from course_simulation_image_metrics import (
            symmetric_foreground_color_coverage,
        )

        metrics["tolerance"] = symmetric_foreground_color_coverage(
            reference,
            student,
            position_tolerance_px=config.get("position_tolerance_px"),
            color_tolerance=config.get("color_tolerance"),
        )

        scoring_mode = config.get("scoring_mode", "metrics_only")
        if scoring_mode not in ("metrics_only", "whole_image", "regions"):
            raise RuntimeError("지원하지 않는 점수 산정 방식입니다.")

        if scoring_mode == "regions":
            if config.get("scoring_method") != "region_coverage_linear_v1":
                raise RuntimeError("지원하지 않는 영역별 점수 산정 규칙입니다.")

            reference_max_score = payload.get("reference_max_score")
            if not isinstance(reference_max_score, str):
                raise RuntimeError("영역별 비교에 필요한 전체 배점이 없습니다.")

            from course_simulation_region_metrics import (
                weighted_region_color_coverage,
            )

            metrics["region_comparison"] = weighted_region_color_coverage(
                reference,
                student,
                regions=config.get("regions"),
                max_score=reference_max_score,
                position_tolerance_px=config.get("position_tolerance_px"),
                color_tolerance=config.get("color_tolerance"),
            )


    elapsed = time.perf_counter() - started

    return metrics, elapsed


def print_text_result(payload: Dict[str, Any], result: Dict[str, Any], elapsed: float) -> None:
    print("활동 ID:", payload["activity_id"])
    print("정답 버전:", payload["reference_no"])
    print("제출 ID:", payload["submission_id"])
    print("제출 회차:", payload["submission_no"])
    print("정답 전경 픽셀 수:", int(result["reference_foreground"]))
    print("제출 전경 픽셀 수:", int(result["student_foreground"]))
    print("다른 픽셀 수:", int(result["different_pixels"]))
    print("전체 화면 일치율: {:.3f}%".format(result["full_match"]))
    print("전경 합집합 내 일치율: {:.3f}%".format(result["foreground_match"]))
    print("전경 영역 겹침률: {:.3f}%".format(result["foreground_overlap"]))
    print(
        "전경 합집합 내 평균 색상 오차: {:.3f}".format(
            result["foreground_color_error"]
        )
    )
    print("렌더링·비교 시간: {:.3f}초".format(elapsed))
    print("결과: dry-run 렌더링 비교 정상")


def build_json_result(
    payload: Dict[str, Any],
    result: Dict[str, Any],
    elapsed: float,
) -> Dict[str, Any]:
    return {
        "activity_id": int(payload["activity_id"]),
        "reference_no": int(payload["reference_no"]),
        "submission_id": int(payload["submission_id"]),
        "submission_no": int(payload["submission_no"]),
        "metrics": result,
        "elapsed_seconds": elapsed,
        "status": "ok",
    }


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Course 시뮬레이션 정적 그림 렌더링 비교 dry-run"
    )
    parser.add_argument(
        "--input-json",
        required=True,
        type=Path,
        help="정답 코드와 제출 코드가 담긴 입력 JSON 경로",
    )
    parser.add_argument(
        "--p5",
        required=True,
        type=Path,
        help="p5.min.js 파일 경로",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="결과를 JSON으로 출력",
    )

    args = parser.parse_args()

    payload = load_payload(args.input_json)
    result, elapsed = render_and_compare(payload, args.p5)

    if args.json:
        print(
            json.dumps(
                build_json_result(payload, result, elapsed),
                ensure_ascii=False,
                indent=2,
            )
        )
    else:
        print_text_result(payload, result, elapsed)


if __name__ == "__main__":
    main()
