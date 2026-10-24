<?php

require_once(__DIR__ . '/admin-init.php');

if (!oj_is_admin()) {
    http_response_code(403);
    exit('관리자만 워드클라우드 전체 관리를 할 수 있습니다.');
}

$admin_page_title = '워드클라우드 관리';
$admin_active_menu = 'wordcloud_list';

function admin_wordcloud_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

try {
    if (
        pdo_query('SELECT 1') === false ||
        !isset($dbh) ||
        !($dbh instanceof PDO)
    ) {
        throw new RuntimeException('DB 연결 실패');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once(__DIR__ . '/../include/csrf_check.php');

        $action = $_POST['action'] ?? '';
        $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

        if ($room_id <= 0) {
            throw new RuntimeException('방 번호가 올바르지 않습니다.');
        }

        if ($action === 'toggle') {
            $stmt = $dbh->prepare(
                'UPDATE wordcloud_room
                 SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END
                 WHERE room_id = ?'
            );
            $stmt->execute(array($room_id));
            $stmt->closeCursor();
        } elseif ($action === 'delete') {
            $dbh->beginTransaction();

            $stmt = $dbh->prepare(
                'DELETE FROM wordcloud_entry
                 WHERE room_id = ?'
            );
            $stmt->execute(array($room_id));
            $stmt->closeCursor();

            $stmt = $dbh->prepare(
                'DELETE FROM wordcloud_room
                 WHERE room_id = ?'
            );
            $stmt->execute(array($room_id));
            $stmt->closeCursor();

            $dbh->commit();
        } else {
            throw new RuntimeException('지원하지 않는 요청입니다.');
        }

        header('Location: wordcloud.php');
        exit;
    }

    $rows = pdo_query(
        "SELECT r.room_id, r.owner_user_id, r.title, r.access_code,
                r.status, r.allow_anonymous, r.created_at,
                COUNT(e.entry_id) AS entry_count
         FROM wordcloud_room r
         LEFT JOIN wordcloud_entry e
           ON e.room_id = r.room_id
         GROUP BY r.room_id
         ORDER BY r.room_id DESC
         LIMIT 200"
    );

    if ($rows === false) {
        throw new RuntimeException('목록 조회 실패');
    }

    $wordcloud_rooms = $rows;

} catch (Throwable $e) {
    if (isset($dbh) && $dbh instanceof PDO && $dbh->inTransaction()) {
        $dbh->rollBack();
    }

    error_log('[admin_wordcloud] ' . $e->getMessage());
    $wordcloud_error = '워드클라우드 목록을 불러오지 못했습니다.';
    $wordcloud_rooms = array();
}

require(__DIR__ . '/admin-layout-start.php');

ob_start();
include(__DIR__ . '/../csrf.php');
$wordcloud_csrf_html = ob_get_clean();
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">워드클라우드 관리</h1>
            <div class="admin-page-description">
                생성된 워드클라우드 방과 학생 입력 수를 확인하고 관리합니다.
            </div>
        </div>
        <a class="admin-btn admin-btn-secondary" href="../tools/wordcloud.php" target="_blank">
            사용자 화면 열기
        </a>
    </div>

    <?php if (isset($wordcloud_error)) { ?>
        <div class="admin-card">
            <?php echo admin_wordcloud_escape($wordcloud_error); ?>
        </div>
    <?php } ?>

    <div class="admin-card">
        <?php if (!$wordcloud_rooms) { ?>
            <p>생성된 워드클라우드 방이 없습니다.</p>
        <?php } else { ?>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>번호</th>
                            <th>제목</th>
                            <th>소유자</th>
                            <th>공유 코드</th>
                            <th>입력 수</th>
                            <th>상태</th>
                            <th>익명 허용</th>
                            <th>생성일</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wordcloud_rooms as $room) { ?>
                            <tr>
                                <td><?php echo intval($room['room_id']); ?></td>
                                <td>
                                    <a href="../tools/wordcloud.php?code=<?php
                                        echo rawurlencode((string)$room['access_code']);
                                    ?>" target="_blank">
                                        <?php echo admin_wordcloud_escape($room['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo admin_wordcloud_escape($room['owner_user_id']); ?></td>
                                <td><?php echo admin_wordcloud_escape($room['access_code']); ?></td>
                                <td><?php echo intval($room['entry_count']); ?></td>
                                <td><?php echo intval($room['status']) === 1 ? '진행 중' : '중지'; ?></td>
                                <td><?php echo intval($room['allow_anonymous']) === 1 ? '허용' : '로그인 필요'; ?></td>
                                <td><?php echo admin_wordcloud_escape($room['created_at']); ?></td>
                                <td>
                                    <form method="post" action="wordcloud.php" style="display:inline;">
                                        <?php echo $wordcloud_csrf_html; ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="room_id" value="<?php echo intval($room['room_id']); ?>">
                                        <button class="admin-btn admin-btn-secondary" type="submit">
                                            <?php echo intval($room['status']) === 1 ? '중지' : '다시 열기'; ?>
                                        </button>
                                    </form>

                                    <form method="post" action="wordcloud.php" style="display:inline;"
                                          onsubmit="return confirm('이 방과 입력 내용을 삭제할까요?');">
                                        <?php echo $wordcloud_csrf_html; ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="room_id" value="<?php echo intval($room['room_id']); ?>">
                                        <button class="admin-btn admin-btn-danger" type="submit">
                                            삭제
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php require(__DIR__ . '/admin-layout-end.php'); ?>
