#!/usr/bin/env python3
"""문제은행 Python 정답 실행과 별도 컨테이너의 영역 검사를 연결한다."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import tempfile
import time
import uuid
from pathlib import Path

sys.dont_write_bytecode = True

from course_simulation_python_container_poc import (
    SCRIPTS, container_state, docker, read_regular_file, run_container,
)
from course_simulation_python_render_poc import (
    load_request, unique_object, reject_constant,
)


def load_json(path):
    return json.loads(
        read_regular_file(path, 65536).decode("utf-8"),
        object_pairs_hook=unique_object,
        parse_constant=reject_constant,
    )


def validate_grading(payload):
    if not isinstance(payload, dict) or set(payload) != {
        "max_score", "grading_config"
    }:
        raise ValueError("전체 배점과 채점 설정 항목을 확인해 주세요.")

    score = payload["max_score"]
    if (
        not isinstance(score, str) or
        re.fullmatch(r"(0|[1-9][0-9]{0,4})(?:\.[0-9]{1,2})?", score) is None or
        not any(character in "123456789" for character in score)
    ):
        raise ValueError("전체 배점 형식이 올바르지 않습니다.")

    config = payload["grading_config"]
    if not isinstance(config, dict):
        raise ValueError("채점 설정은 JSON 객체여야 합니다.")

    mode = config.get("scoring_mode")
    if mode not in ("metrics_only", "whole_image", "regions"):
        raise ValueError("지원하지 않는 점수 산정 방식입니다.")

    if (
        config.get("schema_version") != 1 or
        type(config.get("schema_version")) is not int or
        config.get("metric") != "symmetric_foreground_color_coverage" or
        config.get("neighborhood") != "euclidean_disk" or
        type(config.get("color_tolerance")) is not int or
        config["color_tolerance"] != 0
    ):
        raise ValueError("지원하지 않는 그림 비교 설정입니다.")

    radius = config.get("position_tolerance_px")
    if type(radius) is not int or not 0 <= radius <= 10:
        raise ValueError("위치 허용 반경은 0~10 사이의 정수여야 합니다.")

    methods = {
        "metrics_only": None,
        "whole_image": "coverage_linear_v1",
        "regions": "region_coverage_linear_v1",
    }
    if config.get("scoring_method") != methods[mode]:
        raise ValueError("점수 산정 방식과 계산 방법이 일치하지 않습니다.")

    if mode == "regions":
        regions = config.get("regions")
        if not isinstance(regions, list) or not 1 <= len(regions) <= 20:
            raise ValueError("채점 영역은 1~20개여야 합니다.")
    else:
        # 전체 그림 방식도 빈 정답 그림을 허용하지 않는다.
        regions = [{
            "id": "whole_canvas", "label": "전체 그림",
            "x": 0, "y": 0, "width": 400, "height": 400,
            "max_score": score,
        }]

    return mode, score, radius, regions


def compare_reference(png, image_id, score, radius, regions):
    digest = hashlib.sha256(png).hexdigest()
    name = "bank-python-check-" + uuid.uuid4().hex[:16]
    attempted = False

    with tempfile.TemporaryDirectory(prefix="bank-python-check-") as directory:
        work = Path(directory)
        png_path = work / "reference.png"
        config_path = work / "comparison.json"
        png_path.write_bytes(png)
        config_path.write_text(json.dumps({
            "schema_version": 1,
            "reference_png_sha256": digest,
            "student_png_sha256": digest,
            "regions": regions,
            "max_score": score,
            "position_tolerance_px": radius,
        }, ensure_ascii=False, allow_nan=False), encoding="utf-8")

        import os
        command = [
            "run", "-d", "--name", name,
            "--network=none", "--read-only",
            "--user", str(os.getuid()) + ":" + str(os.getgid()),
            "--cap-drop=ALL", "--security-opt=no-new-privileges",
            "--cpus=1", "--memory=512m", "--memory-swap=512m",
            "--pids-limit=128",
            "--ulimit", "nofile=64:64",
            "--log-driver=json-file",
            "--log-opt", "max-size=128k", "--log-opt", "max-file=1",
            "--tmpfs", "/tmp:rw,nosuid,nodev,noexec,size=128m",
            "-e", "PYTHONDONTWRITEBYTECODE=1",
        ]
        for local, remote in (
            (SCRIPTS / "course_simulation_python_compare_poc.py", "/work/compare.py"),
            (SCRIPTS / "course_simulation_image_metrics.py",
             "/work/course_simulation_image_metrics.py"),
            (SCRIPTS / "course_simulation_region_metrics.py",
             "/work/course_simulation_region_metrics.py"),
            (png_path, "/work/reference.png"),
            (config_path, "/work/comparison.json"),
        ):
            if not local.is_file():
                raise RuntimeError("비교에 필요한 파일이 없습니다.")
            command.extend(["-v", str(local) + ":" + remote + ":ro"])

        command.extend([
            "--entrypoint", "python", image_id,
            "/work/compare.py",
            "--input-json", "/work/comparison.json",
            "--reference-png", "/work/reference.png",
            "--student-png", "/work/reference.png",
        ])

        try:
            attempted = True
            docker(command)
            deadline = time.monotonic() + 40

            while True:
                state = container_state(name)
                if not state["Running"]:
                    break
                if time.monotonic() >= deadline:
                    raise RuntimeError("정답 영역 검사 제한 시간이 초과됐습니다.")
                time.sleep(0.2)

            if state.get("OOMKilled") or state["ExitCode"] != 0:
                raise RuntimeError(
                    "정답 그림 검사 실패: 영역의 도형 포함 여부·경계 여백·배점을 확인해 주세요."
                )

            raw = docker(["logs", name])
            if len(raw) > 65536:
                raise RuntimeError("영역 검사 결과가 너무 큽니다.")

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
                report.get("reference_png_sha256") != digest or
                report.get("student_png_sha256") != digest
            ):
                raise RuntimeError("영역 검사 결과와 PNG가 일치하지 않습니다.")

            metrics = report["metrics"]
            comparison = metrics["region_comparison"]
            if (
                comparison["final_coverage"] != 100.0 or
                comparison["outside_student_foreground"] != 0 or
                metrics["tolerance"]["symmetric_coverage"] != 100.0
            ):
                raise RuntimeError("정답 그림의 자체 비교 결과가 올바르지 않습니다.")

            return {
                "comparison": comparison,
                "tolerance": metrics["tolerance"],
            }
        finally:
            if attempted:
                # 시간 초과·오류 시에도 이번 비교 컨테이너를 직접 종료·제거한다.
                docker(["rm", "-f", name])


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--grading-json", required=True)
    parser.add_argument("--image-id", required=True)
    parser.add_argument("--output-png", required=True)
    args = parser.parse_args()

    target = Path(args.output_png)
    if target.exists() or target.is_symlink():
        raise RuntimeError("기존 결과 파일을 덮어쓰지 않습니다.")

    runtime = SCRIPTS / "course_simulation_python_runtime_poc.py"
    runtime_hash = hashlib.sha256(runtime.read_bytes()).hexdigest()
    request = load_request(args.input_json, runtime_hash)
    if request["frame"] != 0:
        raise ValueError("현재 문제은행 정답은 프레임 0만 지원합니다.")

    mode, score, radius, regions = validate_grading(load_json(args.grading_json))
    started = time.monotonic()

    result, png = run_container(args.input_json, args.image_id, 8)
    if result["status"] != "ok" or png is None:
        raise RuntimeError(
            "Python 정답 실행 실패: " + str(result.get("code", "execution_error"))
        )

    validation = compare_reference(png, args.image_id, score, radius, regions)
    if hashlib.sha256(runtime.read_bytes()).hexdigest() != runtime_hash:
        raise RuntimeError("실행 중 그림 함수 파일이 변경됐습니다.")

    report = {
        "schema_version": 1,
        "status": "ok",
        "engine": result["engine"],
        "runtime_sha256": runtime_hash,
        "source_sha256": hashlib.sha256(
            request["source_code"].encode("utf-8")
        ).hexdigest(),
        "frame": 0,
        "width": 400,
        "height": 400,
        "png_sha256": hashlib.sha256(png).hexdigest(),
        "png_bytes": len(png),
        "pixels_sha256": result["pixels_sha256"],
        "elapsed_seconds": time.monotonic() - started,
    }
    if mode == "regions":
        report["region_validation"] = validation

    # 실행·검사 컨테이너가 모두 정리된 뒤에만 결과를 기록한다.
    try:
        with target.open("xb") as file:
            try:
                file.write(png)
            except BaseException:
                target.unlink(missing_ok=True)
                raise
    except FileExistsError:
        raise RuntimeError("결과 경로에 다른 파일이 먼저 생성됐습니다.") from None

    print(json.dumps(report, ensure_ascii=False, allow_nan=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print("Python 목표 그림 생성 실패: " + str(error), file=sys.stderr)
        sys.exit(1)
