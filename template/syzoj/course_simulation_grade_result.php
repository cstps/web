<?php

// 제출 현황 및 권한 검사를 마친 결과 조회 경로에서만 사용한다.
if (
    !isset($submission) ||
    !is_array($submission) ||
    !isset($simulation_escape) ||
    !is_callable($simulation_escape)
) {
    http_response_code(404);
    exit;
}
?>
<?php
                                                $grade_status = isset(
                                                    $submission['grading_status']
                                                ) ? (string)$submission['grading_status'] : '';

                                                $grade_labels = array(
                                                    'pending' => '채점 대기',
                                                    'running' => '비교 중',
                                                    'graded' => '비교 완료',
                                                    'error' => '실행 오류',
                                                );

                                                $grade_label = $grade_status === ''
                                                    ? '채점 요청 없음'
                                                    : (
                                                        isset($grade_labels[$grade_status])
                                                        ? $grade_labels[$grade_status]
                                                        : '상태 확인 필요'
                                                    );
                                                ?>

                                                <div class="ui small message">
                                                    <div class="header">
                                                        <?php echo $simulation_escape(
                                                            $grade_label
                                                        ); ?>
                                                    </div>

                                                    <?php if ($grade_status !== '') { ?>
                                                        <p>
                                                            채점 <?php echo intval(
                                                                $submission['grading_no']
                                                            ); ?>회차
                                                            <?php if (isset(
                                                                $submission['grade_reference_no']
                                                            )) { ?>
                                                                · 정답 버전
                                                                <?php echo $simulation_escape(
                                                                    $submission['grade_reference_no']
                                                                ); ?>
                                                            <?php } ?>
                                                        </p>
                                                    <?php } ?>

                                                    <?php if ($grade_status === 'graded') { ?>
                                                        <p>
                                                            <?php
                                                            foreach (array(
                                                                'grade_foreground_match' => '전경 일치율',
                                                                'grade_foreground_overlap' => '전경 겹침률',
                                                            ) as $metric_key => $metric_label) {
                                                                $metric_value = isset($submission[$metric_key])
                                                                    ? $submission[$metric_key]
                                                                    : null;

                                                                $metric_text = '확인 불가';

                                                                if (
                                                                    is_numeric($metric_value) &&
                                                                    is_finite((float)$metric_value) &&
                                                                    (float)$metric_value >= 0 &&
                                                                    (float)$metric_value <= 100
                                                                ) {
                                                                    $metric_text = number_format(
                                                                        (float)$metric_value,
                                                                        3,
                                                                        '.',
                                                                        ''
                                                                    ) . '%';
                                                                }
                                                                ?>
                                                                <span class="ui basic label">
                                                                    <?php echo $simulation_escape(
                                                                        $metric_label . ': ' . $metric_text
                                                                    ); ?>
                                                                </span>
                                                            <?php } ?>
                                                        </p>
                                                        <?php
                                                        $tolerance_value =
                                                            $submission['grade_tolerance_coverage'] ?? null;
                                                        $tolerance_radius =
                                                            $submission['grade_tolerance_radius'] ?? null;

                                                        $tolerance_valid = (
                                                            is_numeric($tolerance_value) &&
                                                            is_finite((float)$tolerance_value) &&
                                                            (float)$tolerance_value >= 0 &&
                                                            (float)$tolerance_value <= 100 &&
                                                            ctype_digit((string)$tolerance_radius) &&
                                                            intval($tolerance_radius) >= 0 &&
                                                            intval($tolerance_radius) <= 10
                                                        );
                                                        ?>

                                                        <p>
                                                            <?php if ($tolerance_valid) { ?>
                                                                <span class="ui blue basic label">
                                                                    전체 그림 허용 오차 충족률:
                                                                    <?php echo $simulation_escape(
                                                                        number_format(
                                                                            (float)$tolerance_value,
                                                                            3,
                                                                            '.',
                                                                            ''
                                                                        ) . '%'
                                                                    ); ?>
                                                                </span>

                                                                <span class="ui basic label">
                                                                    위치 허용 반경:
                                                                    <?php echo intval(
                                                                        $tolerance_radius
                                                                    ); ?>픽셀
                                                                </span>
                                                            <?php } elseif (
                                                                $tolerance_value === null &&
                                                                $tolerance_radius === null
                                                            ) { ?>
                                                                <span class="ui basic label">
                                                                    허용 오차 비교: 미산출
                                                                </span>
                                                            <?php } else { ?>
                                                                <span class="ui basic label">
                                                                    허용 오차 정보 확인 필요
                                                                </span>
                                                            <?php } ?>
                                                        </p>

                                                        <?php
                                                        $saved_score =
                                                            $submission['grade_score'] ?? null;
                                                        $saved_max_score =
                                                            $submission['grade_max_score'] ?? null;

                                                        $saved_score_valid = (
                                                            $saved_score !== null &&
                                                            $saved_max_score !== null &&
                                                            is_numeric($saved_score) &&
                                                            is_numeric($saved_max_score) &&
                                                            is_finite((float)$saved_score) &&
                                                            is_finite((float)$saved_max_score) &&
                                                            (float)$saved_score >= 0 &&
                                                            (float)$saved_max_score > 0 &&
                                                            (float)$saved_score <=
                                                                (float)$saved_max_score
                                                        );
                                                        ?>

                                                        <p>
                                                            <?php if ($saved_score_valid) { ?>
                                                                <span class="ui teal label">
                                                                    점수:
                                                                    <?php echo $simulation_escape(
                                                                        number_format(
                                                                            (float)$saved_score,
                                                                            2,
                                                                            '.',
                                                                            ''
                                                                        ) .
                                                                        ' / ' .
                                                                        number_format(
                                                                            (float)$saved_max_score,
                                                                            2,
                                                                            '.',
                                                                            ''
                                                                        )
                                                                    ); ?>점
                                                                </span>
                                                            <?php } elseif (
                                                                $saved_score === null &&
                                                                $saved_max_score === null
                                                            ) { ?>
                                                                비교 지표만 저장한 기록입니다.
                                                                점수는 산정하지 않았습니다.
                                                            <?php } else { ?>
                                                                점수 정보 확인이 필요합니다.
                                                            <?php } ?>
                                                        </p>
                                                    <?php
                                                        require(
                                                            __DIR__ .
                                                            '/course_simulation_region_grade_result.php'
                                                        );
                                                    ?>
                                                    <?php } elseif ($grade_status === 'error') { ?>
                                                        <p>
                                                            코드 실행 또는 그림 비교 처리 중
                                                            오류가 발생했습니다.
                                                        </p>
                                                    <?php } ?>
                                                </div>
