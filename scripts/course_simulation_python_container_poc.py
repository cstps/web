#!/usr/bin/env python3
"""Python 그림 실험용 컨테이너 실행·종료 및 결과 검사."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import stat
import struct
import subprocess
import sys
import tempfile
import time
import uuid
import zlib
from pathlib import Path

from course_simulation_python_render_poc import (
    ENGINE,
    load_request,
    unique_object,
    reject_constant,
)

ROOT = Path(__file__).resolve().parent.parent
SCRIPTS = ROOT / "scripts"
PIXEL_BYTES = 400 * 400 * 4
RAW_BYTES = (400 * 4 + 1) * 400
MAX_PNG_BYTES = 1048576


def docker_prefix():
    socket = "/var/run/docker.sock"
    if os.access(socket, os.R_OK | os.W_OK):
        return ["/usr/bin/docker"]
    return ["/usr/bin/sudo", "-n", "/usr/bin/docker"]


def docker(args, timeout=10):
    try:
        result = subprocess.run(
            docker_prefix() + args,
            capture_output=True,
            timeout=timeout,
        )
    except subprocess.TimeoutExpired:
        raise RuntimeError(
            "Docker 명령의 응답 시간이 초과됐습니다."
        ) from None

    if result.returncode != 0:
        raise RuntimeError(
            "Docker 명령 처리에 실패했습니다: " + args[0]
        )

    return result.stdout


def container_state(name):
    return json.loads(docker([
        "inspect", "--format", "{{json .State}}", name,
    ]))


def read_regular_file(path, limit):
    flags = os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW
    fd = os.open(path, flags)

    with os.fdopen(fd, "rb") as file:
        info = os.fstat(file.fileno())

        if not stat.S_ISREG(info.st_mode) or info.st_size > limit:
            raise RuntimeError(
                "결과 파일의 형식 또는 크기가 올바르지 않습니다."
            )

        data = file.read(limit + 1)

        if len(data) > limit:
            raise RuntimeError(
                "결과 파일 크기가 허용 범위를 초과했습니다."
            )

    return data


def validate_png(data):
    if not data.startswith(b"\x89PNG\r\n\x1a\n"):
        raise RuntimeError("PNG 서명이 올바르지 않습니다.")

    offset = 8
    kinds = []
    compressed = bytearray()

    while offset < len(data):
        if offset + 12 > len(data):
            raise RuntimeError("PNG 청크가 잘렸습니다.")

        length = struct.unpack(">I", data[offset:offset + 4])[0]
        kind = data[offset + 4:offset + 8]
        end = offset + 12 + length

        if end > len(data):
            raise RuntimeError("PNG 청크 길이가 올바르지 않습니다.")

        body = data[offset + 8:offset + 8 + length]
        crc = struct.unpack(">I", data[end - 4:end])[0]

        if (zlib.crc32(kind + body) & 0xffffffff) != crc:
            raise RuntimeError("PNG 청크 검사값이 일치하지 않습니다.")

        kinds.append(kind)

        if kind == b"IHDR":
            expected = struct.pack(
                ">IIBBBBB", 400, 400, 8, 6, 0, 0, 0
            )
            if body != expected:
                raise RuntimeError(
                    "PNG 이미지 조건이 일치하지 않습니다."
                )
        elif kind == b"IDAT":
            compressed.extend(body)
        elif kind == b"IEND":
            if body or end != len(data):
                raise RuntimeError(
                    "PNG 종료 구조가 올바르지 않습니다."
                )
        else:
            raise RuntimeError("지원하지 않는 PNG 청크입니다.")

        offset = end

    if kinds != [b"IHDR", b"IDAT", b"IEND"]:
        raise RuntimeError("PNG 청크 순서가 올바르지 않습니다.")

    decoder = zlib.decompressobj()
    raw = decoder.decompress(bytes(compressed), RAW_BYTES + 1)

    if (
        len(raw) != RAW_BYTES or
        not decoder.eof or
        decoder.unconsumed_tail or
        decoder.unused_data
    ):
        raise RuntimeError("PNG 압축 데이터가 올바르지 않습니다.")

    stride = 1601

    if any(raw[y * stride] != 0 for y in range(400)):
        raise RuntimeError("지원하지 않는 PNG 필터입니다.")

    pixels = b"".join(
        raw[y * stride + 1:(y + 1) * stride]
        for y in range(400)
    )

    if len(pixels) != PIXEL_BYTES:
        raise RuntimeError("PNG 픽셀 크기가 올바르지 않습니다.")

    return hashlib.sha256(pixels).hexdigest()


def run_container(input_path, image_id, seconds):
    if not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id):
        raise ValueError("전체 로컬 이미지 ID가 필요합니다.")

    if type(seconds) is not int or not 1 <= seconds <= 40:
        raise ValueError("실행 제한 시간은 1~40초여야 합니다.")

    if os.getuid() == 0:
        raise RuntimeError("일반 사용자 계정에서 실행해 주세요.")

    runtime = (
        SCRIPTS / "course_simulation_python_runtime_poc.py"
    ).read_bytes()

    runner = (
        SCRIPTS / "course_simulation_python_render_poc.py"
    ).read_bytes()

    if len(runtime) > 100000:
        raise RuntimeError("그림 함수 파일이 너무 큽니다.")

    runtime_hash = hashlib.sha256(runtime).hexdigest()
    request = load_request(input_path, runtime_hash)
    name = "simulation-python-managed-" + uuid.uuid4().hex[:16]

    # 생성 요청 직후 오류가 발생해도 이번 컨테이너를 정리한다.
    attempted = False
    png = None

    with tempfile.TemporaryDirectory(
        prefix="python-managed-"
    ) as directory:
        work = Path(directory)
        output = work / "output"
        output.mkdir(mode=0o700)

        (work / "runner.py").write_bytes(runner)
        (work / "runtime.py").write_bytes(runtime)
        (work / "request.json").write_text(
            json.dumps(request, ensure_ascii=False),
            encoding="utf-8",
        )

        command = [
            "run", "-d", "--name", name,
            "--network=none",
            "--read-only",
            "--user", str(os.getuid()) + ":" + str(os.getgid()),
            "--cap-drop=ALL",
            "--security-opt=no-new-privileges",
            "--cpus=1",
            "--memory=512m",
            "--memory-swap=512m",
            "--pids-limit=128",
            "--ulimit", "nofile=64:64",
            "--ulimit", "fsize=2097152:2097152",
            "--log-driver=json-file",
            "--log-opt", "max-size=64k",
            "--log-opt", "max-file=1",
            "--tmpfs", "/tmp:rw,nosuid,nodev,noexec,size=128m",
            "-e", "PYTHONDONTWRITEBYTECODE=1",
        ]

        for local, remote in (
            (work / "runner.py", "/work/runner.py"),
            (work / "runtime.py", "/work/runtime.py"),
            (work / "request.json", "/work/request.json"),
        ):
            command.extend([
                "-v", str(local) + ":" + remote + ":ro"
            ])

        command.extend([
            "-v", str(output) + ":/output:rw",
            "--entrypoint", "python", image_id,
            "/work/runner.py",
            "--input-json", "/work/request.json",
            "--runtime", "/work/runtime.py",
            "--output-png", "/output/result.png",
        ])

        try:
            started = time.monotonic()
            attempted = True
            docker(command)
            deadline = started + seconds
            timed_out = False

            while True:
                state = container_state(name)

                if not state["Running"]:
                    break

                if time.monotonic() >= deadline:
                    timed_out = True

                    # 종료 시점이 겹친 경우 실패 후 상태를 다시 확인한다.
                    try:
                        docker(["kill", "--signal=KILL", name])
                    except RuntimeError:
                        if container_state(name)["Running"]:
                            raise

                    break

                time.sleep(0.2)

            stop_deadline = time.monotonic() + 5

            while True:
                state = container_state(name)

                if not state["Running"]:
                    break

                if time.monotonic() >= stop_deadline:
                    raise RuntimeError(
                        "컨테이너 종료를 확인하지 못했습니다."
                    )

                time.sleep(0.1)

            if timed_out:
                result = {
                    "status": "error",
                    "code": "timeout",
                }
            elif state.get("OOMKilled"):
                result = {
                    "status": "error",
                    "code": "memory_limit",
                }
            elif state["ExitCode"] != 0:
                code = {
                    1: "runtime_error",
                    2: "invalid_request",
                    3: "syntax_error",
                }.get(state["ExitCode"], "execution_error")

                result = {
                    "status": "error",
                    "code": code,
                }
            else:
                logs = docker(["logs", "--tail", "1", name])

                if len(logs) > 65536:
                    raise RuntimeError(
                        "실행 정보 크기가 너무 큽니다."
                    )

                report = json.loads(
                    logs.decode("utf-8"),
                    object_pairs_hook=unique_object,
                    parse_constant=reject_constant,
                )

                if not isinstance(report, dict):
                    raise RuntimeError(
                        "실행 정보가 올바르지 않습니다."
                    )

                expected = {
                    "schema_version": 1,
                    "status": "ok",
                    "engine": ENGINE,
                    "runtime_sha256": runtime_hash,
                    "frame": request["frame"],
                    "width": 400,
                    "height": 400,
                }

                for key, value in expected.items():
                    if (
                        type(report.get(key)) is not type(value) or
                        report[key] != value
                    ):
                        raise RuntimeError(
                            "실행 정보가 요청과 일치하지 않습니다."
                        )

                png = read_regular_file(
                    output / "result.png",
                    MAX_PNG_BYTES,
                )
                pixels_hash = validate_png(png)
                png_hash = hashlib.sha256(png).hexdigest()

                if (
                    report.get("pixels_sha256") != pixels_hash or
                    report.get("png_sha256") != png_hash
                ):
                    raise RuntimeError(
                        "실행 정보와 PNG 해시가 일치하지 않습니다."
                    )

                result = dict(expected)
                result["pixels_sha256"] = pixels_hash
                result["png_sha256"] = png_hash

        finally:
            if attempted:
                # 이번 실행에서 지정한 이름의 컨테이너만 정리한다.
                docker(["rm", "-f", name])

    return result, png


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--image-id", required=True)
    parser.add_argument("--output-png", required=True)
    parser.add_argument("--timeout", type=int, default=8)
    args = parser.parse_args()

    target = Path(args.output_png)

    if target.exists() or target.is_symlink():
        raise RuntimeError(
            "결과 저장 경로에 기존 파일이 있습니다."
        )

    result, png = run_container(
        args.input_json,
        args.image_id,
        args.timeout,
    )

    if png is not None:
        with target.open("xb") as file:
            file.write(png)

    print(json.dumps(result, ensure_ascii=False))
    return 0 if result["status"] == "ok" else 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        print(json.dumps({
            "status": "error",
            "code": "manager_error",
            "message": "실행 관리 또는 결과 검사에 실패했습니다.",
        }, ensure_ascii=False))
        sys.exit(2)
