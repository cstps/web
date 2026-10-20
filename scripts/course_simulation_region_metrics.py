"""
분리된 도형의 영역별 배점을 반영하는 실험용 비교 함수.

영역별 충족률을 배점으로 가중 평균한다.
지정 영역 밖의 제출 전경 비율을 추가 감점에 반영한다.
DB 저장 및 공식 점수 저장은 수행하지 않는다.
"""

from __future__ import annotations

import re
from typing import Any, Dict

import numpy as np

from course_simulation_image_metrics import symmetric_foreground_color_coverage


def _score_units(value: Any) -> int:
    if not isinstance(value, str):
        raise ValueError("배점은 소수 둘째 자리까지의 문자열이어야 합니다.")

    match = re.fullmatch(
        r"(0|[1-9][0-9]{0,4})(?:\.([0-9]{1,2}))?",
        value,
    )
    if match is None:
        raise ValueError("배점 형식이 올바르지 않습니다.")

    units = int(match.group(1)) * 100 + int(
        (match.group(2) or "").ljust(2, "0")
    )
    if units <= 0:
        raise ValueError("영역 배점과 전체 배점은 0보다 커야 합니다.")
    return units


def _score_text(units: int) -> str:
    return f"{units // 100}.{units % 100:02d}"


def weighted_region_color_coverage(
    reference: np.ndarray,
    student: np.ndarray,
    regions: Any,
    max_score: str,
    position_tolerance_px: int = 0,
    color_tolerance: int = 0,
) -> Dict[str, Any]:
    # 기존 함수에서 이미지 형식·크기·허용 반경·빈 정답을 검사한다.
    whole = symmetric_foreground_color_coverage(
        reference,
        student,
        position_tolerance_px=position_tolerance_px,
        color_tolerance=color_tolerance,
    )

    if not isinstance(regions, list) or not 1 <= len(regions) <= 20:
        raise ValueError("채점 영역은 1~20개여야 합니다.")

    total_units = _score_units(max_score)
    height, width = reference.shape[:2]
    reference_mask = np.any(reference != 255, axis=2)
    student_mask = np.any(student != 255, axis=2)
    occupied = np.zeros((height, width), dtype=bool)
    checked = []
    identifiers = set()
    region_units_sum = 0

    required = {"id", "x", "y", "width", "height", "max_score"}
    allowed = required | {"label"}

    for entry in regions:
        if (
            not isinstance(entry, dict)
            or not required.issubset(entry)
            or set(entry) - allowed
        ):
            raise ValueError("영역 설정 필드가 올바르지 않습니다.")

        identifier = entry["id"]
        if (
            not isinstance(identifier, str)
            or re.fullmatch(r"[A-Za-z0-9_-]{1,32}", identifier) is None
            or identifier in identifiers
        ):
            raise ValueError("영역 식별자는 중복 없는 영문·숫자 조합이어야 합니다.")
        identifiers.add(identifier)

        label = entry.get("label", identifier)
        if not isinstance(label, str) or not 1 <= len(label.strip()) <= 80:
            raise ValueError("영역 이름은 1~80자여야 합니다.")
        label = label.strip()

        x, y, w, h = (
            entry["x"], entry["y"], entry["width"], entry["height"]
        )
        if (
            any(type(value) is not int for value in (x, y, w, h))
            or x < 0 or y < 0 or w <= 0 or h <= 0
            or x + w > width or y + h > height
        ):
            raise ValueError(label + ": 영역 좌표가 캔버스 범위를 벗어났습니다.")

        section = (slice(y, y + h), slice(x, x + w))
        if np.any(occupied[section]):
            raise ValueError(label + ": 채점 영역은 서로 겹칠 수 없습니다.")

        foreground = reference_mask[section]
        if not np.any(foreground):
            raise ValueError(label + ": 영역 안에 정답 도형이 없습니다.")

        # 캔버스 내부의 영역 경계에는 허용 반경만큼 여백이 필요하다.
        radius = position_tolerance_px
        if radius > 0:
            near_boundary = (
                (x > 0 and np.any(foreground[:, :radius]))
                or (x + w < width and np.any(foreground[:, -radius:]))
                or (y > 0 and np.any(foreground[:radius, :]))
                or (y + h < height and np.any(foreground[-radius:, :]))
            )
            if near_boundary:
                raise ValueError(
                    label + ": 정답 도형과 영역 경계 사이에 "
                    "위치 허용 반경만큼의 여백이 필요합니다."
                )

        units = _score_units(entry["max_score"])
        region_units_sum += units
        occupied[section] = True
        checked.append(
            (identifier, label, x, y, w, h, units, section)
        )

    if region_units_sum != total_units:
        raise ValueError("영역별 배점 합계가 전체 배점과 다릅니다.")

    if np.any(reference_mask & ~occupied):
        raise ValueError("채점 영역에 포함되지 않은 정답 도형이 있습니다.")

    results = []
    weighted_coverage = 0.0

    for identifier, label, x, y, w, h, units, section in checked:
        metrics = symmetric_foreground_color_coverage(
            reference[section],
            student[section],
            position_tolerance_px=position_tolerance_px,
            color_tolerance=color_tolerance,
        )
        coverage = float(metrics["symmetric_coverage"])
        contribution = coverage * units / total_units
        weighted_coverage += contribution

        results.append({
            "id": identifier,
            "label": label,
            "x": x,
            "y": y,
            "width": w,
            "height": h,
            "max_score": _score_text(units),
            "coverage": coverage,
            "weighted_contribution": contribution,
            "metrics": metrics,
        })

    student_count = int(student_mask.sum())
    outside_count = int((student_mask & ~occupied).sum())

    # 영역 안의 추가 도형은 각 영역의 양방향 비교에서 감점된다.
    # 영역 밖의 추가 도형은 별도의 비율로 감점한다.
    outside_factor = (
        (student_count - outside_count) / student_count
        if student_count > 0
        else 1.0
    )

    final_coverage = min(
        100.0,
        max(0.0, weighted_coverage * outside_factor),
    )

    return {
        "metric": "weighted_region_color_coverage_v1",
        "aggregation": "weighted_mean_with_outside_penalty",
        "max_score": _score_text(total_units),
        "position_tolerance_px": position_tolerance_px,
        "color_tolerance": 0,
        "regions": results,
        "weighted_coverage": weighted_coverage,
        "student_foreground": student_count,
        "outside_student_foreground": outside_count,
        "outside_penalty_factor": outside_factor,
        "final_coverage": final_coverage,
        "whole_image_coverage": float(whole["symmetric_coverage"]),
    }
