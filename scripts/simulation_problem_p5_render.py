#!/usr/bin/env python3
"""격리 컨테이너 내부에서 p5.js 제출 코드를 PNG로 렌더링한다."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import stat
import sys
from io import BytesIO
from pathlib import Path

sys.dont_write_bytecode = True


class P5RequestError(ValueError):
    pass


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise P5RequestError("실행 요청에 중복 항목이 있습니다.")
        result[key] = value
    return result


def reject_constant(value):
    raise P5RequestError("실행 요청에 올바르지 않은 숫자가 있습니다.")


def read_regular_file(path, limit):
    fd = os.open(path, os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW)

    with os.fdopen(fd, "rb") as file:
        info = os.fstat(file.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_size > limit:
            raise P5RequestError("실행 파일의 형식 또는 크기가 올바르지 않습니다.")
        data = file.read(limit + 1)

    if len(data) > limit:
        raise P5RequestError("실행 파일 크기가 허용 범위를 초과했습니다.")

    return data


def validate_request(payload, expected_p5_hash):
    required = {
        "schema_version",
        "execution_engine",
        "execution_version",
        "p5_file_sha256",
        "source_code",
        "frame",
    }

    if not isinstance(payload, dict) or set(payload) != required:
        raise P5RequestError("실행 요청 항목이 올바르지 않습니다.")

    if (
        type(payload["schema_version"]) is not int or
        payload["schema_version"] != 1 or
        payload["execution_engine"] != "p5js" or
        payload["execution_version"] != "2.3.4"
    ):
        raise P5RequestError("지원하지 않는 실행 엔진 또는 버전입니다.")

    if (
        not isinstance(expected_p5_hash, str) or
        re.fullmatch(r"[0-9a-f]{64}", expected_p5_hash) is None or
        payload["p5_file_sha256"] != expected_p5_hash
    ):
        raise P5RequestError("p5.js 실행 파일 해시가 일치하지 않습니다.")

    source = payload["source_code"]

    if (
        not isinstance(source, str) or
        not source.strip() or
        "\0" in source
    ):
        raise P5RequestError("제출 코드가 올바르지 않습니다.")

    try:
        size = len(source.encode("utf-8"))
    except UnicodeError as error:
        raise P5RequestError("제출 코드 문자 인코딩이 올바르지 않습니다.") from error

    if size > 200000:
        raise P5RequestError("제출 코드는 200,000바이트 이하여야 합니다.")

    if type(payload["frame"]) is not int or payload["frame"] != 0:
        raise P5RequestError("현재 정식 그림 채점은 프레임 0만 지원합니다.")

    return dict(payload)


def load_request(path, expected_p5_hash):
    try:
        payload = json.loads(
            read_regular_file(path, 256000).decode("utf-8"),
            object_pairs_hook=unique_object,
            parse_constant=reject_constant,
        )
    except (UnicodeError, json.JSONDecodeError, RecursionError) as error:
        raise P5RequestError("실행 요청 JSON이 올바르지 않습니다.") from error

    return validate_request(payload, expected_p5_hash)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-json", required=True)
    parser.add_argument("--p5", required=True)
    parser.add_argument("--output-png", required=True)
    args = parser.parse_args()

    if not Path("/.dockerenv").exists():
        raise RuntimeError("이 실행기는 Docker 내부에서 사용해 주세요.")

    target = Path(args.output_png)
    if target.exists() or target.is_symlink():
        raise RuntimeError("기존 결과 파일을 덮어쓰지 않습니다.")

    p5_bytes = read_regular_file(args.p5, 4194304)
    p5_hash = hashlib.sha256(p5_bytes).hexdigest()
    request = load_request(args.input_json, p5_hash)

    # 브라우저와 이미지 라이브러리는 격리 컨테이너 안에서만 불러온다.
    from PIL import Image
    from playwright.sync_api import sync_playwright
    from course_simulation_render_grade import render_code

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage"],
        )
        try:
            # 기존 정답 생성기와 같은 정적 그림 캡처 조건을 사용한다.
            pixels = render_code(
                browser,
                p5_bytes.decode("utf-8"),
                request["source_code"],
                "제출",
                400,
                400,
            )
        finally:
            browser.close()

    # 빈 학생 그림은 오류가 아니라 비교 후 0점 처리 대상이다.
    buffer = BytesIO()
    Image.fromarray(pixels).save(buffer, format="PNG")
    png = buffer.getvalue()

    if not 1 <= len(png) <= 1048576:
        raise RuntimeError("렌더링 PNG 크기가 허용 범위를 초과했습니다.")

    with target.open("xb") as file:
        try:
            file.write(png)
        except BaseException:
            target.unlink(missing_ok=True)
            raise

    print(json.dumps({
        "schema_version": 1,
        "status": "ok",
        "execution_engine": "p5js",
        "execution_version": "2.3.4",
        "p5_file_sha256": p5_hash,
        "source_sha256": hashlib.sha256(
            request["source_code"].encode("utf-8")
        ).hexdigest(),
        "frame": 0,
        "width": 400,
        "height": 400,
        "png_sha256": hashlib.sha256(png).hexdigest(),
        "png_bytes": len(png),
    }, ensure_ascii=False, allow_nan=False))


if __name__ == "__main__":
    try:
        main()
    except P5RequestError:
        print(json.dumps({
            "status": "error",
            "code": "invalid_request",
            "message": "p5.js 실행 요청이 올바르지 않습니다.",
        }, ensure_ascii=False))
        sys.exit(2)
    except Exception:
        print(json.dumps({
            "status": "error",
            "code": "render_error",
            "message": "p5.js 코드 실행 또는 그림 생성에 실패했습니다.",
        }, ensure_ascii=False))
        sys.exit(1)
