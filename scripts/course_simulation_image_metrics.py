"""
정적 그림의 위치 허용 오차를 반영하는 비교 함수.

흰색 배경을 제외한 전경 픽셀을 양방향으로 비교한다.
허용 반경은 유클리드 거리 기준이며, 색상은 정확히 같아야 한다.
DB 저장과 공식 점수 계산은 수행하지 않는다.
"""

from __future__ import annotations

from typing import Any, Dict

import numpy as np


def symmetric_foreground_color_coverage(
    reference: np.ndarray,
    student: np.ndarray,
    position_tolerance_px: int = 0,
    color_tolerance: int = 0,
) -> Dict[str, Any]:
    for label, pixels in (("정답", reference), ("제출", student)):
        if (
            not isinstance(pixels, np.ndarray)
            or pixels.dtype != np.uint8
            or pixels.ndim != 3
            or pixels.shape[2] != 3
            or pixels.shape[0] == 0
            or pixels.shape[1] == 0
        ):
            raise ValueError(label + " 이미지는 비어 있지 않은 RGB 배열이어야 합니다.")

    if reference.shape != student.shape:
        raise ValueError("정답과 제출 이미지 크기가 다릅니다.")

    if (
        type(position_tolerance_px) is not int
        or not 0 <= position_tolerance_px <= 10
    ):
        raise ValueError("위치 허용 오차는 0~10 사이의 정수여야 합니다.")

    if type(color_tolerance) is not int or color_tolerance != 0:
        raise ValueError("현재 색상 오차는 0만 지원합니다.")

    reference_mask = np.any(reference != 255, axis=2)
    student_mask = np.any(student != 255, axis=2)

    reference_count = int(reference_mask.sum())
    student_count = int(student_mask.sum())

    if reference_count == 0:
        raise ValueError("정답 이미지에 전경 도형이 없습니다.")

    reference_covered = np.zeros(reference_mask.shape, dtype=bool)
    student_covered = np.zeros(student_mask.shape, dtype=bool)

    height, width = reference_mask.shape
    radius = position_tolerance_px

    if student_count > 0:
        for dy in range(-radius, radius + 1):
            for dx in range(-radius, radius + 1):
                if dx * dx + dy * dy > radius * radius:
                    continue

                # 가장자리 바깥의 픽셀은 반대편으로 이동시키지 않는다.
                reference_y0 = max(0, -dy)
                reference_y1 = min(height, height - dy)
                reference_x0 = max(0, -dx)
                reference_x1 = min(width, width - dx)

                if (
                    reference_y0 >= reference_y1
                    or reference_x0 >= reference_x1
                ):
                    continue

                reference_slice = (
                    slice(reference_y0, reference_y1),
                    slice(reference_x0, reference_x1),
                )
                student_slice = (
                    slice(reference_y0 + dy, reference_y1 + dy),
                    slice(reference_x0 + dx, reference_x1 + dx),
                )

                same_color = np.all(
                    reference[reference_slice] == student[student_slice],
                    axis=2,
                )

                matched = (
                    same_color
                    & reference_mask[reference_slice]
                    & student_mask[student_slice]
                )

                reference_covered[reference_slice] |= matched
                student_covered[student_slice] |= matched

    reference_matched = int(reference_covered.sum())
    student_matched = int(student_covered.sum())

    reference_ratio = reference_matched / reference_count
    student_ratio = (
        student_matched / student_count
        if student_count > 0
        else 0.0
    )

    if reference_ratio + student_ratio == 0:
        symmetric_ratio = 0.0
    else:
        symmetric_ratio = (
            2.0 * reference_ratio * student_ratio
            / (reference_ratio + student_ratio)
        )

    return {
        "metric": "symmetric_foreground_color_coverage",
        "aggregation": "harmonic_mean",
        "position_tolerance_px": radius,
        "color_tolerance": 0,
        "neighborhood": "euclidean_disk",
        "reference_foreground": reference_count,
        "student_foreground": student_count,
        "reference_matched": reference_matched,
        "student_matched": student_matched,
        "reference_coverage": 100.0 * reference_ratio,
        "student_coverage": 100.0 * student_ratio,
        "symmetric_coverage": 100.0 * symmetric_ratio,
    }
