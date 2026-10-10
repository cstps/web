<?php

// ============================================================
// Course 공통 권한 함수
//
// 전제:
// - 이 파일을 include하기 전에 db_info.inc.php가 로드되어 있어야 함
// - $_SESSION, $OJ_NAME, pdo_query() 사용 가능 상태
//
// 권한 원칙:
// - administrator : 모든 수업 접근 가능
// - owner         : 해당 수업 접근 가능
// - teacher       : 해당 수업 접근 가능
// - assistant     : 해당 수업 접근 가능
// - 그 외 HUSTOJ 전역 권한은 course 접근권한으로 사용하지 않음
// ============================================================


// ============================================================
// 현재 로그인 사용자의 Course 역할 반환
//
// 반환값:
// administrator
// owner
// teacher
// assistant
// null
// ============================================================

function course_get_role($course_id) {

    global $OJ_NAME;

    $course_id = intval($course_id);

    if ($course_id <= 0) {
        return null;
    }


    // --------------------------------------------------------
    // 로그인 확인
    // --------------------------------------------------------

    if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
        return null;
    }

    $user_id = $_SESSION[$OJ_NAME.'_user_id'];


    // --------------------------------------------------------
    // 사이트 관리자는 모든 Course에 대한 관리자 권한
    // --------------------------------------------------------

    if (isset($_SESSION[$OJ_NAME.'_administrator'])) {
        return 'administrator';
    }


    // --------------------------------------------------------
    // 해당 Course에 등록된 현재 담당교사 확인
    // --------------------------------------------------------

    $rows = pdo_query(
        "SELECT role
           FROM course_teacher
          WHERE course_id = ?
            AND user_id = ?
            AND status = 1
          LIMIT 1",
        $course_id,
        $user_id
    );


    if (!$rows || !isset($rows[0]['role'])) {
        return null;
    }

    $role = $rows[0]['role'];


    // DB에 예상하지 못한 role 값이 들어가더라도
    // 권한으로 인정하지 않음
    if (!in_array(
        $role,
        array('owner', 'teacher', 'assistant'),
        true
    )) {
        return null;
    }


    return $role;
}


// ============================================================
// 현재 사용자가 해당 Course에 접근 가능한지 확인
// ============================================================

function course_can_access($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner' ||
        $role === 'teacher' ||
        $role === 'assistant'
    );
}

// ============================================================
// 현재 사용자가 해당 Course의 활성 수강생인지 확인
//
// Course가 종료됐더라도 수강 기록 열람을 위해 접근을 허용한다.
// 개별 학생의 수강 상태가 status=0이면 접근을 허용하지 않는다.
// ============================================================

// ============================================================
// 지정한 사용자가 해당 Course의 활성 수강생인지 확인
//
// 학생 수행모드 판정뿐 아니라,
// 교사가 특정 학생의 수강 상태를 확인할 때도 사용할 수 있다.
// ============================================================

function course_is_active_student_user(
    $course_id,
    $user_id
) {

    $course_id = intval($course_id);
    $user_id = trim((string)$user_id);

    if (
        $course_id <= 0 ||
        $user_id === ''
    ) {
        return false;
    }


    $rows = pdo_query(
        "SELECT user_id
           FROM course_student
          WHERE course_id = ?
            AND user_id = ?
            AND status = 1
          LIMIT 1",
        $course_id,
        $user_id
    );


    return (
        $rows &&
        isset($rows[0]['user_id'])
    );
}


// ============================================================
// 현재 사용자가 해당 Course의 활성 수강생인지 확인
//
// Course가 종료됐더라도 수강 기록 열람을 위해 접근을 허용한다.
// 개별 학생의 수강 상태가 status=0이면 접근을 허용하지 않는다.
// ============================================================

function course_is_active_student($course_id) {

    global $OJ_NAME;

    if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
        return false;
    }


    return course_is_active_student_user(
        $course_id,
        $_SESSION[$OJ_NAME.'_user_id']
    );
}


// ============================================================
// Course 코드 입력 보안 적용 여부
//
// 적용 우선순위:
//
// 1. 수행모드 학생
//    → Course 개별 설정과 관계없이 항상 차단
//
// 2. 일반 Course 차시
//    → course.block_code_clipboard = 1
//    → 해당 Course의 활성 수강생
//    → 복사/붙여넣기 차단
//
// 일반 문제(problem.php?id=...)처럼 contest_id가 없는 경우에는
// Course를 특정할 수 없으므로 일반모드에서는 적용하지 않는다.
// ============================================================

function course_should_block_code_clipboard(
    $user_id,
    $contest_id
) {

    $user_id =
        trim((string)$user_id);

    $contest_id =
        intval($contest_id);


    if ($user_id === '') {
        return false;
    }


    // 수행모드는 항상 강제 차단한다.
    if (
        course_is_user_in_performance_mode(
            $user_id
        )
    ) {
        return true;
    }


    // 일반 문제는 Course를 특정할 수 없다.
    if ($contest_id <= 0) {
        return false;
    }


    $rows =
        pdo_query(
            "SELECT
                cc.course_id,
                c.block_code_clipboard
             FROM course_contest cc
             INNER JOIN course c
                ON c.course_id = cc.course_id
             WHERE cc.contest_id = ?
               AND cc.status = 1
               AND c.status = 1
             LIMIT 1",
            $contest_id
        );


    if (
        !$rows ||
        !isset($rows[0]['course_id'])
    ) {
        return false;
    }


    $course_id =
        intval(
            $rows[0]['course_id']
        );

    $block_code_clipboard =
        isset(
            $rows[0]['block_code_clipboard']
        )
            ? intval(
                $rows[0]['block_code_clipboard']
            )
            : 0;


    if ($block_code_clipboard !== 1) {
        return false;
    }


    return course_is_active_student_user(
        $course_id,
        $user_id
    );
}


// ============================================================
// 지정한 학생에게 현재 적용 중인 Course 수행평가 세션 조회
//
// 적용 조건:
// - course_performance_session.status = 1
// - 해당 Course에 학생이 status = 1로 등록되어 있음
//
// 중요:
// - Contest 참가 여부나 제출 여부는 검사하지 않는다.
// - 서로 다른 Course에서 동시에 수행모드가 진행될 수 있으므로
//   단일 행이 아니라 전체 활성 세션 목록을 반환한다.
// ============================================================

function course_get_user_active_performance_sessions(
    $user_id
) {

    $user_id = trim((string)$user_id);

    if ($user_id === '') {
        return array();
    }


    $rows = pdo_query(
        "SELECT
             cps.id,
             cps.course_id,
             cps.started_at,
             cps.started_by
           FROM course_performance_session cps
           INNER JOIN course_student cs
                   ON cs.course_id = cps.course_id
                  AND cs.user_id = ?
                  AND cs.status = 1
          WHERE cps.status = 1
          ORDER BY cps.started_at ASC,
                   cps.id ASC",
        $user_id
    );


    if (!$rows) {
        return array();
    }


    return $rows;
}


// ============================================================
// 지정한 학생이 현재 Course 수행모드 적용 대상인지 확인
// ============================================================

function course_is_user_in_performance_mode(
    $user_id
) {

    $sessions =
        course_get_user_active_performance_sessions(
            $user_id
        );


    return count($sessions) > 0;
}



// ============================================================
// 현재 학생에게 적용되는 수행모드 기준 시작시각
//
// 여러 Course 수행모드에 동시에 참여 중인 경우
// 가장 먼저 시작된 활성 수행모드의 시작시각을 사용한다.
//
// 이유:
// - 다른 Course 수행모드가 중간에 시작되더라도
//   기존 제한 기준시각이 뒤로 이동하지 않도록 한다.
// - 일부 Course 수행모드가 먼저 종료되더라도
//   남아 있는 활성 수행모드 범위 안에서만 기준을 다시 계산한다.
// ============================================================

function course_get_user_performance_cutoff(
    $user_id
) {

    $sessions =
        course_get_user_active_performance_sessions(
            $user_id
        );

    if (!$sessions) {
        return null;
    }

    $cutoff = null;

    foreach ($sessions as $session) {

        if (
            !isset($session['started_at']) ||
            trim((string)$session['started_at']) === ''
        ) {
            continue;
        }

        $started_at =
            trim(
                (string)$session['started_at']
            );

        if (
            $cutoff === null ||
            $started_at < $cutoff
        ) {
            $cutoff = $started_at;
        }
    }

    return $cutoff;
}


// ============================================================
// 특정 기록이 현재 수행모드 시작 전 기록인지 확인
// ============================================================

function course_should_restrict_student_record(
    $user_id,
    $record_time
) {

    $user_id =
        trim((string)$user_id);

    $record_time =
        trim((string)$record_time);

    if (
        $user_id === '' ||
        $record_time === ''
    ) {
        return false;
    }

    $cutoff =
        course_get_user_performance_cutoff(
            $user_id
        );

    if ($cutoff === null) {
        return false;
    }

    return $record_time < $cutoff;
}

// ============================================================
// Course 기본 정보 수정 권한
//
// administrator / owner
// ============================================================

function course_can_edit($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner'
    );
}


// ============================================================
// Course 담당교사 관리 권한
//
// administrator / owner
// ============================================================

function course_can_manage_teachers($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner'
    );
}



// ============================================================
// Course 수행모드 중 학생의 기존 학습 기록 열람 제한 여부
//
// 현재는 활성 수행모드 Course의 활성 학생이면 제한한다.
//
// 향후 다음 기록 차단에서도 같은 함수를 사용한다.
// - 제출 소스
// - 제출 목록
// - 문제 해결 과정
// - AJAX / SSE 기록 조회
// ============================================================

function course_should_restrict_student_history(
    $user_id
) {

    $user_id = trim((string)$user_id);

    if ($user_id === '') {
        return false;
    }

    return course_is_user_in_performance_mode(
        $user_id
    );
}


// ============================================================
// Course 학생 관리 권한
//
// administrator / owner / teacher
// ============================================================

function course_can_manage_students($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner' ||
        $role === 'teacher'
    );
}


// ============================================================
// Course 대회 연결 관리 권한
//
// administrator / owner / teacher
// ============================================================

function course_can_manage_contests($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner' ||
        $role === 'teacher'
    );
}


// ============================================================
// Course 수행모드 관리 권한
//
// 허용:
// - administrator
// - owner
// - teacher
//
// assistant는 수행평가 시작·종료 권한에서 제외한다.
// ============================================================

function course_can_manage_performance($course_id) {

    $role = course_get_role($course_id);

    return (
        $role === 'administrator' ||
        $role === 'owner' ||
        $role === 'teacher'
    );
}


// ============================================================
// Course 수행모드 시작
//
// 같은 Course에서는 동시에 하나의 수행모드만 진행할 수 있다.
//
// 반환:
// - 성공: 생성된 session id
// - 실패: false
// ============================================================

function course_start_performance_session(
    $course_id
) {

    global $dbh, $OJ_NAME;

    $course_id = intval($course_id);

    if ($course_id <= 0) {
        return false;
    }

    if (!course_can_manage_performance($course_id)) {
        return false;
    }

    if (
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        return false;
    }

    $user_id =
        isset($_SESSION[$OJ_NAME . '_user_id'])
            ? trim(
                (string)$_SESSION[
                    $OJ_NAME . '_user_id'
                ]
            )
            : '';

    if ($user_id === '') {
        return false;
    }

    $transaction_started = false;

    try {

        if (!$dbh->inTransaction()) {
            $dbh->beginTransaction();
            $transaction_started = true;
        }


        // ----------------------------------------------------
        // Course 행 자체를 잠근다.
        //
        // 활성 수행모드 행이 아직 없는 경우에도
        // 같은 Course에 대한 동시 시작 요청을 직렬화하기 위해
        // Course 행을 기준 잠금으로 사용한다.
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "SELECT course_id
               FROM course
              WHERE course_id = ?
              FOR UPDATE"
        );

        $stmt->execute([$course_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Course not found.'
            );
        }


        // ----------------------------------------------------
        // 같은 Course에서 이미 진행 중인 수행모드가 있는지 확인
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "SELECT id
               FROM course_performance_session
              WHERE course_id = ?
                AND status = 1
              ORDER BY id DESC
              LIMIT 1"
        );

        $stmt->execute([$course_id]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Performance session already active.'
            );
        }


        // ----------------------------------------------------
        // Course 수행모드 시작
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "INSERT INTO course_performance_session
                (
                    course_id,
                    status,
                    started_at,
                    started_by
                )
             VALUES
                (
                    ?,
                    1,
                    NOW(),
                    ?
                )"
        );

        $stmt->execute([
            $course_id,
            $user_id
        ]);

        $session_id =
            intval($dbh->lastInsertId());


        if ($transaction_started) {
            $dbh->commit();
        }

        return $session_id;

    } catch (Throwable $e) {

        if (
            $transaction_started &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        error_log(
            'course_start_performance_session: ' .
            $e->getMessage()
        );

        return false;
    }
}


// ============================================================
// Course 수행모드 종료
//
// 해당 Course의 현재 진행 중인 수행모드를 종료한다.
//
// 반환:
// - 성공: true
// - 실패: false
// ============================================================

function course_end_performance_session($course_id) {

    global $dbh, $OJ_NAME;

    $course_id = intval($course_id);

    if ($course_id <= 0) {
        return false;
    }

    if (!course_can_manage_performance($course_id)) {
        return false;
    }

    if (
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        return false;
    }

    $user_id =
        isset($_SESSION[$OJ_NAME . '_user_id'])
            ? trim(
                (string)$_SESSION[
                    $OJ_NAME . '_user_id'
                ]
            )
            : '';

    if ($user_id === '') {
        return false;
    }

    $transaction_started = false;

    try {

        if (!$dbh->inTransaction()) {
            $dbh->beginTransaction();
            $transaction_started = true;
        }


        // ----------------------------------------------------
        // 시작 함수와 동일하게 Course 행을 잠근다.
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "SELECT course_id
               FROM course
              WHERE course_id = ?
              FOR UPDATE"
        );

        $stmt->execute([$course_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Course not found.'
            );
        }


        // ----------------------------------------------------
        // 현재 진행 중인 수행모드 확인
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "SELECT id
               FROM course_performance_session
              WHERE course_id = ?
                AND status = 1
              ORDER BY id DESC
              LIMIT 1
              FOR UPDATE"
        );

        $stmt->execute([$course_id]);

        $session =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$session ||
            !isset($session['id'])
        ) {
            throw new RuntimeException(
                'Active performance session not found.'
            );
        }

        $session_id =
            intval($session['id']);


        // ----------------------------------------------------
        // 수행모드 종료
        // ----------------------------------------------------

        $stmt = $dbh->prepare(
            "UPDATE course_performance_session
                SET status = 0,
                    ended_at = NOW(),
                    ended_by = ?
              WHERE id = ?
                AND status = 1"
        );

        $stmt->execute([
            $user_id,
            $session_id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Failed to end performance session.'
            );
        }


        if ($transaction_started) {
            $dbh->commit();
        }

        return true;

    } catch (Throwable $e) {

        if (
            $transaction_started &&
            $dbh->inTransaction()
        ) {
            $dbh->rollBack();
        }

        error_log(
            'course_end_performance_session: ' .
            $e->getMessage()
        );

        return false;
    }
}


// ============================================================
// Course 차시의 문제 해결 과정 현황 열람 권한
//
// 허용:
// - administrator
// - 활성 Course의 owner
// - 활성 Course의 teacher
//
// assistant는 차시 관리 권한이 없으므로 제외한다.
// 종료된 Course도 기존 학습 기록은 열람할 수 있으므로
// course.status는 검사하지 않는다.
// ============================================================

function course_can_view_contest_process($contest_id) {

    $contest_id = intval($contest_id);

    if ($contest_id <= 0) {
        return false;
    }


    $course_rows = pdo_query(
        "SELECT course_id
           FROM course_contest
          WHERE contest_id = ?
            AND status = 1
          LIMIT 1",
        $contest_id
    );


    if (
        !$course_rows ||
        !isset($course_rows[0]['course_id'])
    ) {
        return false;
    }


    $course_id =
        intval($course_rows[0]['course_id']);


    return course_can_manage_contests($course_id);
}


// ============================================================
// linked Contest 학생 참가권한 부여
//
// 적용 조건:
// - Course 활성
// - 학생 활성
// - 차시 활성
// - 차시 공개
// - link_type = linked
//
// 기존 privilege가 있으면 수동 권한으로 보고 그대로 유지한다.
// 권한이 없을 때만 Course가 privilege를 추가하고 추적한다.
// ============================================================

function course_grant_linked_student_right(
    $course_id,
    $contest_id,
    $user_id
) {

    $course_id =
        intval($course_id);

    $contest_id =
        intval($contest_id);

    $user_id =
        trim($user_id);


    if (
        $course_id <= 0 ||
        $contest_id <= 0 ||
        $user_id === '' ||
        strlen($user_id) > 48
    ) {
        return false;
    }


    $rightstr =
        "c".$contest_id;


    // --------------------------------------------------------
    // 실제 권한 부여 대상인지 서버에서 재확인
    // --------------------------------------------------------

    $eligible_rows = pdo_query(
        "SELECT
            cc.id

         FROM course_contest cc

         INNER JOIN course c
           ON c.course_id = cc.course_id

         INNER JOIN course_student cs
           ON cs.course_id = cc.course_id
          AND cs.user_id = ?

         WHERE cc.course_id = ?
           AND cc.contest_id = ?
           AND cc.link_type = 'linked'
           AND cc.status = 1
           AND cc.visible = 1
           AND c.status = 1
           AND cs.status = 1

         LIMIT 1",
        $user_id,
        $course_id,
        $contest_id
    );


    if (
        !$eligible_rows ||
        !isset($eligible_rows[0]['id'])
    ) {
        return false;
    }


    // --------------------------------------------------------
    // Course 권한 추적 기록 확인
    // --------------------------------------------------------

    $grant_rows = pdo_query(
        "SELECT
            id,
            status
         FROM course_contest_student_grant
         WHERE course_id = ?
           AND contest_id = ?
           AND user_id = ?
           AND rightstr = ?
         LIMIT 1",
        $course_id,
        $contest_id,
        $user_id,
        $rightstr
    );


    // DB 조회 실패
    if (
        $grant_rows === false ||
        $grant_rows === null
    ) {
        return false;
    }


    $grant_id = 0;
    $grant_status = 0;


    if (
        $grant_rows &&
        isset($grant_rows[0]['id'])
    ) {

        $grant_id =
            intval($grant_rows[0]['id']);

        $grant_status =
            intval($grant_rows[0]['status']);
    }


    // --------------------------------------------------------
    // 현재 활성 privilege 확인
    // --------------------------------------------------------

    $privilege_rows = pdo_query(
        "SELECT
            user_id
         FROM privilege
         WHERE user_id = ?
           AND rightstr = ?
           AND valuestr = 'true'
           AND defunct = 'N'
         LIMIT 1",
        $user_id,
        $rightstr
    );


    // DB 조회 실패
    if (
        $privilege_rows === false ||
        $privilege_rows === null
    ) {
        return false;
    }


    $privilege_exists = (
        $privilege_rows &&
        isset($privilege_rows[0]['user_id'])
    );


    // --------------------------------------------------------
    // 이미 Course가 관리 중이며 실제 권한도 존재하면 완료
    // --------------------------------------------------------

    if (
        $grant_status === 1 &&
        $privilege_exists
    ) {
        return true;
    }


    // --------------------------------------------------------
    // Course 추적 기록은 활성인데 실제 privilege가 없다면 복구
    // --------------------------------------------------------

    if (
        $grant_status === 1 &&
        !$privilege_exists
    ) {

        $repair_result =
            pdo_query(
                "INSERT INTO privilege
        (
            user_id,
            rightstr,
            valuestr,
            defunct
        )
        VALUES (?, ?, 'true', 'N')",
                $user_id,
                $rightstr
            );

        if (
            $repair_result === false ||
            $repair_result === null
        ) {
            return false;
        }

        return true;

    }


    // --------------------------------------------------------
    // Course가 관리하지 않는 기존 권한이 이미 있으면 보존
    //
    // 추적 행을 활성화하지 않는다.
    // 나중에 Course에서 학생을 제외해도 이 권한은 삭제하지 않는다.
    // --------------------------------------------------------

    if ($privilege_exists) {
        return true;
    }


    // --------------------------------------------------------
    // 권한이 없으므로 Course가 새로 생성
    // --------------------------------------------------------

    $privilege_inserted = false;


    try {

        $privilege_result =
            pdo_query(
                "INSERT INTO privilege
        (
            user_id,
            rightstr,
            valuestr,
            defunct
        )
        VALUES (?, ?, 'true', 'N')",
                $user_id,
                $rightstr
            );

        if (
            $privilege_result === false ||
            $privilege_result === null
        ) {
            throw new RuntimeException(
                "학생의 Contest 참가권한 부여에 실패했습니다."
            );
        }

        $privilege_inserted = true;


        // ------------------------------------------------------------
        // Course 권한 추적 기록 저장
        // ------------------------------------------------------------

        if ($grant_id > 0) {

            // 기존 추적 기록을 다시 활성화
            $grant_result =
                pdo_query(
                    "UPDATE course_contest_student_grant
             SET
                status = 1,
                granted_at = NOW(),
                revoked_at = NULL
             WHERE id = ?",
                    $grant_id
                );
        } else {

            // 새로운 추적 기록 생성
            $grant_result =
                pdo_query(
                    "INSERT INTO course_contest_student_grant
            (
                course_id,
                contest_id,
                user_id,
                rightstr,
                status,
                granted_at,
                revoked_at
            )
            VALUES
            (
                ?, ?, ?, ?, 1, NOW(), NULL
            )",
                    $course_id,
                    $contest_id,
                    $user_id,
                    $rightstr
                );
        }


        // ------------------------------------------------------------
        // 추적 기록 저장 실패 확인
        // ------------------------------------------------------------

        if (
            $grant_result === false ||
            $grant_result === null
        ) {
            throw new RuntimeException(
                "Course 권한 추적 기록 저장에 실패했습니다."
            );
        }
    } catch (Exception $e) {

        /*
         * privilege는 MyISAM이므로 트랜잭션 rollback이 되지 않는다.
         * 추적 기록 저장에 실패하면 방금 추가한 권한 한 행을 정리한다.
         */

        if ($privilege_inserted) {

            try {

                pdo_query(
                    "DELETE FROM privilege
                     WHERE user_id = ?
                       AND rightstr = ?
                       AND valuestr = 'true'
                       AND defunct = 'N'
                     LIMIT 1",
                    $user_id,
                    $rightstr
                );

            }
            catch (Exception $cleanup_error) {

                // 정리 실패는 여기서 별도 출력하지 않는다.
            }
        }


        throw $e;
    }


    return true;
}


// ============================================================
// linked Contest 학생 참가권한 회수
//
// Course가 실제 생성하여 추적 중인 권한만 한 행 회수한다.
// 기존 수동 권한은 추적 기록이 없으므로 삭제하지 않는다.
//
// 회수 함수는 학생 제외·숨김·차시 제거·Course 종료 후에도
// 호출해야 하므로 현재 Course/차시 상태를 검사하지 않는다.
// ============================================================

function course_revoke_linked_student_right(
    $course_id,
    $contest_id,
    $user_id
) {

    $course_id =
        intval($course_id);

    $contest_id =
        intval($contest_id);

    $user_id =
        trim($user_id);


    if (
        $course_id <= 0 ||
        $contest_id <= 0 ||
        $user_id === '' ||
        strlen($user_id) > 48
    ) {
        return false;
    }


    $rightstr =
        "c".$contest_id;


    // --------------------------------------------------------
    // Course가 활성 상태로 추적 중인 권한인지 확인
    // --------------------------------------------------------

    $grant_rows = pdo_query(
        "SELECT
            id
         FROM course_contest_student_grant
         WHERE course_id = ?
           AND contest_id = ?
           AND user_id = ?
           AND rightstr = ?
           AND status = 1
         LIMIT 1",
        $course_id,
        $contest_id,
        $user_id,
        $rightstr
    );


    if (
        $grant_rows === false ||
        $grant_rows === null
    ) {
        return false;
    }

    if (
        !$grant_rows ||
        !isset($grant_rows[0]['id'])
    ) {
        // Course가 생성한 권한이 아니므로 삭제하지 않는다.
        return true;
    }


    $grant_id =
        intval($grant_rows[0]['id']);


    /*
     * privilege가 MyISAM이므로 두 테이블을 하나의 트랜잭션으로
     * 처리할 수 없다.
     *
     * 먼저 추적 상태를 비활성화한 뒤 privilege 한 행을 삭제한다.
     * 삭제 실패 시 추적 상태를 가능한 범위에서 복구한다.
     */

    $tracking_result =
        pdo_query(
            "UPDATE course_contest_student_grant
         SET
            status = 0,
            revoked_at = NOW()
         WHERE id = ?",
            $grant_id
        );

    if (
        $tracking_result === false ||
        $tracking_result === null
    ) {
        return false;
    }


    try {

        $delete_result =
            pdo_query(
                "DELETE FROM privilege
         WHERE user_id = ?
           AND rightstr = ?
           AND valuestr = 'true'
           AND defunct = 'N'
         LIMIT 1",
                $user_id,
                $rightstr
            );

        if (
            $delete_result === false ||
            $delete_result === null
        ) {
            throw new RuntimeException(
                "학생의 Contest 참가권한 회수에 실패했습니다."
            );
        }

    }
    catch (Exception $e) {

        try {

            pdo_query(
                "UPDATE course_contest_student_grant
                 SET
                    status = 1,
                    revoked_at = NULL
                 WHERE id = ?",
                $grant_id
            );

        }
        catch (Exception $restore_error) {

            // 복구 실패는 여기서 별도 출력하지 않는다.
        }


        throw $e;
    }


    return true;
}


// ============================================================
// Course Lesson 조회
// ============================================================

function course_get_lesson($lesson_id) {

    $lesson_id = intval($lesson_id);

    if ($lesson_id <= 0) {
        return null;
    }


    $rows =
        pdo_query(
            "SELECT
                lesson_id,
                course_id,
                lesson_no,
                title,
                description,
                start_time,
                end_time,
                sort_order,
                visible,
                status,
                created_by,
                created_at,
                updated_at
             FROM course_lesson
             WHERE lesson_id = ?
             LIMIT 1",
            $lesson_id
        );


    if (
        !$rows ||
        !isset($rows[0])
    ) {
        return null;
    }


    return $rows[0];
}


// ============================================================
// Contest에 연결된 Course Lesson 조회
//
// 신규 구조:
// course_contest.lesson_id -> course_lesson
//
// 호환 처리:
// lesson_id가 없는 기존 데이터는
// course_contest.lesson_no / contest 정보를 사용한다.
// ============================================================

function course_get_lesson_by_contest($contest_id) {

    $contest_id = intval($contest_id);

    if ($contest_id <= 0) {
        return null;
    }


    $rows =
        pdo_query(
            "SELECT
                cc.id AS course_contest_id,
                cc.course_id,
                cc.contest_id,
                cc.lesson_id,

                COALESCE(
                    cl.lesson_no,
                    cc.lesson_no
                ) AS lesson_no,

                COALESCE(
                    NULLIF(cl.title, ''),
                    NULLIF(c.title, ''),
                    CONCAT(
                        cc.lesson_no,
                        '차시'
                    )
                ) AS title,

                cl.description,

                COALESCE(
                    cl.start_time,
                    c.start_time
                ) AS start_time,

                COALESCE(
                    cl.end_time,
                    c.end_time
                ) AS end_time,

                COALESCE(
                    cl.sort_order,
                    cc.sort_order
                ) AS sort_order,

                COALESCE(
                    cl.visible,
                    cc.visible
                ) AS visible,

                cc.status AS course_contest_status,

                cl.status AS lesson_status

             FROM course_contest cc

             LEFT JOIN course_lesson cl
                    ON cl.lesson_id = cc.lesson_id

             LEFT JOIN contest c
                    ON c.contest_id = cc.contest_id

             WHERE cc.contest_id = ?

             LIMIT 1",
            $contest_id
        );


    if (
        !$rows ||
        !isset($rows[0])
    ) {
        return null;
    }


    return $rows[0];
}


// ============================================================
// Course Contest 행에서 유효한 차시 번호 반환
// ============================================================

function course_get_effective_lesson_no($row) {

    if (!is_array($row)) {
        return 0;
    }


    if (
        isset($row['course_lesson_no']) &&
        intval($row['course_lesson_no']) > 0
    ) {
        return intval(
            $row['course_lesson_no']
        );
    }


    if (
        isset($row['lesson_no']) &&
        intval($row['lesson_no']) > 0
    ) {
        return intval(
            $row['lesson_no']
        );
    }


    return 0;
}


// ============================================================
// Course Activity 목록 조회
//
// 기존 Contest는 course_contest의 상태 및 공개 설정을
// 우선 사용한다.
//
// 이 함수는 데이터만 조회한다.
// 호출하는 페이지에서 Course 접근 권한을 먼저 검사해야 한다.
// ============================================================

function course_get_activities($course_id) {

    $course_id = intval($course_id);

    if ($course_id <= 0) {
        return array();
    }

    $rows = pdo_query(
        "SELECT
            ca.activity_id,
            ca.course_id,
            ca.lesson_id,
            ca.activity_type,
            ca.title AS activity_title,
            ca.description,

            COALESCE(
                cl.lesson_no,
                cc.lesson_no,
                0
            ) AS lesson_no,

            CASE
                WHEN ca.activity_type = 'contest'
                THEN COALESCE(
                    cl.sort_order,
                    cc.sort_order,
                    ca.sort_order
                )
                ELSE ca.sort_order
            END AS lesson_sort_order,

            ca.sort_order AS activity_sort_order,

            ca.visible AS activity_visible,
            cl.visible AS lesson_visible,
            cc.visible AS contest_visible,

            CASE
                WHEN ca.visible = 1
                 AND (
                     cl.lesson_id IS NULL
                     OR (
                         cl.status = 1
                         AND cl.visible = 1
                     )
                 )
                 AND (
                     ca.activity_type <> 'contest'
                     OR cc.visible = 1
                 )
                THEN 1
                ELSE 0
            END AS visible,

            ca.status,

            cac.course_contest_id,
            cc.contest_id,

            CASE
                WHEN ca.activity_type = 'contest'
                THEN COALESCE(
                    NULLIF(c.title, ''),
                    NULLIF(ca.title, ''),
                    NULLIF(cl.title, '')
                )
                ELSE ca.title
            END AS display_title

         FROM course_activity ca

         LEFT JOIN course_activity_contest cac
           ON cac.activity_id = ca.activity_id

         LEFT JOIN course_contest cc
           ON cc.id = cac.course_contest_id
          AND cc.course_id = ca.course_id

         LEFT JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id

         LEFT JOIN contest c
           ON c.contest_id = cc.contest_id

         WHERE ca.course_id = ?
           AND ca.status = 1
           AND (
               ca.activity_type <> 'contest'
               OR (
                   cc.id IS NOT NULL
                   AND cc.status = 1
               )
           )

         ORDER BY
            lesson_no,
            lesson_sort_order,
            activity_sort_order,
            ca.activity_id",
        $course_id
    );

    return is_array($rows) ? $rows : array();
}


// ============================================================
// Course Lesson 목록 조회
//
// Contest 연결 여부와 관계없이 Lesson 자체를 조회한다.
// 호출 페이지에서 Course 접근 권한을 먼저 검사해야 한다.
// ============================================================

function course_get_lessons($course_id) {

    $course_id = intval($course_id);

    if ($course_id <= 0) {
        return array();
    }

    $rows = pdo_query(
        "SELECT
            lesson_id,
            course_id,
            lesson_no,
            title,
            description,
            start_time,
            end_time,
            sort_order,
            visible,
            status,
            created_by,
            created_at,
            updated_at
         FROM course_lesson
         WHERE course_id = ?
           AND status = 1
         ORDER BY
            sort_order,
            lesson_no,
            lesson_id",
        $course_id
    );

    return is_array($rows) ? $rows : array();
}


// 학생 학습기록 조회
// administrator / owner / teacher / assistant
function course_can_view_student_records($course_id) {

    $role = course_get_role($course_id);

    return in_array(
        $role,
        array(
            'administrator',
            'owner',
            'teacher',
            'assistant'
        ),
        true
    );
}


// 학생 학습기록 작성·변경
// administrator / owner / teacher
function course_can_manage_student_records($course_id) {

    $role = course_get_role($course_id);

    return in_array(
        $role,
        array(
            'administrator',
            'owner',
            'teacher'
        ),
        true
    );
}