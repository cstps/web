<?php
$show_title = "새 글 작성 - " . $MSG_BBS . " - " . $OJ_NAME;

require_once(
    dirname(__FILE__) .
    "/../../lang/$OJ_LANG.php"
);

include("template/$OJ_TEMPLATE/header.php");
?>

<script src="../tinymce/tinymce.min.js?v=8.9.0"></script>

<style>
.oj-newpost-page {
        --newpost-primary: #2563eb;
        --newpost-primary-dark: #1d4ed8;
        --newpost-text: #172033;
        --newpost-muted: #667085;
        --newpost-border: #e4e7ec;
        --newpost-bg: #f8fafc;

        width: min(960px, calc(100% - 32px));
        margin: 32px auto 48px;
        color: var(--newpost-text);
}

.oj-newpost-header {
        margin-bottom: 18px;
}

.oj-newpost-header h1 {
        margin: 0;
        font-size: 26px;
        line-height: 1.35;
        font-weight: 800;
}

.oj-newpost-header p {
        margin: 8px 0 0;
        color: var(--newpost-muted);
        font-size: 14px;
}

.oj-newpost-card {
        overflow: hidden;
        background: #fff;
        border: 1px solid var(--newpost-border);
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.oj-newpost-form {
        padding: 24px;
}

.oj-newpost-field {
        margin-bottom: 22px;
}

.oj-newpost-field:last-child {
        margin-bottom: 0;
}

.oj-newpost-label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 700;
}

.oj-newpost-optional {
        margin-left: 4px;
        color: var(--newpost-muted);
        font-size: 12px;
        font-weight: 500;
}

.oj-newpost-input {
        box-sizing: border-box;
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid var(--newpost-border);
        border-radius: 8px;
        background: #fff;
        color: var(--newpost-text);
        font-size: 15px;
        transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;
}

.oj-newpost-input:focus {
        outline: none;
        border-color: var(--newpost-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.oj-newpost-problem {
        max-width: 220px;
}

.oj-newpost-help {
        margin: 7px 0 0;
        color: var(--newpost-muted);
        font-size: 12px;
        line-height: 1.5;
}

.oj-newpost-editor-wrap {
        border-radius: 8px;
}

.oj-newpost-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 18px 24px;
        border-top: 1px solid var(--newpost-border);
        background: var(--newpost-bg);
}

.oj-newpost-button {
        min-width: 88px;
        min-height: 42px;
        padding: 9px 18px;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
}

.oj-newpost-button.is-secondary {
        border-color: var(--newpost-border);
        background: #fff;
        color: #344054;
}

.oj-newpost-button.is-primary {
        background: var(--newpost-primary);
        color: #fff;
}

.oj-newpost-button.is-primary:hover {
        background: var(--newpost-primary-dark);
}

@media (max-width: 640px) {
        .oj-newpost-page {
                width: calc(100% - 20px);
                margin-top: 20px;
        }

        .oj-newpost-form {
                padding: 18px;
        }

        .oj-newpost-actions {
                padding: 14px 18px;
        }

        .oj-newpost-button {
                flex: 1;
        }

        .oj-newpost-problem {
                max-width: none;
        }
}
</style>

<main class="oj-newpost-page">
        <header class="oj-newpost-header">
                <h1>새 글 작성</h1>


                        <p>
                                질문이나 정보를 작성하여 사용자들과 공유할 수 있습니다.
                        </p>

        </header>

        <section class="oj-newpost-card">
                <form
                        class="oj-newpost-form-wrap"
                        action="post.php?action=new"
                        method="post">

                        <?php require(dirname(__FILE__) . "/../../include/set_post_key.php"); ?>
<div class="oj-newpost-form">
                                <div class="oj-newpost-field">
                                        <label
                                                class="oj-newpost-label"
                                                for="newpost-pid">
                                                문제번호
                                                <span class="oj-newpost-optional">
                                                        선택
                                                </span>
                                        </label>

                                        <input
                                                class="oj-newpost-input oj-newpost-problem"
                                                id="newpost-pid"
                                                name="pid"
                                                type="text"
                                                value="<?php
                                                        echo htmlspecialchars(
                                                                (string)$pid,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                        );
                                                ?>">

                                        <p class="oj-newpost-help">

                                                        특정 문제에 관한 글이면 문제 ID를 입력하세요.

                                        </p>
                                </div>

                                <div class="oj-newpost-field">
                                        <label
                                                class="oj-newpost-label"
                                                for="newpost-title">
                                                제목
                                        </label>

                                        <input
                                                class="oj-newpost-input"
                                                id="newpost-title"
                                                name="title"
                                                type="text"
                                                autocomplete="off"
                                                required>
                                </div>

                                <div class="oj-newpost-field">
                                        <label
                                                class="oj-newpost-label"
                                                for="mytextarea">
                                                내용
                                        </label>

                                        <div class="oj-newpost-editor-wrap">
                                                <textarea
                                                        id="mytextarea"
                                                        name="content"></textarea>
                                        </div>
                                </div>
                        </div>

                        <footer class="oj-newpost-actions">
                                <button
                                        class="oj-newpost-button is-secondary"
                                        type="button"
                                        onclick="history.back();">
                                        취소
                                </button>

                                <button
                                        class="oj-newpost-button is-primary"
                                        type="submit">
                                        글 등록
                                </button>
                        </footer>
                </form>
        </section>
</main>

<script>
(function() {
        'use strict';

        if (
                !window.tinymce ||
                !document.getElementById('mytextarea')
        ) {
                return;
        }

        tinymce.init({
                selector: '#mytextarea',
                license_key: 'gpl',
                cache_suffix: '?v=8.9.0',
                height: 420,
                menubar: false,
                branding: false,
                promotion: false,
                plugins: 'autolink link lists code fullscreen wordcount',
                toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | code fullscreen',
                toolbar_mode: 'sliding',
                browser_spellcheck: true,
                content_style:
                        'body {' +
                        'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", sans-serif;' +
                        'font-size: 15px;' +
                        'line-height: 1.7;' +
                        'color: #344054;' +
                        '}'
        });
})();
</script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
