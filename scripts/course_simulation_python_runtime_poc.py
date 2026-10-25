
from __future__ import annotations

import math
import struct
import sys
import zlib
from pathlib import Path


WIDTH = 400
HEIGHT = 400

_pixels = bytearray([255, 255, 255, 255] * WIDTH * HEIGHT)
_fill = (0, 0, 0, 255)
_stroke = (0, 0, 0, 255)
_stroke_width = 1


def _clamp_color(value):
    return max(0, min(255, int(value)))


def _color(*args):
    if len(args) == 1:
        value = _clamp_color(args[0])
        return (value, value, value, 255)

    if len(args) == 3:
        return (
            _clamp_color(args[0]),
            _clamp_color(args[1]),
            _clamp_color(args[2]),
            255,
        )

    if len(args) == 4:
        return (
            _clamp_color(args[0]),
            _clamp_color(args[1]),
            _clamp_color(args[2]),
            _clamp_color(args[3]),
        )

    raise ValueError("색상은 1개, 3개, 또는 4개 값으로 입력합니다.")


def _set_pixel(x, y, color):
    x = int(round(x))
    y = int(round(y))

    if x < 0 or y < 0 or x >= WIDTH or y >= HEIGHT:
        return

    offset = (y * WIDTH + x) * 4
    _pixels[offset:offset + 4] = bytes(color)


def _draw_disc(cx, cy, radius, color):
    radius = int(max(0, round(radius)))
    cx = float(cx)
    cy = float(cy)

    min_x = int(math.floor(cx - radius))
    max_x = int(math.ceil(cx + radius))
    min_y = int(math.floor(cy - radius))
    max_y = int(math.ceil(cy + radius))
    r2 = radius * radius

    for y in range(min_y, max_y + 1):
        for x in range(min_x, max_x + 1):
            if (x - cx) * (x - cx) + (y - cy) * (y - cy) <= r2:
                _set_pixel(x, y, color)


def _draw_line_basic(x1, y1, x2, y2, width, color):
    x1 = float(x1)
    y1 = float(y1)
    x2 = float(x2)
    y2 = float(y2)
    steps = int(max(abs(x2 - x1), abs(y2 - y1), 1))
    radius = max(0, int(width) // 2)

    for i in range(steps + 1):
        t = i / steps
        x = x1 + (x2 - x1) * t
        y = y1 + (y2 - y1) * t

        if radius <= 0:
            _set_pixel(x, y, color)
        else:
            _draw_disc(x, y, radius, color)


def _png_chunk(chunk_type, data):
    payload = chunk_type + data
    return (
        struct.pack(">I", len(data)) +
        payload +
        struct.pack(">I", zlib.crc32(payload) & 0xffffffff)
    )


def _png_bytes():
    raw = bytearray()

    for y in range(HEIGHT):
        raw.append(0)
        start = y * WIDTH * 4
        raw.extend(_pixels[start:start + WIDTH * 4])

    return (
        b"\x89PNG\r\n\x1a\n" +
        _png_chunk(
            b"IHDR",
            struct.pack(">IIBBBBB", WIDTH, HEIGHT, 8, 6, 0, 0, 0),
        ) +
        _png_chunk(b"IDAT", zlib.compress(bytes(raw), 9)) +
        _png_chunk(b"IEND", b"")
    )


def canvas(width, height):
    global WIDTH, HEIGHT, _pixels

    width = int(width)
    height = int(height)

    if width != 400 or height != 400:
        raise ValueError("현재 PoC는 400x400 캔버스만 지원합니다.")

    WIDTH = width
    HEIGHT = height
    _pixels = bytearray([255, 255, 255, 255] * WIDTH * HEIGHT)


def background(*args):
    color = _color(*args)

    for i in range(0, len(_pixels), 4):
        _pixels[i:i + 4] = bytes(color)


def fill(*args):
    global _fill
    _fill = _color(*args)


def stroke(*args):
    global _stroke
    _stroke = _color(*args)


def stroke_width(value):
    global _stroke_width
    _stroke_width = max(0, int(value))


def circle(x, y, diameter):
    x = float(x)
    y = float(y)
    radius = float(diameter) / 2.0

    _draw_disc(x, y, radius, _fill)

    if _stroke_width > 0:
        steps = max(48, int(radius * 8))

        for i in range(steps):
            angle1 = 2.0 * math.pi * i / steps
            angle2 = 2.0 * math.pi * (i + 1) / steps
            _draw_line_basic(
                x + math.cos(angle1) * radius,
                y + math.sin(angle1) * radius,
                x + math.cos(angle2) * radius,
                y + math.sin(angle2) * radius,
                _stroke_width,
                _stroke,
            )


def rect(x, y, width, height):
    x = int(round(float(x)))
    y = int(round(float(y)))
    width = int(round(float(width)))
    height = int(round(float(height)))

    for py in range(y, y + height + 1):
        for px in range(x, x + width + 1):
            _set_pixel(px, py, _fill)

    if _stroke_width > 0:
        line(x, y, x + width, y)
        line(x + width, y, x + width, y + height)
        line(x + width, y + height, x, y + height)
        line(x, y + height, x, y)


def line(x1, y1, x2, y2):
    if _stroke_width > 0:
        _draw_line_basic(x1, y1, x2, y2, _stroke_width, _stroke)


def no_stroke():
    global _stroke_width
    _stroke_width = 0


def save(path):
    Path(path).write_bytes(_png_bytes())



_poc_env = None

def _poc_start(source):
    global _poc_env
    allowed = {
        "abs": abs, "float": float, "int": int,
        "len": len, "max": max, "min": min,
        "pow": pow, "range": range, "round": round, "sum": sum,
    }
    _poc_env = {
        "__builtins__": allowed,
        "math": math,
        "canvas": canvas,
        "background": background,
        "fill": fill,
        "stroke": stroke,
        "stroke_width": stroke_width,
        "circle": circle,
        "rect": rect,
        "line": line,
        "no_stroke": no_stroke,
    }
    exec(compile(source, "<고정 실험 예제>", "exec"), _poc_env, _poc_env)
    setup = _poc_env.get("setup")
    if callable(setup):
        setup()

def _poc_step(frame):
    draw = _poc_env.get("draw")
    if callable(draw):
        draw(frame)

def _poc_pixels():
    return bytes(_pixels)
