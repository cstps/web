<?php

// 작업 실행 중 유지할 비영속 전용 DB 연결을 반환한다.
// 반환된 연결은 실행기가 끝날 때까지 변수에 보관해야 한다.
// 다른 실행기가 잠금을 보유하고 있으면 null을 반환한다.
function simulation_job_acquire_lock($kind, $id)
{
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    if (
        !in_array($kind, array('grade', 'image', 'bank_image'), true) ||
        !preg_match('/^[1-9][0-9]{0,19}$/D', (string)$id)
    ) {
        throw new RuntimeException('작업 잠금 식별자가 올바르지 않습니다.');
    }

    $connection = new PDO(
        'mysql:host=' . $DB_HOST .
        ';dbname=' . $DB_NAME . ';charset=utf8mb4',
        $DB_USER,
        $DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        )
    );

    $name = '1024kr_simulation_job_' . $kind . '_' . $id;
    $stmt = $connection->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute(array($name));
    $result = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ((string)$result === '0') {
        return null;
    }

    if ((string)$result !== '1') {
        throw new RuntimeException('작업 실행 잠금을 얻지 못했습니다.');
    }

    return $connection;
}
