<?php
$show_title = $view_title;
include('template/' . $OJ_TEMPLATE . '/header.php');
?>
<div class="ui main container" style="margin-top:2rem;margin-bottom:3rem;">
    <section class="ui segment">
        <h2 class="ui header"><?= drawproblem_escape(
            $draw_reader_problem['title']
        ) ?></h2>

        <p>
            문제 <?= drawproblem_escape($draw_reader_problem['problem_id']) ?>
            · 버전 <?= drawproblem_escape($draw_reader_problem['version_no']) ?>
            · <?= $draw_reader_problem['source_language'] === 'python'
                ? 'Python' : 'JavaScript · p5.js' ?>
            · <?= drawproblem_escape($draw_reader_problem['canvas_width']) ?>
            ×<?= drawproblem_escape($draw_reader_problem['canvas_height']) ?>
        </p>

        <?php if (!$draw_reader_problem['reader_is_public']): ?>
            <div class="ui warning message">
                교사용 미리보기입니다. 학생에게 공개된 문제가 아닙니다.
            </div>
        <?php endif; ?>

        <a class="ui basic button" href="/drawproblemset.php">그림 문제 목록</a>

        <?php if ($draw_reader_problem['reader_can_edit']): ?>
            <a class="ui basic button"
               href="/admin/simulation_problem_edit.php?problem_id=<?= drawproblem_escape(
                   $draw_reader_problem['problem_id']
               ) ?>">문제 수정</a>
        <?php endif; ?>
    </section>

    <div class="ui stackable two column grid">
        <div class="column">
            <section class="ui segment">
                <h3 class="ui header">문제 설명</h3>
                <?php if ($draw_reader_description !== ''): ?>
                    <div class="drawproblem-description"><?= $draw_reader_description ?></div>
                <?php else: ?>
                    <p>등록된 문제 설명이 없습니다.</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="column">
            <?php if ($draw_reader_problem['simulation_mode'] === 'drawing'): ?>
                <section class="ui segment">
                    <h3 class="ui header">목표 그림</h3>

                    <?php if ($draw_reader_problem['image_status'] === 'ready'): ?>
                        <?php
                        $draw_reader_image_url = '/drawproblem_image.php?' .
                            http_build_query(array(
                                'problem_id' => $draw_reader_problem['problem_id'],
                                'version_id' => $draw_reader_problem['version_id'],
                            ));
                        ?>
                        <a href="<?= drawproblem_escape($draw_reader_image_url) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <img src="<?= drawproblem_escape($draw_reader_image_url) ?>"
                                 alt="문제의 목표 그림"
                                 width="400" height="400"
                                 style="display:block;max-width:100%;height:auto;border:1px solid #cbd5e1;">
                        </a>
                    <?php else: ?>
                        <p>목표 그림을 준비 중이거나 확인이 필요합니다.</p>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="ui segment">
                    <h3 class="ui header">자유 실습</h3>
                    <p>문제 설명에 따라 자유롭게 코드를 작성하는 활동입니다.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($draw_reader_logged_in): ?>
    <section class="ui segment">
        <h3 class="ui header">
            <label for="drawproblem-source">기본 코드</label>
        </h3>
        <div id="drawproblem-ace-editor" hidden
             data-language="<?= drawproblem_escape(
                 $draw_reader_problem['source_language']
             ) ?>"
             aria-label="문제 풀이 코드 편집기"
             style="width:100%;height:300px;min-height:160px;resize:vertical;overflow:hidden;border:1px solid #cbd5e1;"></div>
        <p id="drawproblem-editor-message" role="status" hidden></p>
        <textarea id="drawproblem-source" rows="15"
                  <?= (string)$draw_reader_problem['allow_edit'] === '1'
                      ? '' : 'readonly' ?>
                  spellcheck="false"
                  style="width:100%;box-sizing:border-box;font-family:monospace;tab-size:4;"
        ><?= drawproblem_escape($draw_reader_problem['starter_code']) ?></textarea>
    </section>
    <?php else: ?>
        <div class="ui message">
            기본 코드는 로그인 후 확인할 수 있습니다.
            <a href="/loginpage.php">로그인</a>
        </div>
    <?php endif; ?>
</div>
<?php if ($draw_reader_logged_in): ?>
    <?php foreach (array(
        '/ace/ace.js',
        '/ace/mode-javascript.js',
        '/ace/mode-python.js',
        '/ace/theme-chrome.js',
    ) as $draw_reader_asset): ?>
        <?php if (is_file(dirname(__DIR__, 2) . $draw_reader_asset)): ?>
            <script src="<?= drawproblem_escape($draw_reader_asset) ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
    <script src="/template/syzoj/js/drawproblem-editor.js?v=1"></script>
<?php endif; ?>
<?php include('template/' . $OJ_TEMPLATE . '/footer.php'); ?>
