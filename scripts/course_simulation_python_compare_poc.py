#!/usr/bin/env python3
"""별도 비교 컨테이너에서 Python 그림의 PNG 두 장을 비교한다."""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import re
import stat
import sys
from pathlib import Path


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError("비교 요청에 중복 항목이 있습니다.")
        result[key] = value
    return result


def reject_constant(value):
    raise ValueError("비교 요청에 올바르지 않은 숫자가 있습니다.")


def read_regular_file(path, limit):
    fd = os.open(
        path,
        os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW,
    )

    with os.fdopen(fd, "rb") as file:
        info = os.fstat(file.fileno())

        if not stat.S_ISREG(info.st_mode) or info.st_size > limit:
            raise ValueError("비교 파일의 형식 또는 크기가 올바르지 않습니다.")

        data = file.read(limit + 1)

    if len(data) > limit:
        raise ValueError("비교 파일이 허용 크기를 초과했습니다.")

    return data


def load_config(path):
    config = json.loads(
        read_regular_file(path, 65536).decode("utf-8"),
        object_pairs_hook=unique_object,
        parse_constant=reject_constant,
    )

    required = {
        "schema_version",
        "reference_png_sha256",
        "student_png_sha256",
        "regions",
        "max_score",
        "position_tolerance_px",
    }

    if not isinstance(config, dict) or set(config) != required:
        raise ValueError("비교 요청의 필수 항목이 올바르지 않습니다.")

    if (
        type(config["schema_version"]) is not int or
        config["schema_version"] != 1
    ):
        raise ValueError("지원하지 않는 비교 요청 형식입니다.")

    for key in ("reference_png_sha256", "student_png_sha256"):
        value = config[key]
        if (
            not isinstance(value, str) or
            re.fullmatch(r"[0-9a-f]{64}", value) is None
        ):
            raise ValueError("비교할 PNG 해시가 올바르지 않습니다.")

    radius = config["position_tolerance_px"]
    if type(radius) is not int or not 0 <= radius <= 10:
        raise ValueError("위치 허용 반경은 0~10 사이의 정수여야 합니다.")

    if not isinstance(config["max_score"], str):
        raise ValueError("전체 배점은 문자열이어야 합니다.")

    if (
        not isinstance(config["regions"], list) or
        not 1 <= len(config["regions"]) <= 20
    ):
        raise ValueError("채점 영역은 1~20개여야 합니다.")

    return config


def load_pixels(path, expected_hash):
    import numpy as np
    from PIL import Image

    data = read_regular_file(path, 1048576)

    if hashlib.sha256(data).hexdigest() != expected_hash:
        raise ValueError("비교할 PNG 파일의 해시가 일치하지 않습니다.")

    if not data.startswith(b"\x89PNG\r\n\x1a\n"):
        raise ValueError("PNG 파일 서명이 올바르지 않습니다.")

    with Image.open(io.BytesIO(data)) as image:
        if (
            image.format != "PNG" or
            image.size != (400, 400) or
            image.mode != "RGBA" or
            getattr(image, "n_frames", 1) != 1
        ):
            raise ValueError("비교할 PNG 이미지 조건이 올바르지 않습니다.")

        image.load()
        rgba = np.array(image, dtype=np.uint8)

    if rgba.shape != (400, 400, 4):
        raise ValueError("PNG 픽셀 배열의 크기가 올바르지 않습니다.")

    if not np.all(rgba[:, :, 3] == 255):
        raise ValueError("투명 픽셀이 있는 PNG는 지원하지 않습니다.")

    return rgba[:, :, :3].copy()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--reference-png", required=True)
    parser.add_argument("--student-png", required=True)
    args = parser.parse_args()

    if not Path("/.dockerenv").exists():
        raise RuntimeError("이 비교기는 Docker 내부에서 사용해 주세요.")

    config = load_config(args.input_json)

    # 제출 코드를 불러오거나 실행하지 않고 PNG 두 장만 읽는다.
    reference = load_pixels(
        args.reference_png,
        config["reference_png_sha256"],
    )
    student = load_pixels(
        args.student_png,
        config["student_png_sha256"],
    )

    from course_simulation_image_metrics import (
        symmetric_foreground_color_coverage,
    )
    from course_simulation_region_metrics import (
        weighted_region_color_coverage,
    )

    radius = config["position_tolerance_px"]

    whole = symmetric_foreground_color_coverage(
        reference,
        student,
        position_tolerance_px=radius,
        color_tolerance=0,
    )

    regions = weighted_region_color_coverage(
        reference,
        student,
        regions=config["regions"],
        max_score=config["max_score"],
        position_tolerance_px=radius,
        color_tolerance=0,
    )

    print(json.dumps({
        "schema_version": 1,
        "status": "ok",
        "reference_png_sha256": config["reference_png_sha256"],
        "student_png_sha256": config["student_png_sha256"],
        "metrics": {
            "tolerance": whole,
            "region_comparison": regions,
        },
    }, ensure_ascii=False, allow_nan=False))


if __name__ == "__main__":
    try:
        main()
    except Exception:
        print(json.dumps({
            "status": "error",
            "code": "comparison_error",
            "message": "그림 비교에 실패했습니다.",
        }, ensure_ascii=False))
        sys.exit(1)
