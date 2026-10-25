<?php
if (
    !isset($simulation_problem_form_ready) ||
    $simulation_problem_form_ready !== true
) {
    http_response_code(403);
    exit('문제 편집 화면에서 이용해 주세요.');
}
?>
<link rel="stylesheet" href="simulation-problem-preview.css?v=5">
<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><?= simulation_problem_form_escape($admin_page_title) ?></h1>
            <p class="admin-page-description">
                저장할 때마다 새 버전을 추가합니다.
                정답 코드는 미리보기로 확인하고, 저장 후 서버에서 목표 그림을 생성합니다.
            </p>
        </div>
        <a class="admin-btn admin-btn-secondary"
           href="simulation_problem_list.php">그림 문제 목록</a>
    </div>

    <?php if ($simulation_problem_form_error !== ''): ?>
        <div class="admin-card" role="alert">
            <?= simulation_problem_form_escape($simulation_problem_form_error) ?>
            <p>입력한 코드를 복사해 보관할 수 있습니다.</p>
        </div>
    <?php elseif (($_GET['saved'] ?? '') === '1'): ?>
        <p role="status">새 문제 버전을 저장했습니다.</p>
    <?php endif; ?>

    <?php if (!$fatal): ?>
    <form method="post" action="simulation_problem_edit.php"
          id="simulation-bank-form" accept-charset="UTF-8">
        <input type="hidden" name="problem_id"
               value="<?= simulation_problem_form_escape($problem_id ?? '') ?>">
        <input type="hidden" name="base_version"
               value="<?= simulation_problem_form_escape($base_version) ?>">
        <input type="hidden" name="simulation_problem_csrf"
               value="<?= simulation_problem_form_escape($simulation_problem_form_csrf_token) ?>">

        <section class="admin-card bank-basic-settings">
            <h2>기본 정보</h2>
            <p>현재 버전: <?= simulation_problem_form_escape($base_version) ?></p>

            <div class="admin-form-field bank-title-field">
                <label class="admin-form-label" for="bank-title">문제 제목</label>
                <input class="admin-form-input" id="bank-title"
                       name="title" maxlength="255" required
                       value="<?= simulation_problem_form_escape($values['title']) ?>">
            </div>



            <?php
            $choices = array(
                'execution_profile' => array('작성 언어', array(
                    'p5js-2.3.4' => 'JavaScript · p5.js',
                    'python-draw-poc-v1' => 'Python · 그림 실험',
                )),
                'simulation_mode' => array('문제 유형', array(
                    'drawing' => '그림 문제',
                    'free' => '자유 실습',
                )),
                'published' => array('학생 공개', array(
                    '0' => '비공개',
                    '1' => '공개',
                )),
                'sharing' => array('다른 교사에게 공유', array(
                    'private' => '공유하지 않음',
                    'shared' => '공유',
                )),
                'allow_edit' => array('학생 코드 편집', array(
                    '1' => '허용', '0' => '허용하지 않음',
                )),
                'allow_submit' => array('학생 코드 제출', array(
                    '1' => '허용', '0' => '허용하지 않음',
                )),
            );
            ?>
            <?php foreach ($choices as $key => $choice): ?>
                <div class="admin-form-field">
                    <label class="admin-form-label" for="bank-<?= $key ?>"><?= $choice[0] ?></label>
                    <select class="admin-form-input" id="bank-<?= $key ?>"
                            name="<?= $key ?>">
                        <?php foreach ($choice[1] as $value => $label): ?>
                            <option value="<?= simulation_problem_form_escape($value) ?>"
                                <?= $values[$key] === (string)$value ? 'selected' : '' ?>>
                                <?= simulation_problem_form_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <p>Python 문제는 현재 비공개로만 저장할 수 있습니다.</p>

            <?php foreach (array(
                'canvas_width' => '캔버스 너비',
                'canvas_height' => '캔버스 높이',
            ) as $key => $label): ?>
                <div class="admin-form-field">
                    <label class="admin-form-label" for="bank-<?= $key ?>"><?= $label ?></label>
                    <input class="admin-form-input" type="number"
                           style="width:180px;max-width:100%"
                           id="bank-<?= $key ?>" name="<?= $key ?>"
                           min="100" max="2000" step="1" required
                           value="<?= simulation_problem_form_escape($values[$key]) ?>">
                </div>
            <?php endforeach; ?>
            <p>그림 문제와 Python 실험은 400×400으로 설정합니다.</p>
        </section>


        <section class="admin-card bank-authoring-workspace">
            <div class="bank-description-panel">
            <div class="admin-form-field bank-description-field">
                <label class="admin-form-label" for="bank-description">문제 설명</label>
                <textarea class="admin-form-input" id="bank-description"
                          name="description" rows="4"><?= simulation_problem_form_escape($values['description']) ?></textarea>
                <p>문단·글자 강조·목록·표 등의 서식을 함께 저장합니다.</p>
            </div>
            </div>

            <div class="bank-starter-panel">
                <label class="admin-form-label" for="bank-starter-source">
                    학생에게 제공할 기본 코드
                </label>
                <div id="bank-editor-starter_code" hidden
                     style="height:320px;min-height:180px;resize:vertical;overflow:hidden;border:1px solid #cbd5e1"></div>
                <textarea id="bank-starter-source"
                          class="admin-form-input simulation-bank-source"
                          name="starter_code" rows="15"
                          aria-label="학생에게 제공할 기본 코드"
                          data-editor-id="bank-editor-starter_code"
                          spellcheck="false"><?= simulation_problem_form_escape($values['starter_code']) ?></textarea>
            </div>
        </section>

        <section class="admin-card">
            <h2>그림 문제 채점 설정</h2>
            <p>자유 실습에서는 이 설정을 사용하지 않습니다.</p>

            <?php foreach (array(
                'scoring_mode' => array('점수 산정 방식', array(
                    'metrics_only' => '비교 지표만 저장',
                    'whole_image' => '전체 그림 충족률',
                    'regions' => '영역별 배점',
                )),
                'position_tolerance' => array('위치 허용 반경', array(
                    '0' => '0픽셀', '1' => '1픽셀', '2' => '2픽셀',
                )),
            ) as $key => $choice): ?>
                <div class="admin-form-field">
                    <label class="admin-form-label" for="bank-<?= $key ?>"><?= $choice[0] ?></label>
                    <select class="admin-form-input" id="bank-<?= $key ?>"
                            name="<?= $key ?>">
                        <?php foreach ($choice[1] as $value => $label): ?>
                            <option value="<?= simulation_problem_form_escape($value) ?>"
                                <?= $values[$key] === (string)$value ? 'selected' : '' ?>>
                                <?= simulation_problem_form_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <div class="admin-form-field">
                <label class="admin-form-label" for="bank-max-score">전체 배점</label>
                <input class="admin-form-input" style="width:180px;max-width:100%"
                       type="number" id="bank-max-score"
                       name="max_score" min="0.01" max="99999.99" step="0.01"
                       value="<?= simulation_problem_form_escape($values['max_score']) ?>">
            </div>

            <div class="admin-form-field">
                <label class="admin-form-label" for="bank-regions">영역 설정 JSON</label>
                <textarea class="admin-form-input" id="bank-regions"
                          name="regions_json" rows="8"
                          spellcheck="false"><?= simulation_problem_form_escape($values['regions_json']) ?></textarea>
                <p>영역별 배점 방식을 선택할 때 사용합니다. 영역별 배점 합계는 전체 배점과 같아야 합니다.</p>
            </div>
        </section>

        <?php foreach (array(
            'reference_source' => '교사용 정답 코드',
        ) as $key => $label): ?>
            <section class="admin-card">
                <h2><?= $label ?></h2>
                <div id="bank-editor-<?= $key ?>" hidden
                     style="height:<?= $key === 'reference_source' ? '500' : '240' ?>px;min-height:180px;resize:vertical;overflow:hidden;border:1px solid #cbd5e1"></div>
                <textarea class="admin-form-input simulation-bank-source"
                          name="<?= $key ?>" rows="<?= $key === 'reference_source' ? '25' : '12' ?>"
                          aria-label="<?= $label ?>"
                          data-editor-id="bank-editor-<?= $key ?>"
                          spellcheck="false"><?= simulation_problem_form_escape($values[$key]) ?></textarea>
                <?php if ($key === 'reference_source'): ?>
                    <p>그림 문제에만 필요하며 학생용 문제 목록에는 표시하지 않습니다.</p>
                    <div id="bank-reference-preview">
                        <div class="bank-preview-toolbar">
                            <h3>정답 그림 미리보기</h3>
                            <div class="bank-preview-actions">
                            <button type="button" id="bank-preview-run"
                                    class="admin-btn admin-btn-primary">미리보기 실행</button>
                            <button type="button" id="bank-preview-stop"
                                    class="admin-btn admin-btn-secondary" disabled>중지</button>

                            </div>
                        </div>
                        <p id="bank-preview-status" role="status" aria-live="polite">
                            정답 코드를 실행하고 결과 그림을 확인해 주세요.
                        </p>
                        <div class="bank-preview-output">
                            <div id="bank-preview-host"></div>
                            <canvas id="bank-preview-canvas"
                                    width="400" height="400" hidden></canvas>
                        </div>
                        <p>Python 정적 미리보기는 setup 실행 직후인 프레임 0을 표시합니다.</p>
                    </div>

                <?php endif; ?>
            </section>
        <?php endforeach; ?>




        <?php require(__DIR__ . '/simulation_problem_saved_image.php'); ?>

        <p id="simulation-bank-message" role="alert" hidden></p>
        <button class="admin-btn admin-btn-primary" type="submit">새 버전 저장</button>
        <a class="admin-btn admin-btn-secondary"
           href="<?= $problem_id === null
               ? 'simulation_problem_edit.php'
               : 'simulation_problem_edit.php?problem_id=' . rawurlencode($problem_id) ?>">
            편집 화면 다시 열기
        </a>
    </form>

    <?php foreach (array(
        '/ace/ace.js',
        '/ace/mode-javascript.js',
        '/ace/mode-python.js',
        '/ace/theme-chrome.js',
    ) as $asset): ?>
        <?php if (is_file(dirname(__DIR__) . $asset)): ?>
            <script src="<?= simulation_problem_form_escape($asset) ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
    <script src="/tinymce/tinymce.min.js?v=8.9.0"></script>
    <script src="simulation-problem-description.js?v=1"></script>
    <script src="simulation-problem-editor.js?v=2"></script>
    <script src="simulation-problem-preview.js?v=1"></script>
    <script src="simulation-problem-saved-image.js?v=1"></script>
    <script src="simulation-problem-regions.js?v=1"></script>
    <?php endif; ?>
</div>
