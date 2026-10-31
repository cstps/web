<?php // 호출 화면에서 설정한 Contest 번호와 복원 모드를 사용한다. ?>
<?php echo $view_csrf_input; ?>
<input type="hidden" name="course_id" value="<?= (int)$course_id ?>">
<input type="hidden" name="contest_id" value="<?= (int)$restore_contest_id ?>">
<input type="hidden" name="repair_active" value="<?= $restore_repair_active ? '1' : '0' ?>">
<div class="field">
    <label for="contest-restore-lesson-<?= (int)$restore_contest_id ?>">배정할 차시</label>
    <select name="target_lesson_id" id="contest-restore-lesson-<?= (int)$restore_contest_id ?>">
        <option value="">원래 차시 유지</option>
        <?php foreach ($view_lesson_groups as $restore_group): ?>
            <?php $restore_lesson = $restore_group['lesson']; ?>
            <option value="<?= (int)$restore_lesson['lesson_id'] ?>">
                <?= (int)$restore_lesson['lesson_no'] ?>차시 —
                <?= htmlspecialchars((string)$restore_lesson['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="field">
    <label>
        <input type="checkbox" name="restore_lesson" value="1" checked>
        원래 차시가 비활성이면 함께 비공개로 복원
    </label>
</div>
<p>활동은 비공개로 복원됩니다. 다른 차시를 선택하면 해당 차시에 배정합니다.</p>
