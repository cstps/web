<?php

// 교사 권한 검사를 마친 정답 관리 화면에서 사용한다.
if (
    !isset($view_reference) ||
    !is_array($view_reference) ||
    !isset($view_reference_scoring_mode) ||
    !function_exists('simulation_reference_escape')
) {
    http_response_code(404);
    exit;
}

$reference_image_state = $view_reference['image_status'] ?? '';
$reference_image_label = '목표 이미지 상태 확인 필요';
$reference_image_text = '목표 이미지 상태를 확인하지 못했습니다.';
$reference_image_class = 'warning';

$reference_image_source_matches =
    intval($view_reference['image_source_matches'] ?? 0) === 1;

$reference_region_verified = (
    ($view_reference['image_region_validation'] ?? '') === 'valid' &&
    $reference_image_source_matches &&
    is_string($view_reference['image_grading_config_sha256'] ?? null) &&
    hash_equals(
        hash('sha256', (string)$view_reference['grading_config']),
        $view_reference['image_grading_config_sha256']
    )
);

if (intval($view_reference['reference_no']) === 0) {
    $reference_image_label = '저장된 정답 없음';
    $reference_image_text =
        '정답 코드를 저장하면 서버에서 목표 이미지 생성을 시작합니다.';
    $reference_image_class = 'info';
} elseif ($reference_image_state === 'pending') {
    $reference_image_label = '목표 이미지 생성 대기';
    $reference_image_text =
        '서버 처리 순서를 기다리고 있습니다.';
    $reference_image_class = 'info';
} elseif ($reference_image_state === 'running') {
    $reference_image_label = '목표 이미지 생성 중';
    $reference_image_text = $view_reference_scoring_mode === 'regions'
        ? '정답 그림을 생성하고 영역 설정을 검사하고 있습니다.'
        : '서버에서 정답 그림을 생성하고 있습니다.';
    $reference_image_class = 'info';
} elseif ($reference_image_state === 'ready') {
    if (!$reference_image_source_matches) {
        $reference_image_label = '목표 이미지 정보 확인 필요';
        $reference_image_text =
            '저장된 정답 코드와 목표 이미지의 정보가 일치하지 않습니다.';
    } elseif ($view_reference_scoring_mode !== 'regions') {
        $reference_image_label = '목표 이미지 생성 완료';
        $reference_image_text =
            '저장된 정답 버전의 목표 이미지가 준비되었습니다.';
        $reference_image_class = 'positive';
    } elseif ($reference_region_verified) {
        $reference_image_label = '목표 이미지 생성 완료 · 영역 검사 통과';
        $reference_image_text =
            '정답 도형의 영역 포함 여부와 위치 허용 반경 여백 검사를 통과했습니다.';
        $reference_image_class = 'positive';
    } else {
        $reference_image_label = '목표 이미지 생성 완료 · 영역 검사 기록 확인 필요';
        $reference_image_text =
            '이 정답 버전에는 현재 설정과 일치하는 영역 검사 통과 기록이 없습니다.';
    }
} elseif ($reference_image_state === 'error') {
    $reference_image_label = '목표 이미지 생성 또는 영역 검사 실패';
    $reference_image_text =
        '정답 코드와 영역 설정을 확인해 주세요. 수정 후 새 정답 버전으로 저장할 수 있습니다.';
    $reference_image_class = 'negative';
} elseif ($reference_image_state === '') {
    $reference_image_label = '목표 이미지 생성 요청 없음';
    $reference_image_text =
        '이 정답 버전에는 목표 이미지 생성 요청이 없습니다.';
}
?>

<div
    id="reference-image-status"
    data-activity-id="<?= intval($activity_id) ?>"
    data-reference-no="<?= intval($view_reference['reference_no']) ?>"
    data-image-status="<?= simulation_reference_escape($reference_image_state) ?>"
    class="ui <?= simulation_reference_escape($reference_image_class) ?> message"
    role="status"
    aria-live="polite"
>
    <div class="header">
        <?= simulation_reference_escape($reference_image_label) ?>
    </div>

    <p>
        저장된 정답 버전:
        <?= intval($view_reference['reference_no']) ?>
        · 이 상태는 현재 편집 중인 코드의 미리보기와 별개입니다.
    </p>

    <p><?= simulation_reference_escape($reference_image_text) ?></p>

    <?php if (
        $reference_image_state === 'ready' &&
        !empty($view_reference['image_generated_at'])
    ): ?>
        <p>
            생성 완료 시각:
            <?= simulation_reference_escape(
                $view_reference['image_generated_at']
            ) ?>
        </p>
    <?php endif; ?>
</div>
