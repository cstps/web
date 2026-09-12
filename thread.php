<?php
require_once("discuss_func.inc.php");

echo "<title>1024 Online Judge WebBoard</title>";

$tid = intval($_REQUEST['tid']);
$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;

$sql = "SELECT t.`title`, `cid`, `pid`, `status`, `top_level`
              FROM `topic` t
         LEFT JOIN contest_problem cp ON cp.problem_id = t.pid
             WHERE `tid` = ? AND `status` <= 1";
$result = pdo_query($sql, $tid);
$rows_cnt = count($result);
$row = $result[0];

$topic_title = $row['title'];
$topic_cid = intval($row['cid']);
$topic_pid = intval($row['pid']);
$topic_status = intval($row['status']);
$topic_level = intval($row['top_level']);

if ($topic_cid > 0) {
	$cid = $topic_cid;
}

if ($topic_pid > 0 && $topic_cid > 0) {
	$problem_num = pdo_query(
		"SELECT num FROM contest_problem WHERE problem_id = ? AND contest_id = ?",
		$topic_pid,
		$topic_cid
	)[0][0];
	$pid = $PID[$problem_num];
} else {
	$pid = $topic_pid;
}

$isadmin = isset($_SESSION[$OJ_NAME . '_' . 'administrator']);
$is_logged_in = isset($_SESSION[$OJ_NAME . '_' . 'user_id']);

$discuss_params = array();
if ($topic_pid > 0) {
	$discuss_params['pid'] = $topic_pid;
}
if ($topic_cid > 0) {
	$discuss_params['cid'] = $topic_cid;
}
$discuss_url = 'discuss.php';
if (!empty($discuss_params)) {
	$discuss_url .= '?' . http_build_query($discuss_params);
}

$newpost_params = array();
if ($topic_cid > 0) {
	$newpost_params['cid'] = $topic_cid;
}
if ($topic_pid > 0) {
	$newpost_params['pid'] = $topic_pid;
}
$newpost_url = 'newpost.php';
if (!empty($newpost_params)) {
	$newpost_url .= '?' . http_build_query($newpost_params);
}
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
			<h1 class="thread-title"><?php echo nl2br(htmlentities($topic_title, ENT_QUOTES, 'UTF-8')); ?></h1>
		</div>

		<?php if ($isadmin) {
			$adminurl = "threadadmin.php?target=thread&amp;tid={$tid}&amp;action=";
		?>
			<details class="thread-admin-tools">
				<summary>관리 도구</summary>
				<div class="thread-admin-menu">
					<?php if ($topic_level == 0) { ?>
						<a href="<?php echo $adminurl; ?>sticky&amp;level=3">Level Top</a>
						<a href="<?php echo $adminurl; ?>sticky&amp;level=2">Level Mid</a>
						<a href="<?php echo $adminurl; ?>sticky&amp;level=1">Level Low</a>
					<?php } else { ?>
						<a href="<?php echo $adminurl; ?>sticky&amp;level=0">Standard</a>
					<?php } ?>

					<?php if ($topic_status != 1) { ?>
						<a href="<?php echo $adminurl; ?>lock">Lock</a>
					<?php } else { ?>
						<a href="<?php echo $adminurl; ?>resume">Resume</a>
					<?php } ?>

					<a class="is-danger" href="<?php echo $adminurl; ?>delete" onclick="return confirm('이 게시물을 삭제하시겠습니까?');">Delete</a>
				</div>
			</details>
		<?php } ?>
	</section>

	<?php
	$sql = "SELECT `rid`, `author_id`, `time`, `content`, `status`
                  FROM `reply`
                 WHERE `topic_id` = ? AND `status` <= 1
              ORDER BY `rid`
                 LIMIT 30";
	$result = pdo_query($sql, $tid);
	$rows_cnt = count($result);
	$i = 0;
	?>

	<section class="thread-reply-list" aria-label="답글 목록">
		<?php if ($rows_cnt === 0) { ?>
			<div class="thread-empty">아직 작성된 답글이 없습니다.</div>
		<?php } ?>

		<?php foreach ($result as $row) {
			$reply_admin_url = "threadadmin.php?target=reply&amp;rid=" . intval($row['rid']) . "&amp;tid={$tid}&amp;action=";
			$isuser = $is_logged_in
				? strtolower($row['author_id']) == strtolower($_SESSION[$OJ_NAME . '_' . 'user_id'])
				: false;
			$author = htmlspecialchars($row['author_id'], ENT_QUOTES, 'UTF-8');
			$author_initial = htmlspecialchars(strtoupper(substr($row['author_id'], 0, 1)), ENT_QUOTES, 'UTF-8');
			$reply_status = intval($row['status']);
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
						</div>
					</div>

					<div class="thread-actions">
						<?php if ($isadmin) { ?>
							<?php if ($reply_status == 0) { ?>
								<a class="thread-action" href="<?php echo $reply_admin_url; ?>disable">Disable</a>
							<?php } else { ?>
								<a class="thread-action" href="<?php echo $reply_admin_url; ?>resume">Resume</a>
							<?php } ?>
							<button class="thread-action" type="button" onclick="reply(<?php echo intval($row['rid']); ?>);">답글</button>
						<?php } ?>

						<span class="thread-action is-disabled" title="준비 중" aria-disabled="true">추천</span>
						<span class="thread-action is-disabled" title="준비 중" aria-disabled="true">수정</span>

						<?php if ($isuser || $isadmin) { ?>
							<a class="thread-action is-danger" href="<?php echo $reply_admin_url; ?>delete" onclick="return confirm('이 답글을 삭제하시겠습니까?');">삭제</a>
						<?php } ?>
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
		<section id="replyComposer" class="thread-composer-card">
			<h2 class="thread-composer-heading">답글 작성</h2>
			<p class="thread-composer-help">내용을 입력한 뒤 등록 버튼을 눌러 주세요.</p>

			<form action="post.php?action=reply" method="post">
				<input type="hidden" name="tid" value="<?php echo $tid; ?>">
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

<?php require_once("template/$OJ_TEMPLATE/discuss.php"); ?>