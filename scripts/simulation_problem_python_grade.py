#!/usr/bin/env python3
"""문제은행 Python 제출의 격리 실행과 정답 PNG 비교를 연결한다."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
import time
from pathlib import Path

sys.dont_write_bytecode = True

from course_simulation_python_container_poc import (
    SCRIPTS, read_regular_file, run_container,
)
from course_simulation_python_render_poc import load_request
from simulation_problem_python_reference import (
    load_json, validate_grading,
)
from simulation_problem_compare import compare_images


def grade_python(input_path, reference_path, grading_path, image_id):
    runtime_path = SCRIPTS / "course_simulation_python_runtime_poc.py"
    runtime = read_regular_file(runtime_path, 100000)
    runtime_hash = hashlib.sha256(runtime).hexdigest()

    request = load_request(input_path, runtime_hash)

    if request["frame"] != 0:
        raise ValueError("현재 정식 그림 채점은 프레임 0만 지원합니다.")

    mode, score, radius, regions = validate_grading(
        load_json(grading_path)
    )

    reference_png = read_regular_file(reference_path, 1048576)
    reference_hash = hashlib.sha256(reference_png).hexdigest()

    if not reference_png.startswith(b"\x89PNG\r\n\x1a\n"):
        raise ValueError("정답 PNG 파일 서명이 올바르지 않습니다.")

    started = time.monotonic()

    # 이 함수가 반환될 때 실행 컨테이너는 이미 정리되어 있다.
    rendered, student_png = run_container(input_path, image_id, 8)

    if rendered.get("status") != "ok" or student_png is None:
        messages = {
            "syntax_error": "Python 코드 문법을 확인해 주세요.",
            "runtime_error": "Python 코드 실행 중 오류가 발생했습니다.",
            "timeout": "코드 실행 제한 시간을 초과했습니다.",
            "memory_limit": "코드 실행 메모리 제한을 초과했습니다.",
            "invalid_request": "코드 실행 요청이 올바르지 않습니다.",
            "execution_error": "코드를 실행하지 못했습니다.",
        }

        error_code = rendered.get("code")
        if error_code not in messages:
            error_code = "execution_error"

        return {
            "schema_version": 1,
            "status": "error",
            "code": error_code,
            "message": messages[error_code],
        }

    student_hash = hashlib.sha256(student_png).hexdigest()

    comparison = compare_images(
        {
            "schema_version": 1,
            "reference_png_sha256": reference_hash,
            "student_png_sha256": student_hash,
            "regions": regions,
            "max_score": score,
            "position_tolerance_px": radius,
        },
        reference_png,
        student_png,
        image_id,
    )

    current_runtime = read_regular_file(runtime_path, 100000)
    if hashlib.sha256(current_runtime).hexdigest() != runtime_hash:
        raise RuntimeError("채점 중 그림 함수 파일이 변경됐습니다.")

    # 충족률 재검산·점수 환산·DB 기록은 PHP 처리기가 담당한다.
    # 코드·PNG 원본과 컨테이너 내부 경로는 결과에 포함하지 않는다.
    return {
        "schema_version": 1,
        "status": "ok",
        "execution_engine": "python-stdlib-rgba-poc",
        "execution_version": "1",
        "runtime_sha256": runtime_hash,
        "source_sha256": hashlib.sha256(
            request["source_code"].encode("utf-8")
        ).hexdigest(),
        "frame": 0,
        "width": 400,
        "height": 400,
        "scoring_mode": mode,
        "reference_png_sha256": reference_hash,
        "student_png_sha256": student_hash,
        "metrics": comparison["metrics"],
        "elapsed_seconds": time.monotonic() - started,
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--reference-png", required=True)
    parser.add_argument("--grading-json", required=True)
    parser.add_argument("--image-id", required=True)
    args = parser.parse_args()

    result = grade_python(
        args.input_json,
        args.reference_png,
        args.grading_json,
        args.image_id,
    )

    print(json.dumps(result, ensure_ascii=False, allow_nan=False))
    return 0 if result["status"] == "ok" else 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        print(json.dumps({
            "schema_version": 1,
            "status": "error",
            "code": "grading_error",
            "message": "제출 코드 채점에 실패했습니다.",
        }, ensure_ascii=False))
        sys.exit(1)
