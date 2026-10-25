#!/usr/bin/env python3
"""문제은행의 정답·제출 PNG를 별도 컨테이너에서 비교한다."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import tempfile
import time
import uuid
from pathlib import Path

sys.dont_write_bytecode = True

from course_simulation_python_container_poc import (
    SCRIPTS, container_state, docker, read_regular_file,
)
from course_simulation_python_compare_poc import load_config
from course_simulation_python_render_poc import (
    unique_object, reject_constant,
)


def compare_images(config, reference_png, student_png, image_id):
    if (
        not isinstance(image_id, str) or
        re.fullmatch(r"sha256:[0-9a-f]{64}", image_id) is None
    ):
        raise ValueError("전체 로컬 이미지 ID가 필요합니다.")

    if os.getuid() == 0:
        raise RuntimeError("일반 사용자 계정에서 실행해 주세요.")

    for png in (reference_png, student_png):
        if (
            not isinstance(png, bytes) or
            not 1 <= len(png) <= 1048576 or
            not png.startswith(b"\x89PNG\r\n\x1a\n")
        ):
            raise ValueError("비교할 PNG 데이터가 올바르지 않습니다.")

    reference_hash = hashlib.sha256(reference_png).hexdigest()
    student_hash = hashlib.sha256(student_png).hexdigest()
    name = "bank-image-compare-" + uuid.uuid4().hex[:16]
    attempted = False

    with tempfile.TemporaryDirectory(prefix="bank-image-compare-") as directory:
        work = Path(directory)
        config_path = work / "comparison.json"
        reference_path = work / "reference.png"
        student_path = work / "student.png"

        config_path.write_text(
            json.dumps(config, ensure_ascii=False, allow_nan=False),
            encoding="utf-8",
        )
        checked = load_config(config_path)

        if (
            checked["reference_png_sha256"] != reference_hash or
            checked["student_png_sha256"] != student_hash
        ):
            raise ValueError("비교 요청과 실제 PNG 해시가 다릅니다.")

        reference_path.write_bytes(reference_png)
        student_path.write_bytes(student_png)

        command = [
            "run", "-d", "--name", name,
            "--network=none", "--read-only",
            "--user", str(os.getuid()) + ":" + str(os.getgid()),
            "--cap-drop=ALL", "--security-opt=no-new-privileges",
            "--cpus=1", "--memory=512m", "--memory-swap=512m",
            "--pids-limit=128",
            "--ulimit", "nofile=64:64",
            "--log-driver=json-file",
            "--log-opt", "max-size=128k",
            "--log-opt", "max-file=1",
            "--tmpfs", "/tmp:rw,nosuid,nodev,noexec,size=128m",
            "-e", "PYTHONDONTWRITEBYTECODE=1",
        ]

        for local, remote in (
            (
                SCRIPTS / "course_simulation_python_compare_poc.py",
                "/work/compare.py",
            ),
            (
                SCRIPTS / "course_simulation_image_metrics.py",
                "/work/course_simulation_image_metrics.py",
            ),
            (
                SCRIPTS / "course_simulation_region_metrics.py",
                "/work/course_simulation_region_metrics.py",
            ),
            (config_path, "/work/comparison.json"),
            (reference_path, "/work/reference.png"),
            (student_path, "/work/student.png"),
        ):
            if not local.is_file():
                raise RuntimeError("비교에 필요한 파일이 없습니다.")
            command.extend(["-v", str(local) + ":" + remote + ":ro"])

        command.extend([
            "--entrypoint", "python", image_id,
            "/work/compare.py",
            "--input-json", "/work/comparison.json",
            "--reference-png", "/work/reference.png",
            "--student-png", "/work/student.png",
        ])

        try:
            started = time.monotonic()
            attempted = True
            docker(command)
            deadline = started + 40

            while True:
                state = container_state(name)
                if not state["Running"]:
                    break
                if time.monotonic() >= deadline:
                    raise RuntimeError("그림 비교 제한 시간이 초과됐습니다.")
                time.sleep(0.2)

            if state.get("OOMKilled"):
                raise RuntimeError("그림 비교 메모리 제한을 초과했습니다.")

            if state["ExitCode"] != 0:
                raise RuntimeError("그림 비교 또는 채점 영역 검사에 실패했습니다.")

            raw = docker(["logs", name])

            if len(raw) > 65536:
                raise RuntimeError("그림 비교 결과가 너무 큽니다.")

            report = json.loads(
                raw.decode("utf-8"),
                object_pairs_hook=unique_object,
                parse_constant=reject_constant,
            )

            if (
                not isinstance(report, dict) or
                type(report.get("schema_version")) is not int or
                report["schema_version"] != 1 or
                report.get("status") != "ok" or
                report.get("reference_png_sha256") != reference_hash or
                report.get("student_png_sha256") != student_hash or
                not isinstance(report.get("metrics"), dict) or
                not isinstance(report["metrics"].get("tolerance"), dict) or
                not isinstance(
                    report["metrics"].get("region_comparison"), dict
                )
            ):
                raise RuntimeError("그림 비교 결과와 요청 정보가 일치하지 않습니다.")

            # 픽셀 수·충족률·점수의 재검산은 PHP 저장 처리에서 수행한다.
            return report

        finally:
            if attempted:
                # 정상·오류·시간 초과 모두 이번 컨테이너만 직접 제거한다.
                docker(["rm", "-f", name])


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--reference-png", required=True)
    parser.add_argument("--student-png", required=True)
    parser.add_argument("--image-id", required=True)
    args = parser.parse_args()

    report = compare_images(
        load_config(args.input_json),
        read_regular_file(args.reference_png, 1048576),
        read_regular_file(args.student_png, 1048576),
        args.image_id,
    )

    print(json.dumps(report, ensure_ascii=False, allow_nan=False))


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
