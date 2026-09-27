<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (
    !isset($_SERVER["REQUEST_METHOD"]) ||
    $_SERVER["REQUEST_METHOD"] !== "GET"
) {
    header("Allow: GET");
    http_response_code(405);
    exit("GET 요청만 허용됩니다.");
}

if (!oj_can_manage_admin_notice()) {
    http_response_code(403);
    exit("공지사항을 수정할 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$news_id =
    isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($news_id <= 0) {
    http_response_code(400);
    exit("공지사항 번호가 올바르지 않습니다.");
}

$news_rows =
    pdo_query(
        "
        SELECT
            news_id,
            title,
            content,
            user_id,
            time,
            defunct
        FROM news
        WHERE news_id = ?
        LIMIT 1
        ",
        $news_id
    );

if ($news_rows === false) {
    http_response_code(500);
    exit("공지사항을 불러올 수 없습니다.");
}

if (!isset($news_rows[0])) {
    http_response_code(404);
    exit("공지사항을 찾을 수 없습니다.");
}

$news =
    $news_rows[0];

$title =
    (string)$news["title"];

$content =
    (string)$news["content"];

$saved =
    isset($_GET["saved"]) &&
    $_GET["saved"] === "1";

$escape =
    static function ($value) {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES |
            ENT_SUBSTITUTE,
            "UTF-8"
        );
    };

$admin_page_title =
    "공지사항 수정";

$admin_active_menu =
    "news_list";

$admin_page_head_file =
    __DIR__ . "/tinymce.php";

require(
    __DIR__ . "/admin-layout-start.php"
);
?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                공지사항 수정
            </h1>

            <div class="admin-page-description">
                공지사항 번호
                <?php echo (int)$news_id; ?>의
                제목과 내용을 수정합니다.
            </div>
        </div>

        <a
            href="news_list.php"
            class="admin-btn admin-btn-secondary">
            공지사항 목록
        </a>

    </div>

    <?php if ($saved) { ?>

        <div
            class="admin-alert admin-alert-success"
            role="status">
            공지사항을 수정했습니다.
        </div>

    <?php } ?>

    <form
        method="post"
        action="news_update.php">

        <input
            type="hidden"
            name="news_id"
            value="<?php echo (int)$news_id; ?>">

        <div class="admin-csrf-fields">
            <?php
            require(
                __DIR__ .
                "/../include/set_post_key.php"
            );
            ?>
        </div>

        <div class="admin-form-card">

            <div class="admin-form-card-header">

                <span class="admin-form-step">
                    1
                </span>

                <div>
                    <div class="admin-form-card-title">
                        공지 내용
                    </div>

                    <div class="admin-form-card-desc">
                        변경할 제목과 본문을 입력한 후 저장합니다.
                    </div>
                </div>

            </div>

            <div class="admin-form-field">

                <label
                    class="admin-form-label"
                    for="news-title">
                    제목
                </label>

                <input
                    type="text"
                    id="news-title"
                    name="title"
                    class="admin-form-input"
                    maxlength="200"
                    required
                    value="<?php echo $escape($title); ?>">

            </div>

            <div class="admin-form-field">

                <label
                    class="admin-form-label"
                    for="news-content">
                    내용
                </label>

                <textarea
                    id="news-content"
                    name="content"
                    class="tinymce-editor"
                    rows="24"><?php
                    echo $escape($content);
                    ?></textarea>

            </div>

        </div>

        <div class="admin-form-actions">

            <a
                href="news_list.php"
                class="admin-btn admin-btn-secondary">
                취소
            </a>

            <button
                type="submit"
                class="admin-btn admin-btn-primary">
                변경 내용 저장
            </button>

        </div>

    </form>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
