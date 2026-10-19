<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');
require_once('./include/course_functions.inc.php');

$view_title = '그림 문제 정답 관리';
header('Cache-Control: no-store, private');

function simulation_reference_error($message, $status = 400)
{
    global $OJ_TEMPLATE, $view_errors, $view_title;

    http_response_code($status);
    $view_errors = '<h2>' . htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ) . '</h2>';

    require('template/' . $OJ_TEMPLATE . '/error.php');
    exit;
}

function simulation_reference_context($activity_id, $lock = false)
{
    $rows = pdo_query(
        "SELECT
            ca.activity_id,
            ca.course_id,
            ca.title,
            ca.status AS activity_status,
            c.status AS course_status,
            cl.status AS lesson_status,
            s.simulation_mode,
            s.canvas_width,
            s.canvas_height
         FROM course_activity ca
         INNER JOIN course c
           ON c.course_id = ca.course_id
         INNER JOIN course_lesson cl
           ON cl.lesson_id = ca.lesson_id
          AND cl.course_id = ca.course_id
         INNER JOIN course_simulation s
           ON s.activity_id = ca.activity_id
         WHERE ca.activity_id = ?
           AND ca.activity_type = 'simulation'" .
        ($lock ? ' FOR UPDATE' : ''),
        $activity_id
    );

    if ($rows === false) {
        throw new RuntimeException('활동 조회 실패');
    }

    if (!isset($rows[0])) {
        throw new RuntimeException('유효한 시뮬레이션 활동이 아닙니다.');
    }

    $row = $rows[0];
    $course_id = intval($row['course_id']);

    if (
        !course_can_access($course_id) ||
        !course_can_manage_contests($course_id)
    ) {
        throw new RuntimeException('정답 관리 권한이 없습니다.');
    }

    if (
        intval($row['course_status']) !== 1 ||
        intval($row['activity_status']) !== 1 ||
        intval($row['lesson_status']) !== 1
    ) {
        throw new RuntimeException('비활성 수업 또는 학습 활동입니다.');
    }

    if ($row['simulation_mode'] !== 'drawing') {
        throw new RuntimeException(
            '시뮬레이션 편집에서 그림 문제 모드로 설정해 주세요.'
        );
    }

    if (
        intval($row['canvas_width']) !== 400 ||
        intval($row['canvas_height']) !== 400
    ) {
        throw new RuntimeException(
            '현재 정답 관리는 400×400 캔버스만 지원합니다.'
        );
    }

    return $row;
}

if (!isset($_SESSION[$OJ_NAME.'_user_id'])) {
    simulation_reference_error('로그인이 필요합니다.', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, array('GET', 'POST'), true)) {
    header('Allow: GET, POST');
    simulation_reference_error('지원하지 않는 요청입니다.', 405);
}

$raw_id = $method === 'POST'
    ? ($_POST['activity_id'] ?? null)
    : ($_GET['activity_id'] ?? null);

if (
    !is_string($raw_id) ||
    !preg_match('/^[1-9][0-9]*$/D', $raw_id) ||
    strlen($raw_id) > 10 ||
    (float)$raw_id > 2147483647
) {
    simulation_reference_error('활동 번호가 올바르지 않습니다.');
}

$activity_id = intval($raw_id);

try {
    $view_reference_activity = simulation_reference_context($activity_id);
} catch (Throwable $e) {
    error_log('[simulation_reference_access] ' . $e->getMessage());
    simulation_reference_error(
        '정답 관리 권한과 활동 상태를 확인해 주세요. ' .
        '그림 문제 모드이며 캔버스가 400×400이어야 합니다.',
        403
    );
}

if ($method === 'POST') {
    // 기존 일회용 토큰 처리 전에 자료형과 정확한 일치를 검사한다.
    $csrf = $_POST['csrf'] ?? null;
    $keys = $_SESSION[$OJ_NAME.'_csrf_keys'] ?? null;

    if (
        !is_string($csrf) ||
        !is_array($keys) ||
        !in_array($csrf, $keys, true)
    ) {
        simulation_reference_error('보안 토큰이 유효하지 않습니다.', 403);
    }

    require_once('./include/csrf_check.php');

    $source = $_POST['source_code'] ?? null;
    $base = $_POST['base_reference_no'] ?? null;
    $radius = $_POST['position_tolerance'] ?? null;
    $max_score = $_POST['max_score'] ?? null;

    if (
        !is_string($source) ||
        trim($source) === '' ||
        strlen($source) > 200000 ||
        !is_string($base) ||
        !preg_match('/^(0|[1-9][0-9]*)$/D', $base) ||
        strlen($base) > 10 ||
        (float)$base > 2147483646 ||
        !is_string($radius) ||
        !in_array($radius, array('0', '1', '2'), true) ||
        !is_string($max_score) ||
        !preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,2})?$/D', $max_score)
    ) {
        simulation_reference_error(
            '입력값이 올바르지 않습니다. 뒤로 가서 내용을 확인해 주세요.'
        );
    }

    $parts = explode('.', $max_score, 2);
    $score_cents = intval($parts[0]) * 100
        + intval(str_pad($parts[1] ?? '', 2, '0'));

    if ($score_cents <= 0 || $score_cents > 9999999) {
        simulation_reference_error('배점은 0 초과 99999.99 이하여야 합니다.');
    }

    $normalized_score = sprintf(
        '%d.%02d',
        intdiv($score_cents, 100),
        $score_cents % 100
    );

    $render_config = json_encode(array(
        'schema_version' => 1,
        'renderer' => 'P2D',
        'pixel_density' => 1,
        'background_rgb' => array(255, 255, 255)
    ), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $grading_config = json_encode(array(
        'schema_version' => 1,
        'experimental' => true,
        'metric' => 'symmetric_foreground_color_coverage',
        'position_tolerance_px' => intval($radius),
        'neighborhood' => 'euclidean_disk',
        'color_tolerance' => 0
    ), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    global $dbh;
    $transaction_started = false;

    try {
        if (!isset($dbh) || !($dbh instanceof PDO)) {
            throw new RuntimeException('데이터베이스 연결 실패');
        }

        if ($dbh->inTransaction()) {
            throw new RuntimeException('이미 진행 중인 트랜잭션');
        }

        $dbh->beginTransaction();
        $transaction_started = true;

        // 활동별 설정 행을 잠가 동시 버전 저장을 직렬화한다.
        simulation_reference_context($activity_id, true);

        $latest = $dbh->prepare(
            "SELECT reference_no
             FROM course_simulation_reference
             WHERE activity_id = ?
             ORDER BY reference_no DESC
             LIMIT 1
             FOR UPDATE"
        );
        $latest->execute(array($activity_id));
        $latest_row = $latest->fetch(PDO::FETCH_ASSOC);
        $latest->closeCursor();

        $latest_no = $latest_row
            ? intval($latest_row['reference_no'])
            : 0;

        if ($latest_no !== intval($base)) {
            throw new RuntimeException('다른 저장 요청으로 정답 버전이 변경됨');
        }

        if ($latest_no >= 2147483647) {
            throw new RuntimeException('정답 버전 범위 초과');
        }

        $save = $dbh->prepare(
            "INSERT INTO course_simulation_reference
                (
                    activity_id, reference_no, source_code,
                    canvas_width, canvas_height, p5_version,
                    render_config, grading_config, max_score,
                    created_by
                )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $save->execute(array(
            $activity_id,
            $latest_no + 1,
            $source,
            400,
            400,
            '2.3.4',
            $render_config,
            $grading_config,
            $normalized_score,
            (string)$_SESSION[$OJ_NAME.'_user_id']
        ));
        $save->closeCursor();

        $saved_reference_id = (string)$dbh->lastInsertId();

        // 새 정답과 이미지 생성 요청을 같은 트랜잭션으로 저장한다.
        $image_request = $dbh->prepare(
            "INSERT INTO course_simulation_reference_image
                (reference_id, source_sha256, image_status)
             VALUES (?, ?, 'pending')"
        );
        $image_request->execute(array(
            $saved_reference_id,
            hash('sha256', (string)$source),
        ));
        $image_request->closeCursor();

        $dbh->commit();
        $transaction_started = false;
    } catch (Throwable $e) {
        if ($transaction_started && $dbh->inTransaction()) {
            $dbh->rollBack();
        }

        error_log('[simulation_reference_save] ' . $e->getMessage());
        simulation_reference_error(
            '정답 저장에 실패했습니다. 다른 창에서 저장했거나 활동 상태가 ' .
            '변경됐을 수 있습니다. 입력 코드를 복사해 보관한 뒤 ' .
            '정답 관리 화면을 다시 열어 주세요.',
            409
        );
    }

    // 정답과 생성 요청의 커밋이 완료된 뒤 처리기에 알린다.
    // 알림 실패로 저장 완료된 정답을 실패 처리하지 않는다.
    try {
        $notify_errno = 0;
        $notify_error = '';

        $notify_socket = @stream_socket_client(
            'unix:///run/1024kr-simulation-grade/notify.sock',
            $notify_errno,
            $notify_error,
            0.05,
            STREAM_CLIENT_CONNECT
        );

        if ($notify_socket !== false) {
            fclose($notify_socket);
        } else {
            error_log(
                '[simulation_reference_save] ' .
                '이미지 생성 알림 실패. 정답과 생성 요청은 보존됨. 오류 번호: ' .
                $notify_errno
            );
        }
    } catch (Throwable $notify_exception) {
        error_log(
            '[simulation_reference_save] ' .
            '이미지 생성 알림 처리 실패. 정답과 생성 요청은 보존됨.'
        );
    }

    header(
        'Location: course_simulation_reference.php?activity_id=' .
        $activity_id . '&saved=1',
        true,
        303
    );
    exit;
}

$reference_rows = pdo_query(
    "SELECT
        reference_no,
        source_code,
        max_score,
        grading_config,
        created_at
     FROM course_simulation_reference
     WHERE activity_id = ?
     ORDER BY reference_no DESC
     LIMIT 1",
    $activity_id
);

if ($reference_rows === false) {
    simulation_reference_error('정답 조회에 실패했습니다.', 500);
}

$view_reference = $reference_rows[0] ?? array(
    'reference_no' => 0,
    'source_code' => '',
    'max_score' => '100.00',
    'grading_config' => '{}',
    'created_at' => ''
);

$config = json_decode($view_reference['grading_config'], true);
$view_reference_radius = intval(
    $config['position_tolerance_px'] ?? 0
);

require(
    'template/' . $OJ_TEMPLATE . '/course_simulation_reference.php'
);
