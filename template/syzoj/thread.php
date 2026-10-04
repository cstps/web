<?php
$show_title =
    htmlspecialchars(
        (string)$topic_title,
        ENT_QUOTES,
        'UTF-8'
    ) .
    " - " .
    $MSG_BBS .
    " - " .
    $OJ_NAME;

require_once(
    dirname(__FILE__) .
    "/../../lang/$OJ_LANG.php"
);

include("template/$OJ_TEMPLATE/header.php");
include("include/bbcode.php");
?>

<?php
// 게시판 관리 요청 버튼을 POST 폼으로 출력한다.
$thread_render_action = function (
    $target,
    $action,
    $label,
    $rid = null,
    $level = null,
    $danger = false
) use ($tid, $OJ_NAME) {
    echo '<form class="thread-action-form" action="threadadmin.php" method="post"';

    if ($danger) {
        echo ' onsubmit="return confirm(\'삭제하시겠습니까?\');"';
    }

    echo '>';

    $fields = array(
        "target" => $target,
        "action" => $action,
        "tid" => $tid
    );

    if ($rid !== null) {
        $fields["rid"] = $rid;
    }

    if ($level !== null) {
        $fields["level"] = $level;
    }

    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' .
            htmlspecialchars(
                $name,
                ENT_QUOTES,
                "UTF-8"
            ) .
            '" value="' .
            htmlspecialchars(
                (string)$value,
                ENT_QUOTES,
                "UTF-8"
            ) .
            '">';
    }

    require(
        dirname(__FILE__) .
        "/../../include/set_post_key.php"
    );

    echo '<button type="submit" class="thread-action' .
        ($danger ? ' is-danger' : '') .
        '">' .
        htmlspecialchars(
            $label,
            ENT_QUOTES,
            "UTF-8"
        ) .
        '</button></form>';
};
?>

<script src="../tinymce/tinymce.min.js?v=8.9.0"></script>

<style>
	.oj-thread-page {
		--thread-primary: #2563eb;
		--thread-primary-dark: #1d4ed8;
		--thread-text: #172033;
		--thread-muted: #667085;
		--thread-border: #e4e7ec;
		--thread-surface: #ffffff;
		--thread-danger: #dc2626;
		width: min(1120px, calc(100% - 32px));
		margin: 28px auto 56px;
		color: var(--thread-text);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
			"Noto Sans KR", "Apple SD Gothic Neo", Arial, sans-serif;
		text-align: left;
	}

	.oj-thread-page,
	.oj-thread-page * {
		box-sizing: border-box;
	}

	.oj-thread-page a {
		color: inherit;
		text-decoration: none;
	}

	.thread-toolbar,
	.thread-title-card,
	.thread-reply-card,
	.thread-composer-card,
	.thread-empty {
		border: 1px solid var(--thread-border);
		background: var(--thread-surface);
		box-shadow: 0 8px 28px rgba(16, 24, 40, 0.06);
	}

	.thread-toolbar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
		margin-bottom: 14px;
		padding: 12px 14px;
		border-radius: 14px;
	}

	.thread-breadcrumb {
		display: flex;
		align-items: center;
		min-width: 0;
		color: var(--thread-muted);
		font-size: 14px;
		font-weight: 600;
	}

	.thread-breadcrumb a {
		overflow: hidden;
		color: var(--thread-primary);
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.thread-breadcrumb a:hover {
		color: var(--thread-primary-dark);
		text-decoration: underline;
	}

	.thread-primary-button,
	.thread-submit-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-height: 40px;
		border: 0;
		border-radius: 10px;
		background: var(--thread-primary);
		color: #ffffff !important;
		font-size: 14px;
		font-weight: 700;
		line-height: 1;
		cursor: pointer;
		transition: background-color 0.18s ease, box-shadow 0.18s ease,
			transform 0.18s ease;
	}

	.thread-primary-button {
		flex: 0 0 auto;
		padding: 0 16px;
	}

	.thread-primary-button:hover,
	.thread-submit-button:hover {
		background: var(--thread-primary-dark);
		box-shadow: 0 6px 16px rgba(37, 99, 235, 0.24);
		transform: translateY(-1px);
	}

	.thread-title-card {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 24px;
		margin-bottom: 18px;
		padding: 24px 26px;
		border-radius: 16px;
	}

	.thread-title-copy {
		min-width: 0;
	}

	.thread-eyebrow {
		margin-bottom: 7px;
		color: var(--thread-primary);
		font-size: 12px;
		font-weight: 800;
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}

	.thread-title {
		margin: 0;
		overflow-wrap: anywhere;
		color: var(--thread-text);
		font-size: clamp(22px, 3vw, 32px);
		font-weight: 800;
		line-height: 1.3;
		letter-spacing: -0.025em;
	}

        .thread-lock-badge {
                display: inline-block;
                margin-left: 10px;
                padding: 4px 8px;
                border-radius: 999px;
                background: #fef2f2;
                color: #b91c1c;
                font-size: 12px;
                font-weight: 800;
                vertical-align: middle;
                white-space: nowrap;
        }

        .thread-locked-notice {
                text-align: center;
                color: #64748b;
        }

        .thread-locked-notice strong {
                color: #b91c1c;
        }

        .thread-locked-notice p {
                margin: 8px 0 0;
        }

	.thread-admin-tools {
		position: relative;
		flex: 0 0 auto;
	}

	.thread-admin-tools summary {
		min-height: 38px;
		padding: 9px 13px;
		border: 1px solid var(--thread-border);
		border-radius: 9px;
		background: #f8fafc;
		color: #344054;
		font-size: 13px;
		font-weight: 700;
		cursor: pointer;
		list-style: none;
	}

	.thread-admin-tools summary::-webkit-details-marker {
		display: none;
	}

	.thread-admin-menu {
		position: absolute;
		z-index: 20;
		top: calc(100% + 8px);
		right: 0;
		display: flex;
		width: min(390px, calc(100vw - 48px));
		flex-wrap: wrap;
		gap: 8px;
		padding: 12px;
		border: 1px solid var(--thread-border);
		border-radius: 12px;
		background: #ffffff;
		box-shadow: 0 16px 36px rgba(16, 24, 40, 0.15);
	}

	.thread-admin-menu a {
		padding: 8px 10px;
		border-radius: 8px;
		background: #f2f4f7;
		color: #344054;
		font-size: 12px;
		font-weight: 700;
	}

	.thread-admin-menu a:hover {
		background: #e8eefc;
		color: var(--thread-primary-dark);
	}

	.thread-admin-menu .is-danger {
		color: var(--thread-danger);
	}

	.thread-reply-list {
		display: grid;
		gap: 14px;
	}

	.thread-reply-card {
		overflow: hidden;
		border-radius: 16px;
	}

        .thread-reply-status-badge {
                display: inline-block;
                margin-left: 8px;
                padding: 2px 7px;
                border-radius: 999px;
                background: #fff7ed;
                color: #c2410c;
                font-size: 11px;
                font-weight: 800;
                white-space: nowrap;
        }

	.thread-reply-card.is-blocked {
		border-color: #fed7aa;
	}

	.thread-reply-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
		padding: 15px 18px;
		border-bottom: 1px solid #edf0f4;
		background: #fbfcfe;
	}

	.thread-author {
		display: flex;
		align-items: center;
		min-width: 0;
		gap: 11px;
	}

	.thread-avatar {
		display: inline-flex;
		width: 38px;
		height: 38px;
		flex: 0 0 38px;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		background: linear-gradient(135deg, #2563eb, #7c3aed);
		color: #ffffff;
		font-size: 15px;
		font-weight: 800;
		text-transform: uppercase;
	}

	.thread-author-info {
		min-width: 0;
	}

	.thread-author-name {
		display: block;
		overflow: hidden;
		color: #1d2939;
		font-size: 14px;
		font-weight: 800;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.thread-author-name:hover {
		color: var(--thread-primary);
	}

	.thread-time {
		display: block;
		margin-top: 2px;
		color: var(--thread-muted);
		font-size: 12px;
		font-weight: 500;
	}

	.thread-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: flex-end;
		gap: 6px;
	}

	.thread-action {
		display: inline-flex;
		min-height: 32px;
		align-items: center;
		justify-content: center;
		padding: 6px 10px;
		border: 1px solid var(--thread-border);
		border-radius: 8px;
		background: #ffffff;
		color: #475467;
		font: inherit;
		font-size: 12px;
		font-weight: 700;
		cursor: pointer;
	}

	.thread-action:hover {
		border-color: #b2c5f4;
		background: #f5f8ff;
		color: var(--thread-primary-dark);
	}

	.thread-action.is-danger {
		color: var(--thread-danger);
	}

	.thread-action.is-disabled {
		color: #98a2b3;
		cursor: not-allowed;
		opacity: 0.72;
	}

	.thread-reply-content {
		min-height: 96px;
		padding: 22px 24px;
		color: #344054;
		font-size: 15px;
		line-height: 1.75;
		overflow-wrap: anywhere;
		white-space: normal;
	}

	.thread-blocked-notice {
		margin-bottom: 14px;
		padding: 12px 14px;
		border-left: 4px solid #f97316;
		border-radius: 0 8px 8px 0;
		background: #fff7ed;
		color: #9a3412;
		font-size: 13px;
		font-weight: 700;
	}

	.thread-reply-footer {
		display: flex;
		justify-content: flex-end;
		padding: 0 18px 14px;
	}

	.thread-floor-number {
		color: #98a2b3;
		font-size: 12px;
		font-weight: 800;
	}

	.thread-empty {
		padding: 48px 20px;
		border-radius: 16px;
		color: var(--thread-muted);
		text-align: center;
	}

	.thread-pagination {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		margin: 22px 0;
	}

	.thread-page-button {
		min-height: 36px;
		padding: 8px 12px;
		border: 1px solid var(--thread-border);
		border-radius: 9px;
		background: #ffffff;
		color: #98a2b3;
		font-size: 13px;
		font-weight: 700;
		cursor: not-allowed;
	}

	.thread-composer-card {
		margin-top: 24px;
		padding: 22px;
		border-radius: 16px;
	}

	.thread-composer-heading {
		margin: 0 0 6px;
		color: var(--thread-text);
		font-size: 19px;
		font-weight: 800;
	}

	.thread-composer-help {
		margin: 0 0 16px;
		color: var(--thread-muted);
		font-size: 13px;
		line-height: 1.6;
	}

	.thread-reply-textarea {
		width: 100%;
		min-height: 220px;
		padding: 14px;
		border: 1px solid var(--thread-border);
		border-radius: 10px;
		font: inherit;
		resize: vertical;
	}

	.thread-composer-actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 14px;
	}

	.thread-submit-button {
		min-width: 104px;
		padding: 0 20px;
	}

	.oj-thread-page .tox-tinymce {
		border: 1px solid var(--thread-border);
		border-radius: 12px;
		box-shadow: none;
	}

	.oj-thread-page .tox .tox-toolbar-overlord,
	.oj-thread-page .tox .tox-toolbar__primary {
		background-color: #f8fafc;
	}

	@media (max-width: 760px) {
		.oj-thread-page {
			width: min(100% - 20px, 1120px);
			margin-top: 16px;
		}

		.thread-toolbar,
		.thread-title-card,
		.thread-reply-header {
			align-items: stretch;
			flex-direction: column;
		}

		.thread-primary-button {
			width: 100%;
		}

		.thread-title-card {
			padding: 20px;
		}

		.thread-admin-tools,
		.thread-admin-tools summary {
			width: 100%;
		}

		.thread-admin-menu {
			position: static;
			width: 100%;
			margin-top: 8px;
			box-shadow: none;
		}

		.thread-actions {
			justify-content: flex-start;
		}

		.thread-reply-content {
			padding: 18px;
		}

		.thread-composer-card {
			padding: 18px 14px;
		}
	}

/* POST 방식의 게시판 관리 버튼 */
.oj-thread-page .thread-action-form {
    display: inline-flex;
    margin: 0;
}
.oj-thread-page .thread-action-form button {
    font: inherit;
    cursor: pointer;
}
.oj-thread-page .thread-admin-menu .thread-action-form {
    display: block;
}
.oj-thread-page .thread-admin-menu .thread-action-form button {
    width: 100%;
    padding: 10px 12px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: inherit;
    text-align: left;
}
.oj-thread-page .thread-admin-menu .thread-action-form button:hover {
    background: #f1f5f9;
}
.oj-thread-page .thread-action-form button.is-danger {
    color: #dc2626;
}
.oj-thread-page .thread-action-form button:focus-visible {
    outline: 3px solid #60a5fa;
    outline-offset: 2px;
}

</style>

<main class="oj-thread-page">
	<div class="thread-toolbar">
		<nav class="thread-breadcrumb" aria-label="게시판 위치">
			<a href="<?php echo htmlspecialchars($discuss_url, ENT_QUOTES, 'UTF-8'); ?>">
				<?php echo $topic_pid > 0 ? 'Problem ' . htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') : 'MainBoard'; ?>
			</a>
		</nav>
		<a class="thread-primary-button" href="<?php echo htmlspecialchars($newpost_url, ENT_QUOTES, 'UTF-8'); ?>">+ 새 글 작성</a>
	</div>

	<section class="thread-title-card">
		<div class="thread-title-copy">
			<div class="thread-eyebrow">Discussion</div>
			<h1 class="thread-title">
    <?php
    echo nl2br(
        htmlentities(
            $topic_title,
            ENT_QUOTES,
            'UTF-8'
        )
    );
    ?>

    <?php
    if ($topic_status === 1) {
    ?>
        <span class="thread-lock-badge">
            답글 잠금
        </span>
    <?php
    }
    ?>
</h1>
		</div>

		                <?php if ($isadmin || $is_topic_owner): ?>
                    <details class="thread-admin-tools">
                        <summary>관리 도구</summary>
                        <div class="thread-admin-menu">
                            <?php
                            if ($isadmin) {
                            if ($topic_level === 0) {
                                $thread_render_action("thread", "sticky", "전체 게시판 고정", null, 3);
                                $thread_render_action("thread", "sticky", "관련 게시판 고정", null, 2);
                                $thread_render_action("thread", "sticky", "현재 게시판 고정", null, 1);
                            } else {
                                $thread_render_action("thread", "sticky", "고정 해제", null, 0);
                            }

                            if ($topic_status !== 1) {
                                $thread_render_action("thread", "lock", "답글 잠금");
                            } else {
                                $thread_render_action("thread", "resume", "잠금 해제");
                            }

                            }

                            $thread_render_action(
                                "thread",
                                "delete",
                                $isadmin ? "게시물 삭제" : "내 게시물 삭제",
                                null,
                                null,
                                true
                            );
                            ?>
                        </div>
                    </details>
                <?php endif; ?>
        </section>

<?php
	$i = 0;
	?>

	<section class="thread-reply-list" aria-label="답글 목록">
		<?php if ($reply_rows_cnt === 0) { ?>
			<div class="thread-empty">아직 작성된 답글이 없습니다.</div>
		<?php } ?>

		<?php foreach ($reply_result as $row) {
			$reply_admin_url = "threadadmin.php?target=reply&amp;rid=" . intval($row['rid']) . "&amp;tid={$tid}&amp;action=";
			$isuser = $is_logged_in
				? strtolower($row['author_id']) == strtolower($_SESSION[$OJ_NAME . '_' . 'user_id'])
				: false;
			$author = htmlspecialchars($row['author_id'], ENT_QUOTES, 'UTF-8');
			$author_initial = htmlspecialchars(strtoupper(substr($row['author_id'], 0, 1)), ENT_QUOTES, 'UTF-8');
			$reply_status = intval($row['status']);
                        $is_topic_body =
                                intval($row['is_topic_body']) === 1;
		?>
			<article
				class="thread-reply-card<?php echo $reply_status == 0 ? '' : ' is-blocked'; ?>"
				data-author="<?php echo $author; ?>">
				<header class="thread-reply-header">
					<div class="thread-author">
						<span class="thread-avatar" aria-hidden="true"><?php echo $author_initial; ?></span>
						<div class="thread-author-info">
							<a class="thread-author-name" href="../userinfo.php?user=<?php echo rawurlencode($row['author_id']); ?>"><?php echo $author; ?></a>
							<time class="thread-time"><?php echo htmlspecialchars($row['time'], ENT_QUOTES, 'UTF-8'); ?></time>

                                                        <?php if ($is_topic_body) { ?>
                                                                <span class="thread-reply-status-badge">
                                                                        본문
                                                                </span>
                                                        <?php } ?>

                                                        <?php
                                                        if ($reply_status === 1) {
                                                        ?>
                                                                <span class="thread-reply-status-badge">
                                                                        차단됨
                                                                </span>
                                                        <?php
                                                        }
                                                        ?>
						</div>
					</div>

					<div class="thread-actions">
    <?php
    if ($isadmin && !$is_topic_body) {
        if ($reply_status === 0) {
            $thread_render_action("reply", "disable", "차단", (int)$row["rid"]);
        } else {
            $thread_render_action("reply", "resume", "차단 해제", (int)$row["rid"]);
        }
    }
    ?>

    <?php if ($is_logged_in && $topic_status === 0 && $reply_status === 0): ?>
        <button
            class="thread-action"
            type="button"
            onclick="reply(<?php echo (int)$row['rid']; ?>);">
            답글
        </button>
    <?php endif; ?>

    <span class="thread-action is-disabled" title="준비 중" aria-disabled="true">추천</span>
    <span class="thread-action is-disabled" title="준비 중" aria-disabled="true">수정</span>

    <?php
    if (!$is_topic_body && ($isuser || $isadmin)) {
        $thread_render_action("reply", "delete", "삭제", (int)$row["rid"], null, true);
    }
    ?>
</div>
				</header>

				<div id="post<?php echo intval($row['rid']); ?>" class="thread-reply-content">
					<?php
					if ($reply_status == 0) {
						echo strip_tags(htmlspecialchars_decode(nl2br(htmlentities($row['content'], ENT_QUOTES, 'UTF-8'))));
					} else {
						if (!$isuser || $isadmin) {
							echo '<div class="thread-blocked-notice">관리자에 의해 차단된 답글입니다.</div>';
						}
						if ($isuser || $isadmin) {
							echo strip_tags(htmlspecialchars_decode(nl2br(htmlentities($row['content'], ENT_QUOTES, 'UTF-8'))));
						}
					}
					?>
				</div>

				<footer class="thread-reply-footer">
					<span class="thread-floor-number">#<?php echo $i + 1; ?></span>
				</footer>
			</article>
		<?php
			$i++;
		} ?>
	</section>

	<nav class="thread-pagination" aria-label="게시물 페이지">
		<span class="thread-page-button" aria-disabled="true">첫 페이지</span>
		<span class="thread-page-button" aria-disabled="true">이전</span>
		<span class="thread-page-button" aria-disabled="true">다음</span>
	</nav>

	<?php if ($is_logged_in) { ?>
		<?php if ($topic_status === 0) { ?>
                <section id="replyComposer" class="thread-composer-card">
			<h2 class="thread-composer-heading">답글 작성</h2>
			<p class="thread-composer-help">내용을 입력한 뒤 등록 버튼을 눌러 주세요.</p>

			<form action="post.php?action=reply" method="post">
				<input type="hidden" name="tid" value="<?php echo $tid; ?>">

                                <?php
                                require(
                                    dirname(__FILE__) .
                                    "/../../include/set_post_key.php"
                                );
                                ?>
				<textarea
					id="replyContent"
					class="thread-reply-textarea"
					name="content"
					aria-label="답글 내용"></textarea>
				<div class="thread-composer-actions">
					<button class="thread-submit-button" type="submit">답글 등록</button>
				</div>
			</form>
		</section>

                <?php } else { ?>

                <section class="thread-composer-card thread-locked-notice">
                        <strong>
                                현재 답글 작성이 잠겨 있습니다.
                        </strong>

                        <p>
                                기존 답글은 확인할 수 있지만
                                새로운 답글은 등록할 수 없습니다.
                        </p>
                </section>

                <?php } ?>
	<?php } ?>
</main>

<script>
	function escapeReplyHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function reply(rid) {
		var post = document.getElementById('post' + rid);
		var textarea = document.getElementById('replyContent');
		var composer = document.getElementById('replyComposer');

		if (!post || !textarea || !composer) {
			return;
		}

		var card = post.closest('.thread-reply-card');
		var author = card ? card.getAttribute('data-author') : '';
		var origin = post.innerText.trim();
		var plainQuote = author + '님의 글:\n' + origin + '\n----------------------\n';
		var quoteHtml =
			'<blockquote><p><strong>' + escapeReplyHtml(author) +
			'님의 글:</strong></p><p>' +
			escapeReplyHtml(origin).replace(/\r?\n/g, '<br>') +
			'</p></blockquote><p><br></p>';
		var editor = window.tinymce ? tinymce.get('replyContent') : null;

		if (editor) {
			editor.setContent(quoteHtml);
			editor.focus();
			editor.selection.select(editor.getBody(), true);
			editor.selection.collapse(false);
		} else {
			textarea.value = plainQuote;
			textarea.focus();
		}

		composer.scrollIntoView({
			behavior: 'smooth',
			block: 'center'
		});
	}

	if (window.tinymce && document.getElementById('replyContent')) {
		tinymce.init({
			selector: '#replyContent',
			license_key: 'gpl',
			cache_suffix: '?v=8.9.0',
			height: 260,
			menubar: false,
			branding: false,
			promotion: false,
			plugins: 'autolink link lists code fullscreen wordcount',
			toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link | code fullscreen',
			toolbar_mode: 'sliding',
			browser_spellcheck: true,
			content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", sans-serif; font-size: 15px; line-height: 1.7; color: #344054; }'
		});
	}
</script>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
