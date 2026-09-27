<?php

require_once(
    __DIR__ . "/admin-init.php"
);

if (!oj_can_manage_admin_notice()) {
    http_response_code(403);
    exit("공지사항을 관리할 권한이 없습니다.");
}

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Pragma: no-cache"
);

$keyword =
    isset($_GET["keyword"])
    ? trim((string)$_GET["keyword"])
    : "";

if (strlen($keyword) > 200) {
    http_response_code(400);
    exit("검색어는 200바이트 이하로 입력해 주세요.");
}

$page =
    isset($_GET["page"])
    ? (int)$_GET["page"]
    : 1;

if ($page < 1) {
    $page = 1;
}

$per_page = 25;

if ($keyword !== "") {
    $search_keyword =
        "%" . $keyword . "%";

    $count_rows =
        pdo_query(
            "
            SELECT COUNT(*) AS ids
            FROM news
            WHERE title LIKE ?
               OR content LIKE ?
            ",
            $search_keyword,
            $search_keyword
        );
} else {
    $count_rows =
        pdo_query(
            "
            SELECT COUNT(*) AS ids
            FROM news
            "
        );
}

if (
    $count_rows === false ||
    !isset($count_rows[0])
) {
    http_response_code(500);
    exit("공지사항 개수를 확인할 수 없습니다.");
}

$total_count =
    (int)$count_rows[0]["ids"];

$total_pages =
    max(
        1,
        (int)ceil(
            $total_count /
            $per_page
        )
    );

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset =
    ($page - 1) *
    $per_page;

if ($keyword !== "") {
    $news_rows =
        pdo_query(
            "
            SELECT
                news_id,
                user_id,
                title,
                time,
                defunct
            FROM news
            WHERE title LIKE ?
               OR content LIKE ?
            ORDER BY news_id DESC
            LIMIT " .
            (int)$offset .
            ", " .
            (int)$per_page,
            $search_keyword,
            $search_keyword
        );
} else {
    $news_rows =
        pdo_query(
            "
            SELECT
                news_id,
                user_id,
                title,
                time,
                defunct
            FROM news
            ORDER BY news_id DESC
            LIMIT " .
            (int)$offset .
            ", " .
            (int)$per_page
        );
}

if ($news_rows === false) {
    http_response_code(500);
    exit("공지사항 목록을 불러올 수 없습니다.");
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

$page_url =
    static function (
        $target_page
    ) use (
        $keyword
    ) {
        $query =
            array(
                "page" =>
                    (string)$target_page
            );

        if ($keyword !== "") {
            $query["keyword"] =
                $keyword;
        }

        return
            "news_list.php?" .
            http_build_query($query);
    };

$page_group_size = 5;

$start_page =
    ((int)floor(
        ($page - 1) /
        $page_group_size
    ) * $page_group_size) + 1;

$end_page =
    min(
        $total_pages,
        $start_page +
        $page_group_size -
        1
    );

$updated =
    isset($_GET["updated"]) &&
    (string)$_GET["updated"] === "1";

$deleted =
    isset($_GET["deleted"]) &&
    (string)$_GET["deleted"] === "1";

$admin_page_title =
    "공지사항 목록";

$admin_active_menu =
    "news_list";

require(
    __DIR__ . "/admin-layout-start.php"
);
?>

<div class="admin-page">

    <div class="admin-page-header">

        <div>
            <h1 class="admin-page-title">
                공지사항 목록
            </h1>

            <div class="admin-page-description">
                사이트 공지사항을 검색하고 공개 상태를 관리합니다.
            </div>
        </div>

        <a
            href="news_add_page.php"
            class="admin-btn admin-btn-primary">
            공지사항 추가
        </a>

    </div>

    <?php if ($updated) { ?>
        <div
            class="admin-card"
            role="status">
            공지사항 공개 상태를 변경했습니다.
        </div>
    <?php } ?>

    <?php if ($deleted) { ?>
        <div
            class="admin-card"
            role="status">
            공지사항을 삭제했습니다.
        </div>
    <?php } ?>

    <div class="admin-card admin-filter-card">

        <form
            action="news_list.php"
            method="get"
            class="admin-search-form">

            <div class="admin-search-input-wrap">

                <input
                    type="text"
                    name="keyword"
                    class="admin-search-input"
                    value="<?php echo $escape($keyword); ?>"
                    maxlength="200"
                    placeholder="공지 제목 또는 내용 검색">

                <button
                    type="submit"
                    class="admin-btn admin-btn-primary">
                    검색
                </button>

                <?php if ($keyword !== "") { ?>
                    <a
                        href="news_list.php"
                        class="admin-btn admin-btn-secondary">
                        초기화
                    </a>
                <?php } ?>

            </div>

        </form>

    </div>

    <div class="admin-card admin-table-card">

        <div class="admin-pagination-wrap">

            <div class="admin-pagination-info">
                전체 <?php echo number_format($total_count); ?>개
                ·
                <?php echo $page; ?> /
                <?php echo $total_pages; ?> 페이지
            </div>

            <?php if ($total_pages > 1) { ?>
                <nav
                    class="admin-pagination"
                    aria-label="공지사항 목록 페이지">

                    <a
                        class="admin-page-link <?php
                        echo $page <= 1
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php echo $escape($page_url(1)); ?>"
                        title="첫 페이지">
                        «
                    </a>

                    <a
                        class="admin-page-link <?php
                        echo $page <= 1
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                max(
                                    1,
                                    $page - 1
                                )
                            )
                        );
                        ?>"
                        title="이전 페이지">
                        ‹
                    </a>

                    <?php
                    for (
                        $page_number = $start_page;
                        $page_number <= $end_page;
                        $page_number++
                    ) {
                    ?>
                        <a
                            class="admin-page-link <?php
                            echo $page === $page_number
                                ? "active"
                                : "";
                            ?>"
                            href="<?php
                            echo $escape(
                                $page_url(
                                    $page_number
                                )
                            );
                            ?>">
                            <?php echo $page_number; ?>
                        </a>
                    <?php } ?>

                    <a
                        class="admin-page-link <?php
                        echo $page >= $total_pages
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                min(
                                    $total_pages,
                                    $page + 1
                                )
                            )
                        );
                        ?>"
                        title="다음 페이지">
                        ›
                    </a>

                    <a
                        class="admin-page-link <?php
                        echo $page >= $total_pages
                            ? "disabled"
                            : "";
                        ?>"
                        href="<?php
                        echo $escape(
                            $page_url(
                                $total_pages
                            )
                        );
                        ?>"
                        title="마지막 페이지">
                        »
                    </a>

                </nav>
            <?php } ?>

        </div>

        <div class="admin-table-wrap">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>번호</th>
                        <th>제목</th>
                        <th>작성자</th>
                        <th>수정일시</th>
                        <th>공개 상태</th>
                        <th>관리</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($news_rows) === 0) { ?>
                        <tr>
                            <td colspan="6">
                                조건에 맞는 공지사항이 없습니다.
                            </td>
                        </tr>
                    <?php } ?>

                    <?php foreach ($news_rows as $news) { ?>
                        <?php
                        $news_id =
                            (int)$news["news_id"];

                        $is_public =
                            (string)$news["defunct"] === "N";
                        ?>

                        <tr>
                            <td>
                                <?php echo $news_id; ?>
                            </td>

                            <td>
                                <a
                                    href="news_edit.php?id=<?php
                                    echo $news_id;
                                    ?>">
                                    <?php
                                    echo $escape(
                                        $news["title"]
                                    );
                                    ?>
                                </a>
                            </td>

                            <td>
                                <?php
                                echo $escape(
                                    $news["user_id"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo $escape(
                                    $news["time"]
                                );
                                ?>
                            </td>

                            <td>
                                <form
                                    method="post"
                                    action="news_df_change.php">

                                    <div class="admin-csrf-fields">
                                        <?php
                                        require(
                                            __DIR__ .
                                            "/../include/set_post_key.php"
                                        );
                                        ?>
                                    </div>

                                    <input
                                        type="hidden"
                                        name="news_id"
                                        value="<?php echo $news_id; ?>">

                                    <input
                                        type="hidden"
                                        name="return_page"
                                        value="<?php echo $page; ?>">

                                    <input
                                        type="hidden"
                                        name="return_keyword"
                                        value="<?php
                                        echo $escape($keyword);
                                        ?>">

                                    <button
                                        type="submit"
                                        class="admin-status <?php
                                        echo $is_public
                                            ? "admin-status-public"
                                            : "admin-status-private";
                                        ?>"
                                        title="<?php
                                        echo $is_public
                                            ? "클릭하여 비공개로 변경"
                                            : "클릭하여 공개로 변경";
                                        ?>">
                                        <?php
                                        echo $is_public
                                            ? "공개"
                                            : "비공개";
                                        ?>
                                    </button>

                                </form>
                            </td>

                            <td>
                                <div class="admin-row-actions">

                                    <a
                                        href="news_add_page.php?cid=<?php
                                        echo $news_id;
                                        ?>"
                                        class="admin-row-action">
                                        복사
                                    </a>

                                    <?php if (!$is_public) { ?>

                                        <form
                                            method="post"
                                            action="news_delete.php"
                                            onsubmit="return confirm(
                                                '이 비공개 공지를 삭제하시겠습니까?\n삭제 후 복구할 수 없습니다.'
                                            );">

                                            <div class="admin-csrf-fields">
                                                <?php
                                                require(
                                                    __DIR__ .
                                                    "/../include/set_post_key.php"
                                                );
                                                ?>
                                            </div>

                                            <input
                                                type="hidden"
                                                name="news_id"
                                                value="<?php echo $news_id; ?>">

                                            <input
                                                type="hidden"
                                                name="return_page"
                                                value="<?php echo $page; ?>">

                                            <input
                                                type="hidden"
                                                name="return_keyword"
                                                value="<?php
                                                echo $escape($keyword);
                                                ?>">

                                            <button
                                                type="submit"
                                                class="admin-row-action admin-row-action-danger">
                                                삭제
                                            </button>

                                        </form>

                                    <?php } ?>

                                </div>
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php
require(
    __DIR__ . "/admin-layout-end.php"
);
?>
