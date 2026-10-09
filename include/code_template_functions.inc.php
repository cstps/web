<?php

// ============================================================
// 소스 코드 줄바꿈을 LF로 통일
// ============================================================

function oj_normalize_source_newlines($source) {

    return str_replace(
        array("\r\n", "\r"),
        "\n",
        (string)$source
    );
}


// ============================================================
// 현재 OJ에서 활성화된 언어 목록 반환
//
// HUSTOJ의 OJ_LANGMASK 규칙:
// - bit가 0인 언어가 활성화된 언어이다.
// - 실제 제출 화면과 동일하게 language_ext 개수를 기준으로 한다.
//
// 반환 예:
//
// array(
//     0 => 'C',
//     1 => 'C++',
//     3 => 'Java',
//     6 => 'Python'
// )
// ============================================================

function oj_get_enabled_template_languages(
    $language_names,
    $language_extensions,
    $language_mask
) {
    if (
        !is_array($language_names) ||
        !is_array($language_extensions)
    ) {
        return array();
    }

    $language_count =
        min(
            count($language_names),
            count($language_extensions)
        );

    $language_mask =
        intval($language_mask);

    $enabled_languages =
        array();

    for (
        $language_id = 0;
        $language_id < $language_count;
        $language_id++
    ) {
        // HUSTOJ에서는 mask bit가 1이면 비활성화 언어이다.
        if (
            $language_mask &
            (1 << $language_id)
        ) {
            continue;
        }

        $enabled_languages[$language_id] =
            (string)$language_names[$language_id];
    }

    return $enabled_languages;
}


// ============================================================
// 문제별 언어 템플릿 전체 조회
//
// 반환 형식:
//
// array(
//     language_id => array(
//         'lang'  => 'Python',
//         'front' => '...',
//         'rear'  => '...'
//     )
// )
// ============================================================

function oj_get_problem_templates(
    $problem_id
) {
    $problem_id =
        intval($problem_id);

    if ($problem_id <= 0) {
        return array();
    }

    $rows =
        pdo_query(
            "SELECT
                language_id,
                lang,
                kind,
                content
             FROM problem_template
             WHERE problem_id = ?
             ORDER BY language_id, kind",
            $problem_id
        );

    if ($rows === false) {
        return false;
    }

    $templates =
        array();

    foreach ($rows as $row) {
        $language_id =
            intval($row['language_id']);

        if (!isset($templates[$language_id])) {
            $templates[$language_id] =
                array(
                    'lang' =>
                        (string)$row['lang'],

                    'front' =>
                        '',

                    'rear' =>
                        ''
                );
        }

        $kind =
            (string)$row['kind'];

        if (
            $kind !== 'front' &&
            $kind !== 'rear'
        ) {
            continue;
        }

        $templates[$language_id][$kind] =
            (string)$row['content'];
    }

    return $templates;
}


// ============================================================
// 언어별 front/rear 코드 추출
//
// 기존 저장 형식:
// //C//
// C 코드
// //C++//
// C++ 코드
//
// 일반적인 // 주석이 아니라 실제 언어 표식만 다음 구간의
// 시작으로 인식한다.
// ============================================================

function oj_extract_language_template(
    $template_source,
    $language_index,
    $language_names
) {

    if (
        !is_array($language_names) ||
        !isset($language_names[$language_index])
    ) {
        return null;
    }


    $template_source =
        oj_normalize_source_newlines(
            $template_source
        );


    $language_name =
        (string)$language_names[$language_index];

    $target_marker =
        "//".$language_name."//";


    $marker_position =
        strpos(
            $template_source,
            $target_marker
        );


    if ($marker_position === false) {
        return null;
    }


    $content_start =
        $marker_position +
        strlen($target_marker);


    // --------------------------------------------------------
    // 현재 언어 표식 다음에 나타나는 다른 실제 언어 표식을 찾는다.
    // 코드 내부의 일반 // 주석은 종료 위치로 사용하지 않는다.
    // --------------------------------------------------------

    $content_end = false;


    foreach ($language_names as $name) {

        $next_marker =
            "//".(string)$name."//";

        $next_position =
            strpos(
                $template_source,
                $next_marker,
                $content_start
            );


        if (
            $next_position !== false &&
            (
                $content_end === false ||
                $next_position < $content_end
            )
        ) {

            $content_end =
                $next_position;
        }
    }


    if ($content_end === false) {

        $language_code =
            substr(
                $template_source,
                $content_start
            );

    }
    else {

        $language_code =
            substr(
                $template_source,
                $content_start,
                $content_end - $content_start
            );
    }


    // 언어 표식이 한 줄을 차지하는 경우
    // 표식 바로 다음의 구분용 줄바꿈 한 개만 제거한다.
    if (
        $language_code !== '' &&
        substr($language_code, 0, 1) === "\n"
    ) {

        $language_code =
            substr(
                $language_code,
                1
            );
    }


    return $language_code;
}


// ============================================================
// 문제의 언어별 front/rear 템플릿 조회
//
// 우선순위:
// 1. problem_template의 problem_id + language_id
// 2. 기존 problem.front_code/rear_code의 //언어명// 형식
//
// 기존 문제와의 호환성을 유지하면서 신규 problem_template
// 구조를 실제 실행 경로에서 사용할 수 있도록 한다.
// ============================================================

function oj_get_problem_language_templates(
    $problem_id,
    $language_id,
    $language_names,
    $legacy_front_code,
    $legacy_rear_code
) {

    $problem_id =
        intval($problem_id);

    $language_id =
        intval($language_id);


    $result = array(
        'front' => '',
        'rear' => '',
        'source' => 'legacy'
    );


    // problem_template을 사용할 수 있으면
    // language_id 기준 데이터를 가장 먼저 사용한다.
    if (
        $problem_id > 0 &&
        function_exists('pdo_query')
    ) {

        $rows =
            pdo_query(
                "SELECT
                    kind,
                    content
                 FROM problem_template
                 WHERE problem_id = ?
                   AND language_id = ?
                 ORDER BY kind",
                $problem_id,
                $language_id
            );


        if (
            is_array($rows) &&
            count($rows) > 0
        ) {

            foreach ($rows as $row) {

                $kind =
                    isset($row['kind'])
                        ? (string)$row['kind']
                        : '';

                $content =
                    isset($row['content'])
                        ? oj_normalize_source_newlines(
                            $row['content']
                        )
                        : '';


                if ($kind === 'front') {

                    $result['front'] =
                        $content;
                }
                elseif ($kind === 'rear') {

                    $result['rear'] =
                        $content;
                }
            }


            $result['source'] =
                'problem_template';

            return $result;
        }
    }


    // --------------------------------------------------------
    // problem_template에 해당 언어 데이터가 없으면
    // 기존 //C//, //C++//, //Python// 형식을 사용한다.
    // --------------------------------------------------------

    $legacy_front =
        oj_extract_language_template(
            $legacy_front_code,
            $language_id,
            $language_names
        );

    $legacy_rear =
        oj_extract_language_template(
            $legacy_rear_code,
            $language_id,
            $language_names
        );


    $result['front'] =
        $legacy_front === null
            ? ''
            : $legacy_front;

    $result['rear'] =
        $legacy_rear === null
            ? ''
            : $legacy_rear;


    return $result;
}


// ============================================================
// 기존 front_code/rear_code 문자열을 언어별 템플릿으로 분석
// ============================================================

function oj_parse_legacy_problem_templates(
    $front_code,
    $rear_code,
    $language_names
) {
    if (!is_array($language_names)) {
        return false;
    }

    $sources = array(
        'front' =>
        oj_normalize_source_newlines($front_code),

        'rear' =>
        oj_normalize_source_newlines($rear_code)
    );

    $templates = array(
        'front' => array(),
        'rear' => array()
    );

    foreach ($sources as $kind => $template_source) {
        // 공백과 줄바꿈만 있으면 템플릿 없음으로 처리
        if (trim($template_source) === '') {
            continue;
        }

        $found_marker = false;

        foreach (
            $language_names
            as $language_id => $language_label
        ) {
            $content =
                oj_extract_language_template(
                    $template_source,
                    (int)$language_id,
                    $language_names
                );

            if ($content === null) {
                continue;
            }

            $found_marker = true;

            // 빈 언어 블록은 별도 행으로 저장하지 않는다.
            if ($content === '') {
                continue;
            }

            $templates[$kind][(int)$language_id] =
                array(
                    'language_id' =>
                    (int)$language_id,

                    'lang' =>
                    (string)$language_label,

                    'kind' =>
                    $kind,

                    'content' =>
                    $content
                );
        }

        // 내용은 있는데 인식 가능한 언어 표식이 없는 경우
        if (!$found_marker) {
            error_log(
                '[problem_template] ' .
                    $kind .
                    ' 코드에서 언어 표식을 찾을 수 없습니다.'
            );

            return false;
        }
    }

    return $templates;
}


// ============================================================
// 기존 problem.front_code/rear_code를 problem_template과 동기화
//
// - INSERT/UPDATE/DELETE를 구분해 불필요한 ID 재생성을 방지한다.
// - problem_template은 InnoDB이므로 자체 트랜잭션을 사용한다.
// - 오류가 발생하면 기존 problem_template 상태로 되돌린다.
// ============================================================

function oj_sync_problem_templates_from_legacy(
    $problem_id,
    $front_code,
    $rear_code,
    $language_names
) {
    $templates =
        oj_parse_legacy_problem_templates(
            $front_code,
            $rear_code,
            $language_names
        );

    if ($templates === false) {
        return false;
    }

    return oj_save_problem_templates(
        $problem_id,
        $templates
    );
}


// ============================================================
// 언어별 problem_template 직접 저장
//
// $templates 형식:
//
// array(
//     'front' => array(
//         language_id => array(
//             'language_id' => ...,
//             'lang'        => ...,
//             'kind'        => 'front',
//             'content'     => ...
//         )
//     ),
//     'rear' => array(...)
// )
//
// - 신규/수정 UI에서도 직접 호출할 수 있다.
// - INSERT/UPDATE/DELETE를 구분해 기존 ID를 최대한 유지한다.
// - 자체 트랜잭션을 사용한다.
// ============================================================

function oj_save_problem_templates(
    $problem_id,
    $templates
) {
    $problem_id =
        intval($problem_id);

    if ($problem_id <= 0) {
        return false;
    }

    if (
        !is_array($templates) ||
        !isset($templates['front']) ||
        !isset($templates['rear']) ||
        !is_array($templates['front']) ||
        !is_array($templates['rear'])
    ) {
        return false;
    }

    $existing_rows =
        pdo_query(
            "SELECT
                id,
                language_id,
                lang,
                kind,
                content
             FROM problem_template
             WHERE problem_id = ?",
            $problem_id
        );

    if ($existing_rows === false) {
        return false;
    }

    $existing_by_key =
        array();

    foreach ($existing_rows as $row) {
        $key =
            intval($row['language_id']) .
            ':' .
            (string)$row['kind'];

        $existing_by_key[$key] =
            $row;
    }

    global $dbh;

    if (!($dbh instanceof PDO)) {
        error_log(
            '[problem_template] PDO 연결을 확인할 수 없습니다.'
        );

        return false;
    }

    $transaction_started = false;

    try {
        if (!$dbh->inTransaction()) {
            $dbh->beginTransaction();
            $transaction_started = true;
        }

        $active_keys =
            array();

        foreach ($templates as $kind => $kind_templates) {

            if (
                $kind !== 'front' &&
                $kind !== 'rear'
            ) {
                continue;
            }

            foreach (
                $kind_templates
                as $language_id => $template
            ) {
                $language_id =
                    intval($language_id);

                if (
                    !is_array($template) ||
                    $language_id < 0
                ) {
                    throw new RuntimeException(
                        '잘못된 템플릿 데이터'
                    );
                }

                $lang =
                    isset($template['lang'])
                        ? (string)$template['lang']
                        : '';

                $content =
                    isset($template['content'])
                        ? oj_normalize_source_newlines(
                            $template['content']
                        )
                        : '';

                // 빈 템플릿은 행을 만들지 않는다.
                if ($content === '') {
                    continue;
                }

                $key =
                    $language_id .
                    ':' .
                    $kind;

                $active_keys[$key] = true;

                if (isset($existing_by_key[$key])) {
                    $existing =
                        $existing_by_key[$key];

                    if (
                        (string)$existing['lang'] ===
                            $lang &&
                        (string)$existing['content'] ===
                            $content
                    ) {
                        continue;
                    }

                    $update_result =
                        pdo_query(
                            "UPDATE problem_template
                             SET
                                lang = ?,
                                content = ?
                             WHERE id = ?",
                            $lang,
                            $content,
                            intval($existing['id'])
                        );

                    if ($update_result === false) {
                        throw new RuntimeException(
                            '템플릿 UPDATE 실패'
                        );
                    }

                    continue;
                }

                $insert_result =
                    pdo_query(
                        "INSERT INTO problem_template
                        (
                            problem_id,
                            language_id,
                            lang,
                            kind,
                            content
                        )
                        VALUES
                        (
                            ?, ?, ?, ?, ?
                        )",
                        $problem_id,
                        $language_id,
                        $lang,
                        $kind,
                        $content
                    );

                if ($insert_result === false) {
                    throw new RuntimeException(
                        '템플릿 INSERT 실패'
                    );
                }
            }
        }

        // 기존에는 있었지만 이번 저장에서 제거된 템플릿 삭제
        foreach ($existing_by_key as $key => $existing) {
            if (isset($active_keys[$key])) {
                continue;
            }

            $delete_result =
                pdo_query(
                    "DELETE FROM problem_template
                     WHERE id = ?",
                    intval($existing['id'])
                );

            if ($delete_result === false) {
                throw new RuntimeException(
                    '템플릿 DELETE 실패'
                );
            }
        }

        if ($transaction_started) {
            if (!$dbh->commit()) {
                throw new RuntimeException(
                    '템플릿 COMMIT 실패'
                );
            }
        }

        return true;

    } catch (Throwable $exception) {

        if (
            $transaction_started &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        error_log(
            '[problem_template] problem_id=' .
                $problem_id .
                ' 저장 실패: ' .
                $exception->getMessage()
        );

        return false;
    }
}


// ============================================================
// 언어별 템플릿 배열을 기존 HUSTOJ 형식으로 변환
//
// problem_template을 원본으로 사용하면서
// problem.front_code / rear_code 호환본을 유지하기 위한 함수.
// ============================================================

function oj_build_legacy_problem_templates(
    $templates,
    $language_names
) {
    $result =
        array(
            'front' => '',
            'rear' => ''
        );

    if (
        !is_array($templates) ||
        !is_array($language_names)
    ) {
        return $result;
    }

    foreach (
        array('front', 'rear')
        as $kind
    ) {
        if (
            !isset($templates[$kind]) ||
            !is_array($templates[$kind])
        ) {
            continue;
        }

        $parts =
            array();

        foreach (
            $language_names
            as $language_id => $language_label
        ) {
            $language_id =
                intval($language_id);

            if (
                !isset(
                    $templates[$kind][$language_id]
                )
            ) {
                continue;
            }

            $template =
                $templates[$kind][$language_id];

            $content =
                isset($template['content'])
                    ? oj_normalize_source_newlines(
                        $template['content']
                    )
                    : '';

            if ($content === '') {
                continue;
            }

            $parts[] =
                '//' .
                (string)$language_label .
                "//\n" .
                $content;
        }

        $result[$kind] =
            implode(
                "\n",
                $parts
            );
    }

    return $result;
}


// ============================================================
// front + 학생 코드 + rear 결합
//
// trim()을 사용하지 않아 코드의 공백과 빈 줄을 보존한다.
// 코드 사이에 줄바꿈이 전혀 없을 때만 한 줄을 추가한다.
// ============================================================

// ------------------------------------------------------------
// problem_template을 우선 사용하여
// 기존 //언어명// 형식의 Front/Rear 호환본을 반환한다.
//
// 오래된 문제처럼 problem_template 행이 없는 경우에만
// problem.front_code / rear_code를 fallback으로 사용한다.
// ------------------------------------------------------------

function oj_get_problem_legacy_template_codes(
    $problem_id,
    $language_names
) {
    $problem_id =
        intval($problem_id);

    $result =
        array(
            'front' => '',
            'rear' => ''
        );

    if ($problem_id <= 0) {
        return $result;
    }

    $problem_templates =
        oj_get_problem_templates(
            $problem_id
        );

    if (
        is_array($problem_templates) &&
        !empty($problem_templates)
    ) {
        $templates =
            array(
                'front' => array(),
                'rear' => array()
            );

        foreach (
            $problem_templates
            as $language_id => $template
        ) {
            foreach (
                array('front', 'rear')
                as $kind
            ) {
                $content =
                    isset($template[$kind])
                        ? (string)$template[$kind]
                        : '';

                if ($content === '') {
                    continue;
                }

                $templates[$kind][$language_id] =
                    array(
                        'language_id' =>
                            intval($language_id),

                        'lang' =>
                            isset($template['lang'])
                                ? (string)$template['lang']
                                : '',

                        'kind' =>
                            $kind,

                        'content' =>
                            $content
                    );
            }
        }

        return
            oj_build_legacy_problem_templates(
                $templates,
                $language_names
            );
    }

    $rows =
        pdo_query(
            "SELECT
                front_code,
                rear_code
             FROM problem
             WHERE problem_id = ?
             LIMIT 1",
            $problem_id
        );

    if (
        $rows &&
        isset($rows[0])
    ) {
        $result['front'] =
            isset($rows[0]['front_code'])
                ? (string)$rows[0]['front_code']
                : '';

        $result['rear'] =
            isset($rows[0]['rear_code'])
                ? (string)$rows[0]['rear_code']
                : '';
    }

    return $result;
}


function oj_build_judge_source(
    $front_code,
    $user_source,
    $rear_code
) {

    $front_code =
        $front_code === null
            ? ''
            : oj_normalize_source_newlines($front_code);

    $user_source =
        oj_normalize_source_newlines(
            $user_source
        );

    $rear_code =
        $rear_code === null
            ? ''
            : oj_normalize_source_newlines($rear_code);


    $judge_source =
        $user_source;


    if ($front_code !== '') {

        if (
            substr($front_code, -1) !== "\n" &&
            $judge_source !== '' &&
            substr($judge_source, 0, 1) !== "\n"
        ) {

            $front_code .= "\n";
        }


        $judge_source =
            $front_code.
            $judge_source;
    }


    if ($rear_code !== '') {

        if (
            $judge_source !== '' &&
            substr($judge_source, -1) !== "\n" &&
            substr($rear_code, 0, 1) !== "\n"
        ) {

            $judge_source .= "\n";
        }


        $judge_source .=
            $rear_code;
    }


    return $judge_source;
}

// ============================================================
// 기존 제출에서 실제로 사용했던 언어별 코드 추출
//
// 과거 submit.php의 다음 동작을 그대로 재현한다.
// explode("//", ...)[0]
// ============================================================

// ============================================================
// Python 단계적 실행용 trace wrapper 생성
// ============================================================

function oj_build_python_trace_source(
    $front_code,
    $user_source,
    $rear_code,
    $max_steps = 500
) {

    $max_steps = intval($max_steps);

    if ($max_steps < 1) {
        $max_steps = 500;
    }


    $front_code =
        oj_normalize_source_newlines(
            $front_code
        );

    $user_source =
        oj_normalize_source_newlines(
            $user_source
        );

    $rear_code =
        oj_normalize_source_newlines(
            $rear_code
        );


    // oj_build_judge_source()가 Front와 학생 코드 사이에
    // 추가할 수 있는 줄바꿈까지 포함하여 offset을 계산한다.
    $trace_front =
        $front_code;

    if (
        $trace_front !== '' &&
        substr($trace_front, -1) !== "\n" &&
        $user_source !== '' &&
        substr($user_source, 0, 1) !== "\n"
    ) {
        $trace_front .= "\n";
    }


    $student_line_offset =
        substr_count(
            $trace_front,
            "\n"
        );


    if ($user_source === '') {

        $student_line_count = 0;
    }
    else {

        $student_line_count =
            substr_count(
                $user_source,
                "\n"
            );

        if (
            substr($user_source, -1) !== "\n"
        ) {
            $student_line_count++;
        }
    }


    $judge_source =
        oj_build_judge_source(
            $front_code,
            $user_source,
            $rear_code
        );


    $encoded_source =
        base64_encode(
            $judge_source
        );

    $encoded_literal =
        var_export(
            $encoded_source,
            true
        );

    return
'#!/usr/bin/env python3
# coding=utf-8

import base64
import io
import json
import os
import sys

_OJ_MAX_STEPS = ' . $max_steps . '
_OJ_STUDENT_LINE_OFFSET = ' . $student_line_offset . '
_OJ_STUDENT_LINE_COUNT = ' . $student_line_count . '

_OJ_STEPS = []
_OJ_TRACE_TRUNCATED = False
_OJ_TRACE_ERROR = None

# judge_client 저장 한도(512 KiB)보다 여유 있게 제한한다.
_OJ_MAX_TRACE_BYTES = 400 * 1024
_OJ_TRACE_BYTES = 0

# frame별로 아직 실행 결과를 반영하지 않은
# 학생 코드 단계의 index를 보관한다.
_OJ_ACTIVE_STEPS = {}

_OJ_REAL_STDOUT = sys.stdout
_OJ_STDOUT_BUFFER = io.StringIO()


class _OjTraceStdout:
    def write(self, value):
        _OJ_STDOUT_BUFFER.write(value)
        return _OJ_REAL_STDOUT.write(value)

    def flush(self):
        _OJ_STDOUT_BUFFER.flush()
        return _OJ_REAL_STDOUT.flush()

    def isatty(self):
        return False


def _oj_safe_repr(value):
    try:
        text = repr(value)
    except Exception:
        text = "<표시할 수 없는 값>"

    if len(text) > 500:
        text = text[:500] + "...(생략)"

    return text


def _oj_capture_state(frame):
    variables = {}

    for name, value in frame.f_locals.items():
        if name.startswith("__"):
            continue

        if len(variables) >= 30:
            break

        variables[str(name)] = _oj_safe_repr(value)

    return {
        "variables": variables,
        "stdout": _OJ_STDOUT_BUFFER.getvalue()[-4096:]
    }


def _oj_student_line(actual_line):
    if _OJ_STUDENT_LINE_COUNT <= 0:
        return None

    student_line = (
        int(actual_line) -
        _OJ_STUDENT_LINE_OFFSET
    )

    if (
        student_line < 1 or
        student_line > _OJ_STUDENT_LINE_COUNT
    ):
        return None

    return student_line


def _oj_finish_active_step(frame):
    step_index = _OJ_ACTIVE_STEPS.pop(
        frame,
        None
    )

    if step_index is None:
        return

    if (
        step_index < 0 or
        step_index >= len(_OJ_STEPS)
    ):
        return

    state = _oj_capture_state(frame)

    _OJ_STEPS[step_index]["variables"] = (
        state["variables"]
    )

    _OJ_STEPS[step_index]["stdout"] = (
        state["stdout"]
    )


def _oj_trace(frame, event, arg):
    global _OJ_TRACE_TRUNCATED
    global _OJ_TRACE_BYTES

    if frame.f_code.co_filename != "<student>":
        return _oj_trace

    if event == "line":

        # 같은 frame의 직전 학생 코드 줄이 있었다면
        # 현재 줄에 도착한 시점에서 실행 결과를 확정한다.
        _oj_finish_active_step(frame)

        student_line = _oj_student_line(
            frame.f_lineno
        )

        # Front/Rear 줄은 실제로 실행하되
        # 학생 단계 목록에는 기록하지 않는다.
        if student_line is None:
            return _oj_trace

        if len(_OJ_STEPS) >= _OJ_MAX_STEPS:
            _OJ_TRACE_TRUNCATED = True
            return _oj_trace

        new_step = {
            "line": int(student_line),
            "variables": {},
            "stdout": ""
        }

        estimated_bytes = len(
            json.dumps(
                new_step,
                ensure_ascii=False
            ).encode("utf-8")
        )

        if (
            _OJ_TRACE_BYTES +
            estimated_bytes >
            _OJ_MAX_TRACE_BYTES
        ):
            _OJ_TRACE_TRUNCATED = True
            return _oj_trace

        _OJ_STEPS.append(new_step)
        _OJ_TRACE_BYTES += estimated_bytes

        _OJ_ACTIVE_STEPS[frame] = (
            len(_OJ_STEPS) - 1
        )

        return _oj_trace

    if event == "return":

        # 해당 frame에서 실행한 마지막 학생 코드 줄은
        # 다음 line 이벤트가 없을 수 있으므로 여기서 확정한다.
        _oj_finish_active_step(frame)

        return _oj_trace

    return _oj_trace


def _oj_save_trace():
    trace_path = os.path.join(
        os.path.dirname(os.path.abspath(__file__)),
        "trace.json"
    )

    data = {
        "version": 1,
        "steps": _OJ_STEPS,
        "step_count": len(_OJ_STEPS),
        "truncated": bool(_OJ_TRACE_TRUNCATED),
        "error": _OJ_TRACE_ERROR
    }

    try:
        with open(
            trace_path,
            "w",
            encoding="utf-8"
        ) as fp:
            json.dump(
                data,
                fp,
                ensure_ascii=False,
                separators=(",", ":")
            )
    except Exception:
        pass


_OJ_SOURCE = base64.b64decode(
    ' . $encoded_literal . '
).decode("utf-8")

_OJ_NAMESPACE = {
    "__name__": "__main__",
    "__builtins__": __builtins__
}

sys.stdout = _OjTraceStdout()
sys.settrace(_oj_trace)

try:
    exec(
        compile(
            _OJ_SOURCE,
            "<student>",
            "exec"
        ),
        _OJ_NAMESPACE,
        _OJ_NAMESPACE
    )

except Exception as _oj_error:
    _OJ_TRACE_ERROR = {
        "line": None,
        "type": type(_oj_error).__name__,
        "message": str(_oj_error)
    }

    _oj_tb = _oj_error.__traceback__

    while _oj_tb is not None:
        if (
            _oj_tb.tb_frame.f_code.co_filename
            == "<student>"
        ):
            _oj_error_line = _oj_student_line(
                _oj_tb.tb_lineno
            )

            if _oj_error_line is not None:
                _OJ_TRACE_ERROR["line"] = int(
                    _oj_error_line
                )

        _oj_tb = _oj_tb.tb_next

    raise

finally:
    sys.settrace(None)
    sys.stdout = _OJ_REAL_STDOUT
    _oj_save_trace()
';
}


function oj_extract_legacy_language_template(
    $template_source,
    $language_index,
    $language_names
) {

    if (
        !is_array($language_names) ||
        !isset($language_names[$language_index])
    ) {
        return null;
    }


    $template_source =
        oj_normalize_source_newlines(
            $template_source
        );


    $marker =
        "//".
        (string)$language_names[$language_index].
        "//";


    $marker_position =
        strpos(
            $template_source,
            $marker
        );


    if ($marker_position === false) {
        return null;
    }


    $after_marker =
        substr(
            $template_source,
            $marker_position + strlen($marker)
        );


    $parts =
        explode(
            "//",
            $after_marker,
            2
        );


    return trim($parts[0]);
}


// ============================================================
// 기존 source_version=0 제출에서 front/rear 제거
//
// 기존 submit.php가 추가했던 구분용 줄바꿈 한 개만 제거한다.
// 학생이 작성한 나머지 공백과 빈 줄은 유지한다.
// ============================================================

function oj_strip_legacy_source_templates(
    $combined_source,
    $front_template_source,
    $rear_template_source,
    $language_index,
    $language_names
) {

    $combined_source =
        oj_normalize_source_newlines(
            $combined_source
        );

    $front_template_source =
        oj_normalize_source_newlines(
            $front_template_source
        );

    $rear_template_source =
        oj_normalize_source_newlines(
            $rear_template_source
        );


    $had_template = (
        $front_template_source !== '' ||
        $rear_template_source !== ''
    );


    $legacy_front =
        oj_extract_legacy_language_template(
            $front_template_source,
            $language_index,
            $language_names
        );

    $legacy_rear =
        oj_extract_legacy_language_template(
            $rear_template_source,
            $language_index,
            $language_names
        );


    // --------------------------------------------------------
    // front 제거
    // --------------------------------------------------------

    if (
        $legacy_front !== null &&
        $legacy_front !== '' &&
        strpos($combined_source, $legacy_front) === 0
    ) {

        $combined_source =
            substr(
                $combined_source,
                strlen($legacy_front)
            );


        // 기존 결합 과정이 추가했던 줄바꿈 한 개만 제거
        if (
            $combined_source !== '' &&
            substr($combined_source, 0, 1) === "\n"
        ) {

            $combined_source =
                substr(
                    $combined_source,
                    1
                );
        }

    }
    elseif (
        $had_template &&
        (
            $legacy_front === null ||
            $legacy_front === ''
        ) &&
        $combined_source !== '' &&
        substr($combined_source, 0, 1) === "\n"
    ) {

        // 해당 언어의 front가 없더라도 구버전은 앞에
        // 줄바꿈 한 개를 추가했으므로 그 한 개만 제거한다.
        $combined_source =
            substr(
                $combined_source,
                1
            );
    }


    // --------------------------------------------------------
    // rear 제거
    // --------------------------------------------------------

    if (
        $legacy_rear !== null &&
        $legacy_rear !== '' &&
        strlen($combined_source) >= strlen($legacy_rear) &&
        substr(
            $combined_source,
            -strlen($legacy_rear)
        ) === $legacy_rear
    ) {

        $combined_source =
            substr(
                $combined_source,
                0,
                strlen($combined_source) -
                strlen($legacy_rear)
            );


        // 기존 결합 과정이 추가했던 줄바꿈 한 개만 제거
        if (
            $combined_source !== '' &&
            substr($combined_source, -1) === "\n"
        ) {

            $combined_source =
                substr(
                    $combined_source,
                    0,
                    -1
                );
        }

    }
    elseif (
        $had_template &&
        (
            $legacy_rear === null ||
            $legacy_rear === ''
        ) &&
        $combined_source !== '' &&
        substr($combined_source, -1) === "\n"
    ) {

        $combined_source =
            substr(
                $combined_source,
                0,
                -1
            );
    }


    return $combined_source;
}
