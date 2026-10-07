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
    $problem_id =
        intval($problem_id);

    if ($problem_id <= 0) {
        return false;
    }

    $templates =
        oj_parse_legacy_problem_templates(
            $front_code,
            $rear_code,
            $language_names
        );

    if ($templates === false) {
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
            foreach (
                $kind_templates
                as $language_id => $template
            ) {
                $key =
                    intval($language_id) .
                    ':' .
                    $kind;

                $active_keys[$key] = true;

                if (isset($existing_by_key[$key])) {
                    $existing =
                        $existing_by_key[$key];

                    if (
                        (string)$existing['lang'] ===
                        (string)$template['lang'] &&
                        (string)$existing['content'] ===
                        (string)$template['content']
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
                            $template['lang'],
                            $template['content'],
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
                        intval($template['language_id']),
                        $template['lang'],
                        $kind,
                        $template['content']
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
                ' 동기화 실패: ' .
                $exception->getMessage()
        );

        return false;
    }
}




// ============================================================
// front + 학생 코드 + rear 결합
//
// trim()을 사용하지 않아 코드의 공백과 빈 줄을 보존한다.
// 코드 사이에 줄바꿈이 전혀 없을 때만 한 줄을 추가한다.
// ============================================================

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
    $user_source,
    $max_steps = 500
) {

    $max_steps = intval($max_steps);

    if ($max_steps < 1) {
        $max_steps = 500;
    }

    $encoded_source =
        base64_encode(
            (string)$user_source
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
_OJ_STEPS = []
_OJ_TRACE_TRUNCATED = False

# judge_client 저장 한도(512 KiB)보다 여유 있게 제한한다.
_OJ_MAX_TRACE_BYTES = 400 * 1024
_OJ_TRACE_BYTES = 0

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


def _oj_trace(frame, event, arg):
    global _OJ_TRACE_TRUNCATED
    global _OJ_TRACE_BYTES

    if frame.f_code.co_filename != "<student>":
        return _oj_trace

    if event == "line":

        # 새로운 줄에 도착했다는 것은
        # 직전에 기록한 줄의 실행이 끝났다는 뜻이다.
        if _OJ_STEPS:
            state = _oj_capture_state(frame)

            _OJ_STEPS[-1]["variables"] = (
                state["variables"]
            )

            _OJ_STEPS[-1]["stdout"] = (
                state["stdout"]
            )

        if len(_OJ_STEPS) >= _OJ_MAX_STEPS:
            _OJ_TRACE_TRUNCATED = True
            return _oj_trace

        # 지금부터 실행할 줄을 새 단계로 등록한다.
        new_step = {
            "line": int(frame.f_lineno),
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

        return _oj_trace

    if event == "return":

        # 마지막 줄은 다음 line 이벤트가 없으므로
        # 함수/프로그램 종료 시점에서 최종 상태를 반영한다.
        if _OJ_STEPS:
            state = _oj_capture_state(frame)

            _OJ_STEPS[-1]["variables"] = (
                state["variables"]
            )

            _OJ_STEPS[-1]["stdout"] = (
                state["stdout"]
            )

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
        "truncated": bool(_OJ_TRACE_TRUNCATED)
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
