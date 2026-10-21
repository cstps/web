<?php
include("template/$OJ_TEMPLATE/header.php");

function simulation_reference_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<link rel="stylesheet" href="/template/syzoj/css/course-simulation-reference.css?v=3">
<div class="ui container">
    <h2 class="ui header">그림 문제 정답 관리</h2>

    <p>
        활동:
        <?= simulation_reference_escape($view_reference_activity['title']) ?>
        / 현재 정답 버전:
        <?= intval($view_reference['reference_no']) ?>
    </p>

    <div class="ui info message">
        <p>정답 코드는 저장만 하며 이 화면에서는 실행하지 않습니다.</p>
        <p>저장할 때마다 새 버전을 추가합니다. 공식 채점은 아직 연결되지 않았습니다.</p>
        <p>실행 조건: 캔버스 400×400, p5.js 2.3.4, 픽셀 밀도 1, 흰 배경</p>
    </div>

    <?php if (($_GET['saved'] ?? '') === '1'): ?>
        <div class="ui positive message">
            새 정답 버전을 저장했습니다.
        </div>
    <?php endif; ?>

    <form class="ui form" method="post"
          action="course_simulation_reference.php">
        <?php
        ob_start();
        include('./csrf.php');
        echo ob_get_clean();
        ?>

        <input type="hidden" name="activity_id"
               value="<?= intval($activity_id) ?>">
        <input type="hidden" name="base_reference_no"
               value="<?= intval($view_reference['reference_no']) ?>">

        <div class="reference-workspace">
            <div class="reference-code-panel">
        <div class="field">
            <label id="reference-source-label" for="reference-source">교사용 정답 코드</label>
            <div id="reference-ace-editor"
                 class="reference-ace-editor"
                 hidden></div>
            <p id="reference-editor-message" role="alert" hidden></p>
            <textarea id="reference-source" name="source_code"
                      rows="22" required><?= simulation_reference_escape(
                          $view_reference['source_code']
                      ) ?></textarea>
        </div>
            </div>
            <div class="reference-preview-panel">
        <section class="ui segment"
                 aria-labelledby="reference-preview-heading">
            <h3 id="reference-preview-heading">정답 그림 미리보기</h3>

            <div class="ui buttons">
                <button id="reference-preview-run"
                        class="ui primary button" type="button">
                    미리보기 실행
                </button>
                <button id="reference-preview-stop"
                        class="ui button" type="button" disabled>
                    중지
                </button>
            </div>

            <p id="reference-preview-status" role="status"
               aria-live="polite">
                실행 버튼을 누르면 편집 중인 코드의 결과를 표시합니다.
            </p>

            <div class="reference-preview-box">
                <iframe
                    id="reference-preview-frame"
                    class="reference-preview-frame"
                    title="교사용 정답 그림 미리보기"
                    sandbox="allow-scripts"
                    referrerpolicy="no-referrer"
                ></iframe>
            </div>

            <p>미리보기는 정답 버전 저장 및 공식 채점과 별개입니다.</p>
        </section>
            </div>
        </div>

        <div class="three fields">
            <div class="field">
                <label for="reference-scoring-mode">점수 산정 방식</label>
                <select id="reference-scoring-mode" name="scoring_mode">
                    <?php foreach (array(
                        'metrics_only' => '비교 지표만 저장',
                        'whole_image' => '전체 그림 충족률로 점수 산정',
                        'regions' => '영역별 배점으로 점수 산정',
                    ) as $mode_value => $mode_label): ?>
                        <option
                            value="<?= simulation_reference_escape($mode_value) ?>"
                            <?= $view_reference_scoring_mode === $mode_value
                                ? 'selected' : '' ?>
                        >
                            <?= simulation_reference_escape($mode_label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="reference-radius">실험용 위치 허용 반경</label>
                <select id="reference-radius" name="position_tolerance">
                    <?php foreach (array(0, 1, 2) as $radius): ?>
                        <option value="<?= $radius ?>"
                            <?= $view_reference_radius === $radius
                                ? 'selected' : '' ?>>
                            <?= $radius ?>픽셀
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="reference-score">배점</label>
                <input id="reference-score" type="number"
                       name="max_score" min="0.01" max="99999.99"
                       step="0.01" required
                       value="<?= simulation_reference_escape(
                           $view_reference['max_score']
                       ) ?>">
            </div>
        </div>

        <p>
            비교 지표만 저장하면 점수를 산정하지 않습니다.
            전체 그림 방식은 전체 충족률로, 영역별 방식은 각 영역의
            충족률과 배점으로 점수를 산정합니다.
            선택한 방식은 새 정답 버전에 저장됩니다.
        </p>

        <fieldset
            id="reference-regions"
            class="ui segment"
            <?= $view_reference_scoring_mode !== 'regions'
                ? 'hidden disabled' : '' ?>
        >
            <legend>영역별 배점 설정</legend>

            <p>
                좌표는 캔버스 왼쪽 위가 (0, 0)입니다.
                영역은 서로 겹치지 않아야 하며,
                모든 정답 도형이 영역 안에 포함되어야 합니다.
                도형 주변에는 위치 허용 반경 이상의 여백을 두세요.
                영역 밖에 추가한 도형도 감점에 반영됩니다.
            </p>

            <p>
                도형 포함 여부와 여백은 렌더링된 그림으로 확인해야 합니다.
                저장 후 동일한 정답 코드로 시험 제출하여
                채점 결과를 확인해 주세요.
            </p>

            <?php
            $region_form_rows = $view_reference_regions;
            if (empty($region_form_rows)) {
                $region_form_rows = array(array(
                    'id' => 'region_1',
                    'label' => '',
                    'x' => 0,
                    'y' => 0,
                    'width' => 100,
                    'height' => 100,
                    'max_score' => ''
                ));
            }

            $region_fields = array(
                'label' => '영역 이름',
                'x' => '왼쪽 좌표',
                'y' => '위쪽 좌표',
                'width' => '너비',
                'height' => '높이',
                'max_score' => '영역 배점'
            );
            ?>

            <div style="overflow-x:auto;">
                <table class="ui compact table">
                    <thead>
                        <tr>
                            <th>영역 이름</th>
                            <th>왼쪽 x</th>
                            <th>위쪽 y</th>
                            <th>너비</th>
                            <th>높이</th>
                            <th>배점</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody id="reference-region-rows">
                        <?php foreach ($region_form_rows as $region_index => $region): ?>
                            <tr>
                                <?php foreach ($region_fields as $field_key => $field_label): ?>
                                    <td>
                                        <?php if ($field_key === 'label'): ?>
                                            <input
                                                type="hidden"
                                                data-region-key="id"
                                                name="regions[<?= intval($region_index) ?>][id]"
                                                value="<?= simulation_reference_escape($region['id']) ?>"
                                            >
                                        <?php endif; ?>
                                        <input
                                            data-region-key="<?= $field_key ?>"
                                            style="min-width:<?= $field_key === 'label'
                                                ? '140px'
                                                : ($field_key === 'max_score'
                                                    ? '110px' : '90px') ?>;"
                                            name="regions[<?= intval($region_index) ?>][<?= $field_key ?>]"
                                            aria-label="<?= $field_label ?>"
                                            value="<?= simulation_reference_escape(
                                                (string)$region[$field_key]
                                            ) ?>"
                                            required
                                            <?php if ($field_key === 'label'): ?>
                                                type="text" maxlength="80"
                                            <?php elseif ($field_key === 'max_score'): ?>
                                                type="number"
                                                min="0.01" max="99999.99" step="0.01"
                                            <?php else: ?>
                                                type="number"
                                                min="<?= in_array(
                                                    $field_key,
                                                    array('width', 'height'),
                                                    true
                                                ) ? 1 : 0 ?>"
                                                max="<?= in_array(
                                                    $field_key,
                                                    array('width', 'height'),
                                                    true
                                                ) ? 400 : 399 ?>"
                                                step="1"
                                            <?php endif; ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <button
                                        type="button"
                                        class="ui mini basic red button"
                                        data-region-remove
                                    >삭제</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button
                id="reference-region-add"
                type="button"
                class="ui small basic button"
            >영역 추가</button>

            <p
                id="reference-region-message"
                role="status"
                aria-live="polite"
            ></p>

            <noscript>
                영역 추가와 점수 산정 방식 변경에는 JavaScript가 필요합니다.
            </noscript>
        </fieldset>

        <button class="ui primary button" type="submit">
            새 정답 버전 저장
        </button>
        <a class="ui button"
           href="course_simulation_edit.php?activity_id=<?= intval($activity_id) ?>">
            시뮬레이션 편집으로
        </a>
    </form>
</div>
<script src="/ace/ace.js"></script>
<script src="/ace/mode-javascript.js"></script>
<script src="/ace/theme-chrome.js"></script>
<script src="/template/syzoj/js/course-simulation-reference.js?v=1"></script>
<script src="/template/syzoj/js/course-simulation-reference-regions.js?v=1"></script>
<script src="/template/syzoj/js/course-simulation-reference-preview.js?v=1"></script>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
