<?php
if (
    !isset($simulation_problem_form_ready) ||
    $simulation_problem_form_ready !== true
) {
    http_response_code(403);
    exit('문제 편집 화면에서 이용해 주세요.');
}

$simulation_saved_view = $simulation_problem_saved_image ?? null;
?>
<?php if ($simulation_saved_view !== null): ?>
<section class="admin-card bank-saved-image"
         id="bank-saved-image"
         data-version-id="<?= simulation_problem_form_escape(
             $simulation_saved_view['version_id'] ?? ''
         ) ?>">
    <div>
        <h2>저장된 정답 목표 그림</h2>
        <p>
            문제 버전 <?= simulation_problem_form_escape(
                $simulation_saved_view['version_no'] ?? $base_version
            ) ?>의 서버 생성 결과입니다.
        </p>
        <p id="bank-saved-image-status" role="status" aria-live="polite"><?= simulation_problem_form_escape(
            $simulation_saved_view['label']
        ) ?></p>
        <p id="bank-saved-image-generated"><?= simulation_problem_form_escape(
            $simulation_saved_view['generated_at'] ?? ''
        ) ?></p>
        <p id="bank-saved-image-region"><?= simulation_problem_form_escape(
            $simulation_saved_view['region_label'] ?? ''
        ) ?></p>
        <button class="admin-btn admin-btn-secondary"
                id="bank-saved-image-refresh" type="button">상태 확인</button>
    </div>

    <?php
    $simulation_saved_ready = $simulation_saved_view['state'] === 'ready';
    $simulation_saved_url = $simulation_saved_ready
        ? 'simulation_problem_reference_image.php?' . http_build_query(array(
            'version_id' => $simulation_saved_view['version_id'],
            'hash' => $simulation_saved_view['png_sha256'],
        ))
        : '';
    ?>
    <a id="bank-saved-image-link" target="_blank" rel="noopener noreferrer"
       <?= $simulation_saved_ready ? '' : 'hidden' ?>
       href="<?= simulation_problem_form_escape($simulation_saved_url) ?>">
        <img id="bank-saved-image-preview"
             alt="저장된 정답 목표 그림"
             width="200" height="200"
             <?= $simulation_saved_ready
                 ? 'src="' . simulation_problem_form_escape($simulation_saved_url) . '"'
                 : '' ?>>
        <span>원본 크기로 보기</span>
    </a>
</section>
<?php endif; ?>
