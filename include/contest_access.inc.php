<?php

require_once(
    __DIR__ . '/permission_functions.inc.php'
);


if (!function_exists('contest_access_current_user_id')) {
    function contest_access_current_user_id()
    {
        global $OJ_NAME;

        $session_name =
            $OJ_NAME . '_user_id';

        if (
            !isset($_SESSION[$session_name]) ||
            !is_scalar($_SESSION[$session_name])
        ) {
            return '';
        }

        return trim(
            (string)$_SESSION[$session_name]
        );
    }
}


if (!function_exists('contest_can_access')) {
    function contest_can_access($contest_id)
    {
        global $OJ_NAME;

        $cid =
            intval($contest_id);

        if ($cid <= 0) {
            return false;
        }

        // 관리자와 해당 대회 관리자는 기록 확인을 위해 접근 허용
        if (
            oj_can_manage_contest($cid)
        ) {
            return true;
        }

        // --------------------------------------------------------
        // Contest 정보
        // --------------------------------------------------------

        $contest_rows =
            pdo_query(
                "SELECT
                    contest_id,
                    private,
                    defunct,
                    is_stopped,
                    is_archived
                 FROM contest
                 WHERE contest_id=?
                 LIMIT 1",
                $cid
            );

        if (
            !is_array($contest_rows) ||
            !isset($contest_rows[0]['contest_id'])
        ) {
            return false;
        }

        $contest =
            $contest_rows[0];

        $user_id =
            contest_access_current_user_id();


        // --------------------------------------------------------
        // Course 차시 연결 확인
        // --------------------------------------------------------

        $course_rows =
            pdo_query(
                "SELECT
                    cc.course_id,
                    cc.status,
                    cc.visible,
                    EXISTS(
                        SELECT 1
                        FROM course_teacher ct
                        WHERE ct.course_id=cc.course_id
                          AND ct.user_id=?
                          AND ct.status=1
                    ) AS course_staff,
                    EXISTS(
                        SELECT 1
                        FROM course_student cs
                        WHERE cs.course_id=cc.course_id
                          AND cs.user_id=?
                          AND cs.status=1
                    ) AS course_student
                 FROM course_contest cc
                 WHERE cc.contest_id=?
                 LIMIT 1",
                $user_id,
                $user_id,
                $cid
            );

        if (
            is_array($course_rows) &&
            isset($course_rows[0]['course_id'])
        ) {
            $course_contest =
                $course_rows[0];

            $status =
                intval($course_contest['status']);

            $visible =
                intval($course_contest['visible']);

            $course_staff =
                intval($course_contest['course_staff']) === 1;

            $course_student =
                intval($course_contest['course_student']) === 1;

            // 활성 차시의 담당 교사는 중지 상태에서도
            // 기존 학습·제출 기록을 확인할 수 있다.
            if (
                $course_staff &&
                $status === 1
            ) {
                return true;
            }

            // 중지·보관 대회는 Course 학생도 접근할 수 없다.
            if (
                intval($contest['is_stopped']) !== 0 ||
                intval($contest['is_archived']) !== 0
            ) {
                return false;
            }

            if (
                $course_student &&
                $status === 1 &&
                $visible === 1
            ) {
                return true;
            }

            // Course 차시가 비활성 또는 숨김이면
            // 일반 공개·참가 권한으로 우회하지 못하게 한다.
            if (
                $status !== 1 ||
                $visible !== 1
            ) {
                return false;
            }
        }


        // --------------------------------------------------------
        // 일반 Contest 접근 제한
        // --------------------------------------------------------

        if (
            intval($contest['is_stopped']) !== 0 ||
            intval($contest['is_archived']) !== 0
        ) {
            return false;
        }

        if (
            isset($contest['defunct']) &&
            (string)$contest['defunct'] === 'Y'
        ) {
            return false;
        }

        // 공개 Contest
        if (
            intval($contest['private']) === 0
        ) {
            return true;
        }

        if ($user_id === '') {
            return false;
        }


        // --------------------------------------------------------
        // 비공개 Contest 참가 권한
        // --------------------------------------------------------

        if (
            isset(
                $_SESSION[$OJ_NAME . '_c' . $cid]
            ) &&
            $_SESSION[$OJ_NAME . '_c' . $cid]
        ) {
            return true;
        }

        $participant_rows =
            pdo_query(
                "SELECT 1
                 FROM privilege
                 WHERE user_id=?
                   AND rightstr=?
                   AND valuestr='true'
                   AND defunct='N'
                 LIMIT 1",
                $user_id,
                'c' . $cid
            );

        if (
            is_array($participant_rows) &&
            count($participant_rows) > 0
        ) {
            return true;
        }

        return false;
    }
}


if (!function_exists('contest_can_submit')) {
    function contest_can_submit($contest_id)
    {
        $cid =
            intval($contest_id);

        if ($cid <= 0) {
            return false;
        }

        $user_id =
            contest_access_current_user_id();

        if ($user_id === '') {
            return false;
        }

        // 명시적으로 중지되거나 보관된 대회는
        // 관리자도 신규 제출·테스트 실행을 할 수 없다.
        // 조회 실패나 대회가 없는 경우에도 제출을 거부한다.
        $state_rows =
            pdo_query(
                "SELECT
                    is_stopped,
                    is_archived
                 FROM contest
                 WHERE contest_id=?
                 LIMIT 1",
                $cid
            );

        if (
            !is_array($state_rows) ||
            !isset(
                $state_rows[0]['is_stopped'],
                $state_rows[0]['is_archived']
            ) ||
            intval($state_rows[0]['is_stopped']) !== 0 ||
            intval($state_rows[0]['is_archived']) !== 0
        ) {
            return false;
        }

        // 기존 관리자 제출·테스트 예외 유지
        if (
            oj_can_manage_contest($cid)
        ) {
            return true;
        }

        // 일반 접근권한이 없으면 제출도 불가
        if (!contest_can_access($cid)) {
            return false;
        }

        // 일반 사용자는 대회 진행 시간에만 제출 가능
        $time_rows =
            pdo_query(
                "SELECT contest_id
                 FROM contest
                 WHERE contest_id=?
                   AND start_time<=NOW()
                   AND NOW()<end_time
                 LIMIT 1",
                $cid
            );

        if (
            !is_array($time_rows) ||
            !isset($time_rows[0]['contest_id'])
        ) {
            return false;
        }

        return true;
    }
}
