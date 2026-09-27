<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_manage_admin_notice()) {
    http_response_code(403);
    exit("공지사항을 추가할 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$title = "";
$content = "";
$copy_mode = false;

$copy_news_id =
    isset($_GET["cid"])
    ? (int)$_GET["cid"]
    : 0;

if ($copy_news_id > 0) {
    $copy_rows =
        pdo_query(
            "
            SELECT
                news_id,
                title,
                content
            FROM news
            WHERE news_id = ?
            LIMIT 1
            ",
            $copy_news_id
        );

    if ($copy_rows === false) {
        http_response_code(500);
        exit("복사할 공지사항을 불러올 수 없습니다.");
    }

    if (!isset($copy_rows[0])) {
        http_response_code(404);
        exit("복사할 공지사항을 찾을 수 없습니다.");
    }

    $title =
        (string)$copy_rows[0]["title"] .
        " - 복사본";

    $content =
        (string)$copy_rows[0]["content"];

    $copy_mode = true;
}

if (
    isset($_SERVER["REQUEST_METHOD"]) &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["pid"]) &&
    is_array($_POST["pid"])
) {
    $problem_ids =
        array();

    foreach ($_POST["pid"] as $problem_id) {
        $problem_id =
            (int)$problem_id;

        if ($problem_id > 0) {
            $problem_ids[$problem_id] =
                $problem_id;
        }
    }

    sort(
        $problem_ids,
        SORT_NUMERIC
    );

    $problem_keyword =
        isset($_POST["keyword"])
        ? trim((string)$_POST["keyword"])
        : "";

    if (count($problem_ids) > 0) {
        $content =
            "[plist=" .
            implode(
                ",",
                $problem_ids
            ) .
            "]" .
            $problem_keyword .
            "[/plist]";
    }
}

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
    $copy_mode
    ? "공지사항 복사"
    : "공지사항 추가";

$admin_active_menu =
    "news_add";

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
                <?php
                echo $copy_mode
                    ? "공지사항 복사"
                    : "공지사항 추가";
                ?>
            </h1>

            <div class="admin-page-description">
                <?php if ($copy_mode) { ?>
                    기존 공지사항을 복사하여 새 공지사항으로 저장합니다.
                <?php } else { ?>
                    사이트 첫 화면과 공지 상세 화면에 표시할 내용을 작성합니다.
                <?php } ?>
            </div>
        </div>

        <a
            href="news_list.php"
            class="admin-btn admin-btn-secondary">
            공지사항 목록
        </a>

    </div>

    <form
        method="post"
        action="news_add.php">

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
                        제목과 본문을 입력한 후 저장합니다.
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
                공지사항 저장
            </button>

        </div>

    </form>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
