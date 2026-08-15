<?php
require_once("./include/db_info.inc.php");

header('Content-Type: application/json');

try {
    // 수동으로 PDO 객체 만들기
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $contest_id = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
    $last_count = isset($_GET['last_count']) ? intval($_GET['last_count']) : 0;

    $response = [
        'has_update' => false,
        'update_count' => $last_count
    ];

    if ($contest_id > 0) {
        $sql = "SELECT update_count FROM ranking_cache WHERE contest_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contest_id]);
        $row = $stmt->fetch();

        if ($row && intval($row['update_count']) > $last_count) {
            $response['has_update'] = true;
            $response['update_count'] = intval($row['update_count']);
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "details" => $e->getMessage()]);
}
?>