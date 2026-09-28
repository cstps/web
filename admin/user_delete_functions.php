<?php

// ============================================================
// 사용자 영구 삭제 차단 대상
//
// privilege와 loginlog는 삭제 시 함께 정리하므로 제외한다.
// 백업 테이블은 운영 자료가 아니므로 삭제 판단에서 제외한다.
// ============================================================

function oj_admin_user_delete_reference_checks()
{
    return array(
        array(
            "table" => "balloon",
            "column" => "user_id",
            "label" => "풍선 처리 기록"
        ),
        array(
            "table" => "coding_news",
            "column" => "user_id",
            "label" => "IT NEWS 작성 기록"
        ),
        array(
            "table" => "contest",
            "column" => "user_id",
            "label" => "대회 생성 기록"
        ),
        array(
            "table" => "course_contest_student_grant",
            "column" => "user_id",
            "label" => "수업 대회 학생 권한"
        ),
        array(
            "table" => "course_student",
            "column" => "user_id",
            "label" => "수업 학생 기록"
        ),
        array(
            "table" => "course_student_ai_log",
            "column" => "student_user_id",
            "label" => "학생 AI 사용 기록"
        ),
        array(
            "table" => "course_student_memo",
            "column" => "user_id",
            "label" => "학생 메모"
        ),
        array(
            "table" => "course_student_record_draft",
            "column" => "user_id",
            "label" => "학생 기록 초안"
        ),
        array(
            "table" => "course_teacher",
            "column" => "user_id",
            "label" => "수업 교사 기록"
        ),
        array(
            "table" => "draw",
            "column" => "user_id",
            "label" => "추첨 기록"
        ),
        array(
            "table" => "mail",
            "column" => "to_user",
            "label" => "받은 쪽지"
        ),
        array(
            "table" => "mail",
            "column" => "from_user",
            "label" => "보낸 쪽지"
        ),
        array(
            "table" => "news",
            "column" => "user_id",
            "label" => "공지사항 작성 기록"
        ),
        array(
            "table" => "printer",
            "column" => "user_id",
            "label" => "출력 요청 기록"
        ),
        array(
            "table" => "reply",
            "column" => "author_id",
            "label" => "게시판 답글"
        ),
        array(
            "table" => "share_code",
            "column" => "user_id",
            "label" => "공유 코드"
        ),
        array(
            "table" => "solution",
            "column" => "user_id",
            "label" => "문제 제출 기록"
        ),
        array(
            "table" => "solution_process",
            "column" => "user_id",
            "label" => "문제 해결 과정"
        ),
        array(
            "table" => "teacher_process_note",
            "column" => "user_id",
            "label" => "학생 과정 관찰 기록"
        ),
        array(
            "table" => "teacher_process_note",
            "column" => "teacher_id",
            "label" => "교사 관찰 기록"
        ),
        array(
            "table" => "topic",
            "column" => "author_id",
            "label" => "게시판 글"
        )
    );
}


// ============================================================
// 삭제를 차단하는 활동 기록 확인
//
// 반환값:
// - array: 발견된 활동 기록 설명
// - false: DB 조회 실패
// ============================================================

function oj_admin_user_delete_blockers(
    $user_id
) {
    $blockers =
        array();

    foreach (
        oj_admin_user_delete_reference_checks()
        as
        $check
    ) {
        $table =
            $check["table"];

        $column =
            $check["column"];

        $sql =
            "SELECT 1 AS found " .
            "FROM `" . $table . "` " .
            "WHERE `" . $column . "` = ? " .
            "LIMIT 1";

        $rows =
            pdo_query(
                $sql,
                $user_id
            );

        if ($rows === false) {
            return false;
        }

        if (isset($rows[0])) {
            $blockers[] =
                $check["label"];
        }
    }

    return $blockers;
}


// ============================================================
// 활성 최고관리자 권한 보유 여부
// ============================================================

function oj_admin_user_has_administrator_right(
    $user_id
) {
    $rows =
        pdo_query(
            "
            SELECT 1 AS found
            FROM privilege
            WHERE user_id = ?
              AND rightstr = 'administrator'
              AND defunct = 'N'
            LIMIT 1
            ",
            $user_id
        );

    if ($rows === false) {
        return null;
    }

    return isset($rows[0]);
}


// ============================================================
// 현재 계정 생성 이력의 관리자 확인
//
// 반환값:
// - 문자열: 계정을 생성한 관리자 ID
// - 빈 문자열: 확인 가능한 생성 이력 없음
// - null: DB 조회 실패
// ============================================================

function oj_admin_user_creation_actor(
    $user_id
) {
    $rows =
        pdo_query(
            "
            SELECT
                action,
                actor_user_id
            FROM admin_user_audit
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
            ",
            $user_id
        );

    if ($rows === false) {
        return null;
    }

    if (
        !isset($rows[0]) ||
        (string)$rows[0]["action"] !== "create"
    ) {
        return "";
    }

    return trim(
        (string)$rows[0]["actor_user_id"]
    );
}


// ============================================================
// 비밀번호 관리자의 삭제를 차단하는 활성 특별권한 확인
//
// 최고관리자를 포함한 모든 전역 특별권한을 차단한다.
// 대회 참가권한(c번호)과 문제 보기권한(s번호)은 제외한다.
//
// 반환값:
// - true 또는 false: 특별권한 보유 여부
// - null: DB 조회 실패
// ============================================================

function oj_admin_user_has_protected_delete_right(
    $user_id
) {
    $rows =
        pdo_query(
            "
            SELECT 1 AS found
            FROM privilege
            WHERE user_id = ?
              AND defunct = 'N'
              AND rightstr IN (
                  'administrator',
                  'problem_editor',
                  'source_browser',
                  'contest_creator',
                  'http_judge',
                  'password_setter',
                  'printer',
                  'balloon',
                  'vip',
                  'problem_start',
                  'problem_end',
                  'system_config_manager'
              )
            LIMIT 1
            ",
            $user_id
        );

    if ($rows === false) {
        return null;
    }

    return isset($rows[0]);
}
