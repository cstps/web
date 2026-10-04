<?php
$show_title = "$MSG_BBS - $OJ_NAME";

require_once(
    dirname(__FILE__) .
    "/../../lang/$OJ_LANG.php"
);

include("template/$OJ_TEMPLATE/header.php");
include("include/bbcode.php");
?>

<style>
	.oj-discuss-page {
		--board-primary: #2563eb;
		--board-primary-dark: #1d4ed8;
		--board-text: #172033;
		--board-muted: #667085;
		--board-border: #e4e7ec;
		--board-surface: #ffffff;
		--board-soft: #f7f9fc;
		--board-danger: #dc2626;
		width: min(1160px, calc(100% - 32px));
		margin: 28px auto 56px;
		color: var(--board-text);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
			"Noto Sans KR", "Apple SD Gothic Neo", Arial, sans-serif;
		text-align: left;
	}

	.oj-discuss-page,
	.oj-discuss-page * {
		box-sizing: border-box;
	}

	.oj-discuss-page a {
		color: inherit;
		text-decoration: none;
	}

	.discuss-hero,
	.discuss-topic-panel,
	.discuss-context-error {
		border: 1px solid var(--board-border);
		background: var(--board-surface);
		box-shadow: 0 8px 28px rgba(16, 24, 40, 0.06);
	}

	.discuss-hero {
		position: relative;
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
		gap: 28px;
		overflow: hidden;
		margin-bottom: 18px;
		padding: 28px 30px;
		border-radius: 18px;
	}

	.discuss-hero::after {
		position: absolute;
		top: -90px;
		right: -70px;
		width: 230px;
		height: 230px;
		border-radius: 50%;
		background: radial-gradient(circle, rgba(37, 99, 235, 0.13), rgba(37, 99, 235, 0));
		content: '';
		pointer-events: none;
	}

	.discuss-hero-copy {
		position: relative;
		z-index: 1;
		min-width: 0;
	}

	.discuss-breadcrumb {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 7px;
		margin-bottom: 18px;
		color: var(--board-muted);
		font-size: 13px;
		font-weight: 650;
	}

	.discuss-breadcrumb a {
		color: var(--board-primary);
	}

	.discuss-breadcrumb a:hover {
		color: var(--board-primary-dark);
		text-decoration: underline;
	}

	.discuss-breadcrumb-separator {
		color: #c2c8d0;
	}

	.discuss-eyebrow {
		margin: 0 0 7px;
		color: var(--board-primary);
		font-size: 12px;
		font-weight: 800;
		letter-spacing: 0.09em;
		text-transform: uppercase;
	}

	.discuss-title {
		margin: 0;
		color: var(--board-text);
		font-size: clamp(27px, 4vw, 39px);
		font-weight: 850;
		line-height: 1.2;
		letter-spacing: -0.035em;
	}

	.discuss-description {
		max-width: 650px;
		margin: 10px 0 0;
		color: var(--board-muted);
		font-size: 14px;
		line-height: 1.7;
	}

	.discuss-hero-actions {
		position: relative;
		z-index: 1;
		display: flex;
		flex: 0 0 auto;
		flex-wrap: wrap;
		justify-content: flex-end;
		gap: 9px;
	}

	.discuss-button {
		display: inline-flex;
		min-height: 42px;
		align-items: center;
		justify-content: center;
		padding: 0 17px;
		border: 1px solid var(--board-border);
		border-radius: 10px;
		background: #ffffff;
		color: #344054;
		font-size: 14px;
		font-weight: 750;
		line-height: 1;
		transition: border-color 0.18s ease, background-color 0.18s ease,
			box-shadow 0.18s ease, transform 0.18s ease;
	}

	.discuss-button:hover {
		border-color: #b4c6f0;
		background: #f7f9ff;
		color: var(--board-primary-dark);
		transform: translateY(-1px);
	}

	.discuss-button.is-primary {
		border-color: var(--board-primary);
		background: var(--board-primary);
		color: #ffffff;
	}

	.discuss-button.is-primary:hover {
		border-color: var(--board-primary-dark);
		background: var(--board-primary-dark);
		color: #ffffff;
		box-shadow: 0 7px 18px rgba(37, 99, 235, 0.24);
	}

	.discuss-context-error {
		margin-bottom: 18px;
		padding: 14px 16px;
		border-color: #fed7aa;
		border-radius: 12px;
		background: #fff7ed;
		color: #9a3412;
		font-size: 13px;
		font-weight: 700;
		line-height: 1.6;
	}

	.discuss-topic-panel {
		overflow: hidden;
		border-radius: 18px;
	}

	.discuss-panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 18px;
		padding: 18px 22px;
		border-bottom: 1px solid var(--board-border);
		background: #fbfcfe;
	}

	.discuss-panel-title-wrap {
		min-width: 0;
	}

	.discuss-panel-title {
		margin: 0;
		color: #1d2939;
		font-size: 18px;
		font-weight: 800;
		letter-spacing: -0.02em;
	}

	.discuss-panel-caption {
		margin: 4px 0 0;
		color: var(--board-muted);
		font-size: 12px;
		line-height: 1.5;
	}

	.discuss-result-count {
		flex: 0 0 auto;
		padding: 7px 11px;
		border-radius: 999px;
		background: #eef4ff;
		color: var(--board-primary-dark);
		font-size: 12px;
		font-weight: 800;
	}

	.discuss-topic-list {
		display: grid;
	}

	.discuss-topic-card {
		display: grid;
		grid-template-columns: 54px minmax(0, 1fr) 92px;
		gap: 17px;
		align-items: center;
		min-height: 116px;
		padding: 18px 22px;
		border-bottom: 1px solid #edf0f4;
		background: #ffffff;
		transition: background-color 0.18s ease, box-shadow 0.18s ease;
	}

	.discuss-topic-card:last-child {
		border-bottom: 0;
	}

	.discuss-topic-card:hover {
		position: relative;
		z-index: 1;
		background: #fbfdff;
		box-shadow: inset 4px 0 0 var(--board-primary);
	}

	.discuss-topic-leading {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 9px;
	}

	.discuss-topic-number {
		display: inline-flex;
		width: 42px;
		height: 42px;
		align-items: center;
		justify-content: center;
		border-radius: 12px;
		background: var(--board-soft);
		color: #475467;
		font-size: 12px;
		font-weight: 800;
	}

	.discuss-select {
		width: 16px;
		height: 16px;
		accent-color: var(--board-primary);
		cursor: pointer;
	}

	.discuss-topic-main {
		min-width: 0;
	}

	.discuss-topic-meta,
	.discuss-topic-dates {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 7px 12px;
		color: var(--board-muted);
		font-size: 12px;
		line-height: 1.5;
	}

	.discuss-topic-meta {
		margin-bottom: 7px;
	}

	.discuss-meta-link {
		color: var(--board-primary) !important;
		font-weight: 750;
	}

	.discuss-meta-link:hover {
		text-decoration: underline;
	}

	.discuss-status {
		display: inline-flex;
		min-height: 23px;
		align-items: center;
		padding: 3px 8px;
		border-radius: 999px;
		font-size: 11px;
		font-weight: 850;
		letter-spacing: 0.02em;
	}

	.discuss-status.is-top {
		background: #eff4ff;
		color: #3538cd;
	}

	.discuss-status.is-lock {
		background: #f2f4f7;
		color: #475467;
	}

	.discuss-status.is-hot {
		background: #fff1f3;
		color: #c01048;
	}

	.discuss-topic-title {
		margin: 0 0 9px;
		overflow-wrap: anywhere;
		color: #1d2939;
		font-size: 16px;
		font-weight: 800;
		line-height: 1.5;
		letter-spacing: -0.015em;
	}

	.discuss-topic-title a:hover {
		color: var(--board-primary);
	}

	.discuss-topic-dates span {
		display: inline-flex;
		align-items: center;
		gap: 5px;
	}

	.discuss-reply-stat {
		display: flex;
		min-height: 70px;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		border-left: 1px solid #edf0f4;
		color: var(--board-muted);
	}

	.discuss-reply-stat strong {
		color: #1d2939;
		font-size: 21px;
		font-weight: 850;
		line-height: 1.1;
	}

	.discuss-reply-stat span {
		margin-top: 5px;
		font-size: 11px;
		font-weight: 700;
	}

	.discuss-empty {
		padding: 64px 20px;
		color: var(--board-muted);
		text-align: center;
	}

	.discuss-empty-icon {
		display: inline-flex;
		width: 52px;
		height: 52px;
		align-items: center;
		justify-content: center;
		margin-bottom: 13px;
		border-radius: 16px;
		background: var(--board-soft);
		color: #98a2b3;
		font-size: 20px;
		font-weight: 800;
	}

	.discuss-empty strong {
		display: block;
		margin-bottom: 5px;
		color: #344054;
		font-size: 15px;
	}

	.discuss-empty span {
		font-size: 13px;
	}

	.discuss-panel-footer {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 48px;
		padding: 12px 18px;
		border-top: 1px solid var(--board-border);
		background: #fbfcfe;
		color: var(--board-muted);
		font-size: 12px;
		font-weight: 650;
	}

	@media (max-width: 760px) {
		.oj-discuss-page {
			width: min(100% - 20px, 1160px);
			margin-top: 16px;
		}

		.discuss-hero {
			align-items: stretch;
			flex-direction: column;
			padding: 22px 20px;
		}

		.discuss-hero-actions {
			justify-content: stretch;
		}

		.discuss-button {
			flex: 1 1 140px;
		}

		.discuss-panel-header {
			align-items: flex-start;
		}

		.discuss-topic-card {
			grid-template-columns: 44px minmax(0, 1fr);
			gap: 12px;
			padding: 17px 15px;
		}

		.discuss-topic-number {
			width: 38px;
			height: 38px;
			border-radius: 10px;
		}

		.discuss-reply-stat {
			grid-column: 2;
			min-height: auto;
			flex-direction: row;
			justify-content: flex-start;
			gap: 5px;
			border-left: 0;
		}

		.discuss-reply-stat strong {
			font-size: 13px;
		}

		.discuss-reply-stat span {
			margin-top: 0;
			font-size: 12px;
		}
	}

	@media (max-width: 460px) {

		.discuss-hero-actions,
		.discuss-panel-header {
			flex-direction: column;
		}

		.discuss-result-count {
			align-self: flex-start;
		}

		.discuss-topic-dates {
			align-items: flex-start;
			flex-direction: column;
			gap: 3px;
		}
	}
</style>

<main class="oj-discuss-page">
	<header class="discuss-hero">
		<div class="discuss-hero-copy">
			<nav class="discuss-breadcrumb" aria-label="게시판 위치">
				<a href="discuss.php">전체 게시판</a>

				<?php if ($pid > 0) {
                                        $problem_query = array('pid' => $pid);
                                ?>
					<span class="discuss-breadcrumb-separator" aria-hidden="true">/</span>
					<a href="discuss.php?<?php echo htmlspecialchars(http_build_query($problem_query), ENT_QUOTES, 'UTF-8'); ?>">
						Problem <?php echo htmlspecialchars($current_problem_label, ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php } ?>
			</nav>

			<p class="discuss-eyebrow">1024 Community</p>
			<h1 class="discuss-title"><?php echo htmlspecialchars($board_title, ENT_QUOTES, 'UTF-8'); ?></h1>
			<p class="discuss-description"><?php echo htmlspecialchars($board_description, ENT_QUOTES, 'UTF-8'); ?></p>
		</div>

		<?php if ($prob_exist) { ?>
			<div class="discuss-hero-actions">
				<?php if ($pid > 0) { ?>
					<a class="discuss-button" href="../problem.php?id=<?php echo $pid; ?>">문제 보기</a>
				<?php } ?>
				<a class="discuss-button is-primary" href="<?php echo htmlspecialchars($newpost_url, ENT_QUOTES, 'UTF-8'); ?>">+ 새 글 작성</a>
			</div>
		<?php } ?>
	</header>

	<?php if (!$prob_exist) { ?>
		<div class="discuss-context-error" role="alert">
			요청한 문제를 찾을 수 없어 이 위치에서는 새 글을 작성할 수 없습니다.
		</div>
	<?php } ?>

	<section class="discuss-topic-panel" aria-labelledby="discuss-topic-heading">
		<header class="discuss-panel-header">
			<div class="discuss-panel-title-wrap">
				<h2 id="discuss-topic-heading" class="discuss-panel-title">게시글</h2>
				<p class="discuss-panel-caption">고정글을 먼저 표시하고, 이후 최근 답글 순으로 최대 30개를 보여줍니다.</p>
			</div>
			<span class="discuss-result-count"><?php echo $rows_cnt; ?>개</span>
		</header>

		<div class="discuss-topic-list">
			<?php if ($rows_cnt === 0) { ?>
				<div class="discuss-empty">
					<span class="discuss-empty-icon" aria-hidden="true">···</span>
					<strong>아직 게시글이 없습니다.</strong>
					<span>첫 번째 질문이나 이야기를 남겨 보세요.</span>
				</div>
			<?php } ?>

			<?php
			$i = 0;
			foreach ($result as $row) {
				$topic_pid = intval($row['pid']);
				$top_level = intval($row['top_level']);
				$topic_status = intval($row['status']);
				$reply_count =
                                    max(
                                            0,
                                            intval($row['reply_count'])
                                    );

				$status_badges = array();

                                if ($top_level !== 0) {
                                        if ($top_level !== 1 || $topic_pid === $pid) {
                                                if ($top_level === 3) {
                                                        $top_label = '전체 고정';
                                                } elseif ($top_level === 2) {
                                                        $top_label = '관련 고정';
                                                } else {
                                                        $top_label = '현재 고정';
                                                }

                                                $status_badges[] = array(
                                                        'label' => $top_label,
                                                        'class' => 'is-top'
                                                );
                                        }
                                }

                                if ($topic_status === 1) {
                                        $status_badges[] = array(
                                                'label' => '답글 잠금',
                                                'class' => 'is-lock'
                                        );
                                }

                                if ($reply_count > 20) {
                                        $status_badges[] = array(
                                                'label' => '인기',
                                                'class' => 'is-hot'
                                        );
                                }

                                $problem_label = '';
				$problem_url = '';

				if ($topic_pid > 0) {
				        $problem_label =
				                (string)$topic_pid;

				        $problem_url =
				                'discuss.php?pid=' .
				                $topic_pid;
				}

				$thread_params =
                                        array(
                                                'tid' =>
                                                        intval($row['tid'])
                                        );

                                $thread_url =
                                        'thread.php?' .
                                        http_build_query($thread_params);

				$author_id = (string)$row['author_id'];
				$posttime = $row['posttime'] ? (string)$row['posttime'] : '-';
				$lastupdate = $row['lastupdate'] ? (string)$row['lastupdate'] : '-';
			?>
				<article class="discuss-topic-card">
					<div class="discuss-topic-leading">
						<span class="discuss-topic-number">#<?php echo $i + 1; ?></span>
						<?php if ($isadmin) { ?>
							<input
								class="discuss-select"
								type="checkbox"
								aria-label="<?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?> 선택">
						<?php } ?>
					</div>

					<div class="discuss-topic-main">
						<div class="discuss-topic-meta">
							<?php foreach ($status_badges as $status_badge) { ?>
                                                                <span class="discuss-status <?php echo htmlspecialchars($status_badge['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <?php echo htmlspecialchars($status_badge['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                        <?php } ?>

							<?php if ($problem_label !== '') { ?>
								<a class="discuss-meta-link" href="<?php echo htmlspecialchars($problem_url, ENT_QUOTES, 'UTF-8'); ?>">
									Problem <?php echo htmlspecialchars($problem_label, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							<?php } ?>

							<a class="discuss-meta-link" href="../userinfo.php?user=<?php echo rawurlencode($author_id); ?>">
								<?php echo htmlspecialchars($author_id, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</div>

						<h3 class="discuss-topic-title">
							<a href="<?php echo htmlspecialchars($thread_url, ENT_QUOTES, 'UTF-8'); ?>">
								<?php echo htmlentities($row['title'], ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</h3>

						<div class="discuss-topic-dates">
							<span>게시 <?php echo htmlspecialchars($posttime, ENT_QUOTES, 'UTF-8'); ?></span>
							<span>최근 답글 <?php echo htmlspecialchars($lastupdate, ENT_QUOTES, 'UTF-8'); ?></span>
						</div>
					</div>

					<div class="discuss-reply-stat" aria-label="답글 <?php echo $reply_count; ?>개">
						<strong><?php echo $reply_count; ?></strong>
						<span>답글</span>
					</div>
				</article>
			<?php
				$i++;
			}
			?>
		</div>

		<footer class="discuss-panel-footer">
			최근 업데이트 순 · 최대 30개 표시
		</footer>
	</section>
</main>

<?php
include("template/$OJ_TEMPLATE/footer.php");
?>
