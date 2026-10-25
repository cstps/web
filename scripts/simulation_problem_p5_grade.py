#!/usr/bin/env python3
"""p5.js 제출의 격리 렌더링과 별도 PNG 비교를 관리한다."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import struct
import sys
import tempfile
import time
import uuid
from pathlib import Path

sys.dont_write_bytecode = True

from course_simulation_python_container_poc import (
    SCRIPTS, container_state, docker, read_regular_file,
)
from simulation_problem_p5_render import (
    load_request, unique_object, reject_constant,
)
from simulation_problem_python_reference import load_json, validate_grading
from simulation_problem_compare import compare_images

P5_FILE = SCRIPTS.parent / "template/syzoj/js/vendor/p5.min.js"


def render_p5(input_path, image_id, seconds=20):
    if (
        not isinstance(image_id, str) or
        re.fullmatch(r"sha256:[0-9a-f]{64}", image_id) is None
    ):
        raise ValueError("전체 로컬 이미지 ID가 필요합니다.")

    if type(seconds) is not int or not 1 <= seconds <= 40:
        raise ValueError("실행 제한 시간은 1~40초여야 합니다.")

    if os.getuid() == 0:
        raise RuntimeError("일반 사용자 계정에서 실행해 주세요.")

    p5_bytes = read_regular_file(P5_FILE, 4194304)
    p5_hash = hashlib.sha256(p5_bytes).hexdigest()
    request = load_request(input_path, p5_hash)
    source_hash = hashlib.sha256(
        request["source_code"].encode("utf-8")
    ).hexdigest()

    name = "bank-p5-render-" + uuid.uuid4().hex[:16]
    attempted = False

    with tempfile.TemporaryDirectory(prefix="bank-p5-render-") as directory:
        work = Path(directory)
        output = work / "output"
        output.mkdir(mode=0o700)

        (work / "p5.min.js").write_bytes(p5_bytes)
        (work / "request.json").write_text(
            json.dumps(request, ensure_ascii=False, allow_nan=False),
            encoding="utf-8",
        )

        for source, target in (
            ("simulation_problem_p5_render.py", "runner.py"),
            ("course_simulation_render_grade.py", "course_simulation_render_grade.py"),
        ):
            (work / target).write_bytes(
                read_regular_file(SCRIPTS / source, 1048576)
            )

        command = [
            "run", "-d", "--name", name,
            "--network=none", "--read-only",
            "--user", str(os.getuid()) + ":" + str(os.getgid()),
            "--cap-drop=ALL", "--security-opt=no-new-privileges",
            "--cpus=1", "--memory=512m", "--memory-swap=512m",
            "--pids-limit=128",
            "--ulimit", "nofile=1024:1024",
            "--ulimit", "fsize=2097152:2097152",
            "--log-driver=json-file",
            "--log-opt", "max-size=64k",
            "--log-opt", "max-file=1",
            "--tmpfs", "/tmp:rw,nosuid,nodev,size=256m",
            "-e", "PYTHONDONTWRITEBYTECODE=1",
        ]

        for filename in (
            "runner.py",
            "course_simulation_render_grade.py",
            "p5.min.js",
            "request.json",
        ):
            command.extend([
                "-v", str(work / filename) + ":/work/" + filename + ":ro"
            ])

        command.extend([
            "-v", str(output) + ":/output:rw",
            "--entrypoint", "python", image_id,
            "/work/runner.py",
            "--input-json", "/work/request.json",
            "--p5", "/work/p5.min.js",
            "--output-png", "/output/result.png",
        ])

        try:
            started = time.monotonic()
            attempted = True
            docker(command)
            deadline = started + seconds

            while True:
                state = container_state(name)

                if not state["Running"]:
                    break

                if time.monotonic() >= deadline:
                    # finally에서 강제 제거를 완료한 뒤 오류를 반환한다.
                    return {
                        "schema_version": 1,
                        "status": "error",
                        "code": "timeout",
                        "message": "p5.js 코드 실행 제한 시간을 초과했습니다.",
                    }, None

                time.sleep(0.2)

            if state.get("OOMKilled"):
                return {
                    "schema_version": 1,
                    "status": "error",
                    "code": "memory_limit",
                    "message": "p5.js 코드 실행 메모리 제한을 초과했습니다.",
                }, None

            if state["ExitCode"] != 0:
                code = "invalid_request" if state["ExitCode"] == 2 else "render_error"
                return {
                    "schema_version": 1,
                    "status": "error",
                    "code": code,
                    "message": "p5.js 코드 실행 또는 그림 생성에 실패했습니다.",
                }, None

            raw = docker(["logs", "--tail", "1", name])

            if len(raw) > 65536:
                raise RuntimeError("렌더링 결과 정보가 너무 큽니다.")

            report = json.loads(
                raw.decode("utf-8"),
                object_pairs_hook=unique_object,
                parse_constant=reject_constant,
            )

            if not isinstance(report, dict):
                raise RuntimeError("렌더링 결과 정보가 올바르지 않습니다.")

            expected = {
                "schema_version": 1,
                "status": "ok",
                "execution_engine": "p5js",
                "execution_version": "2.3.4",
                "p5_file_sha256": p5_hash,
                "source_sha256": source_hash,
                "frame": 0,
                "width": 400,
                "height": 400,
            }

            for key, value in expected.items():
                if (
                    type(report.get(key)) is not type(value) or
                    report[key] != value
                ):
                    raise RuntimeError("렌더링 결과와 실행 요청이 다릅니다.")

            png = read_regular_file(output / "result.png", 1048576)

            if (
                len(png) < 33 or
                not png.startswith(b"\x89PNG\r\n\x1a\n") or
                png[8:16] != b"\x00\x00\x00\x0dIHDR" or
                struct.unpack(">II", png[16:24]) != (400, 400) or
                png[24] != 8 or
                png[25] not in (2, 6)
            ):
                raise RuntimeError("생성된 PNG의 기본 형식이 올바르지 않습니다.")

            png_hash = hashlib.sha256(png).hexdigest()

            if (
                type(report.get("png_bytes")) is not int or
                report["png_bytes"] != len(png) or
                report.get("png_sha256") != png_hash
            ):
                raise RuntimeError("렌더링 결과와 PNG 해시·크기가 다릅니다.")

            if hashlib.sha256(
                read_regular_file(P5_FILE, 4194304)
            ).hexdigest() != p5_hash:
                raise RuntimeError("실행 중 p5.js 파일이 변경됐습니다.")

            return dict(expected, png_sha256=png_hash, png_bytes=len(png)), png

        finally:
            if attempted:
                docker(["rm", "-f", name])


def grade_p5(input_path, reference_path, grading_path, image_id):
    mode, score, radius, regions = validate_grading(load_json(grading_path))
    reference_png = read_regular_file(reference_path, 1048576)
    started = time.monotonic()

    rendered, student_png = render_p5(input_path, image_id)

    if rendered.get("status") != "ok" or student_png is None:
        return rendered

    # 렌더링 컨테이너 정리가 끝난 뒤 비교 컨테이너를 실행한다.
    comparison = compare_images(
        {
            "schema_version": 1,
            "reference_png_sha256": hashlib.sha256(reference_png).hexdigest(),
            "student_png_sha256": hashlib.sha256(student_png).hexdigest(),
            "regions": regions,
            "max_score": score,
            "position_tolerance_px": radius,
        },
        reference_png,
        student_png,
        image_id,
    )

    return {
        "schema_version": 1,
        "status": "ok",
        "execution_engine": "p5js",
        "execution_version": "2.3.4",
        "p5_file_sha256": rendered["p5_file_sha256"],
        "source_sha256": rendered["source_sha256"],
        "frame": 0,
        "width": 400,
        "height": 400,
        "scoring_mode": mode,
        "reference_png_sha256": comparison["reference_png_sha256"],
        "student_png_sha256": comparison["student_png_sha256"],
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

    result = grade_p5(
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
            "message": "p5.js 제출 코드 채점에 실패했습니다.",
        }, ensure_ascii=False))
        sys.exit(1)
