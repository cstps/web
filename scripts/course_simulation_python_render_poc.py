#!/usr/bin/env python3
"""Docker 내부에서 사용하는 Python 그림 실행 실험기."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

ENGINE = "python-stdlib-rgba-poc-v1"


class RenderRequestError(ValueError):
    pass


def validate_request(payload, expected_runtime_sha256):
    required = {
        "schema_version",
        "engine",
        "runtime_sha256",
        "source_code",
        "frame",
    }

    if not isinstance(payload, dict) or set(payload) != required:
        raise RenderRequestError("실행 요청의 필수 항목을 확인해 주세요.")

    if type(payload["schema_version"]) is not int or payload["schema_version"] != 1:
        raise RenderRequestError("지원하지 않는 요청 형식 버전입니다.")

    if payload["engine"] != ENGINE:
        raise RenderRequestError("지원하지 않는 그림 실행 엔진입니다.")

    if (
        not isinstance(expected_runtime_sha256, str) or
        len(expected_runtime_sha256) != 64 or
        any(c not in "0123456789abcdef" for c in expected_runtime_sha256)
    ):
        raise RenderRequestError("서버 그림 함수 해시가 올바르지 않습니다.")

    if (
        not isinstance(payload["runtime_sha256"], str) or
        payload["runtime_sha256"] != expected_runtime_sha256
    ):
        raise RenderRequestError("그림 함수 버전이 일치하지 않습니다.")

    source = payload["source_code"]
    if (
        not isinstance(source, str) or
        not source.strip() or
        "\x00" in source
    ):
        raise RenderRequestError("실행할 코드가 올바르지 않습니다.")

    try:
        size = len(source.encode("utf-8"))
    except UnicodeError as error:
        raise RenderRequestError("코드 문자 인코딩이 올바르지 않습니다.") from error

    if size > 200000:
        raise RenderRequestError("코드는 200KB 이하여야 합니다.")

    frame = payload["frame"]
    if type(frame) is not int or not 0 <= frame <= 600:
        raise RenderRequestError("프레임은 0~600 사이의 정수여야 합니다.")

    return dict(payload)


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise RenderRequestError("실행 요청에 중복 항목이 있습니다.")
        result[key] = value
    return result


def reject_constant(value):
    raise RenderRequestError("실행 요청에 올바르지 않은 숫자가 있습니다.")


def load_request(path, expected_runtime_sha256):
    with Path(path).open("rb") as file:
        raw = file.read(256001)

    if len(raw) > 256000:
        raise RenderRequestError("실행 요청 크기가 허용 범위를 초과했습니다.")

    try:
        payload = json.loads(
            raw.decode("utf-8"),
            object_pairs_hook=unique_object,
            parse_constant=reject_constant,
        )
    except (UnicodeError, json.JSONDecodeError, RecursionError) as error:
        raise RenderRequestError("실행 요청 JSON이 올바르지 않습니다.") from error

    return validate_request(payload, expected_runtime_sha256)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--runtime", required=True)
    parser.add_argument("--output-png", required=True)
    args = parser.parse_args()

    # 실수로 운영 호스트에서 입력 코드를 실행하지 않도록 한다.
    # 실제 보안 경계는 호출 시 적용하는 Docker 격리·자원 제한이다.
    if not Path("/.dockerenv").exists():
        raise RuntimeError("이 실행기는 Docker 내부에서 사용해 주세요.")

    runtime_bytes = Path(args.runtime).read_bytes()
    if len(runtime_bytes) > 100000:
        raise RuntimeError("그림 함수 파일 크기가 올바르지 않습니다.")

    runtime_sha256 = hashlib.sha256(runtime_bytes).hexdigest()
    request = load_request(args.input_json, runtime_sha256)

    # 코드를 실행하기 전에 문법 오류를 구분한다.
    compile(request["source_code"], "<제출 코드>", "exec")

    namespace = {"__name__": "_python_drawing_runtime"}
    exec(
        compile(runtime_bytes.decode("utf-8"), "<그림 함수>", "exec"),
        namespace,
    )

    namespace["_poc_start"](request["source_code"])

    for frame in range(1, request["frame"] + 1):
        namespace["_poc_step"](frame)

    pixels = namespace["_poc_pixels"]()
    if not isinstance(pixels, bytes) or len(pixels) != 400 * 400 * 4:
        raise RuntimeError("실행 결과의 픽셀 크기가 올바르지 않습니다.")

    png = namespace["_png_bytes"]()
    if not isinstance(png, bytes) or not png.startswith(b"\x89PNG\r\n\x1a\n"):
        raise RuntimeError("PNG 생성 결과가 올바르지 않습니다.")

    Path(args.output_png).write_bytes(png)

    print(json.dumps({
        "schema_version": 1,
        "status": "ok",
        "engine": ENGINE,
        "runtime_sha256": runtime_sha256,
        "frame": request["frame"],
        "width": 400,
        "height": 400,
        "pixels_sha256": hashlib.sha256(pixels).hexdigest(),
        "png_sha256": hashlib.sha256(png).hexdigest(),
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except RenderRequestError as error:
        print(json.dumps({
            "status": "error",
            "code": "invalid_request",
            "message": str(error),
        }, ensure_ascii=False))
        sys.exit(2)
    except SyntaxError as error:
        print(json.dumps({
            "status": "error",
            "code": "syntax_error",
            "message": "Python 코드 문법을 확인해 주세요.",
            "line": error.lineno,
            "offset": error.offset,
        }, ensure_ascii=False))
        sys.exit(3)
    except Exception as error:
        print(json.dumps({
            "status": "error",
            "code": "runtime_error",
            "message": str(error)[:1000],
        }, ensure_ascii=False))
        sys.exit(1)
