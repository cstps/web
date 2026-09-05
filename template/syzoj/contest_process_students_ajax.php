<?php
// ============================================================
// AJAX 전용 학생별 문제 진행 현황
//
// 이 파일은
// contest_process_students_ajax.php에서 호출한다.
//
// 주의:
// - header.php 없음
// - footer.php 없음
// - JavaScript 없음
// - 학생 현황 table만 출력
// ============================================================
?>


<table class="ui celled compact table">

    <thead>

        <tr>

            <th>
                학생
            </th>


            <?php foreach (
                $contest_problems
                as
                $problem_num => $problem
            ) { ?>

                <th style="text-align:center;">

                    <a
                        href="problem.php?cid=<?php
                                                echo intval($cid);
                                                ?>&pid=<?php
                                                        echo intval($problem_num);
                                                        ?>"
                        target="_blank"
                        title="<?php
                                echo htmlentities(
                                    $problem['label'] .
                                        ' ' .
                                        (
                                            isset($problem['title']) &&
                                            trim($problem['title']) !== ''
                                            ? $problem['title']
                                            : '문제 보기'
                                        ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>"
                        style="
                            text-decoration:none;
                        ">

                        <?php
                        echo htmlentities(
                            $problem['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </a>

                </th>

            <?php } ?>


            <th
                style="
                text-align:center;
                white-space:nowrap;
            ">
                총 제출
            </th>


            <th
                style="
                    text-align:center;
                    white-space:nowrap;
                    min-width:55px;
                ">
                AI
            </th>


            <th
                style="
                    text-align:center;
                    white-space:nowrap;
                ">
                해결
            </th>

        </tr>

    </thead>


    <tbody>


        <?php foreach ($student_matrix as $student) { ?>


            <?php
            // ========================================================
            // 이 학생의 확인 필요 문제 수
            //
            // 현재 기준:
            // - 5회 이상 제출
            // - 최종 결과가 AC가 아님
            // ========================================================

            $student_attention_count = 0;


            foreach (
                $contest_problems
                as
                $problem_num => $problem
            ) {

                if (
                    !isset(
                        $student['problems'][$problem_num]
                    )
                ) {

                    continue;
                }


                $attention_problem =
                    $student['problems'][$problem_num];


                $attention_submit_count =
                    intval(
                        $attention_problem['submit_count']
                    );


                $attention_result =
                    intval(
                        $attention_problem['latest_result']
                    );


                if (
                    $attention_submit_count >= 5 &&
                    $attention_result !== 4
                ) {

                    $student_attention_count++;
                }
            }
            ?>


            <tr
                class="student-row"

                data-total-submit="<?php
                                    echo intval(
                                        $student['total_submit']
                                    );
                                    ?>"

                data-total-ai="<?php
                                echo intval(
                                    $student['total_ai']
                                );
                                ?>"

                data-solved-count="<?php
                                    echo intval(
                                        $student['solved_count']
                                    );
                                    ?>"

                data-problem-count="<?php
                                    echo count(
                                        $contest_problems
                                    );
                                    ?>"

                data-attention-count="<?php
                                        echo intval(
                                            $student_attention_count
                                        );
                                        ?>"


                <?php
                // ====================================================
                // 문제별 필터용 데이터
                //
                // 예:
                // data-problem-0-submit="3"
                // data-problem-0-result="6"
                //
                // 기존 JavaScript의 applyStudentFilters()가
                // 이 값을 사용한다.
                // ====================================================

                foreach (
                    $contest_problems
                    as
                    $problem_num => $problem
                ) {

                    $problem_submit = 0;
                    $problem_result = -1;


                    if (
                        isset(
                            $student['problems'][$problem_num]
                        )
                    ) {

                        $problem_submit =
                            intval(
                                $student['problems'][$problem_num]['submit_count']
                            );


                        $problem_result =
                            intval(
                                $student['problems'][$problem_num]['latest_result']
                            );
                    }
                ?>

                data-problem-<?php
                                echo intval(
                                    $problem_num
                                );
                                ?>-submit="<?php
                                            echo intval(
                                                $problem_submit
                                            );
                                            ?>"

                data-problem-<?php
                                echo intval(
                                    $problem_num
                                );
                                ?>-result="<?php
                                            echo intval(
                                                $problem_result
                                            );
                                            ?>"

                <?php } ?>>


                <!-- ==================================================
                 학생
                 ================================================== -->

                <td>

                    <strong>

                        <a
                            href="student_process_summary.php?cid=<?php
                                                                    echo intval(
                                                                        $cid
                                                                    );
                                                                    ?>&user_id=<?php
                                                                                echo urlencode(
                                                                                    $student['user_id']
                                                                                );
                                                                                ?>"
                            title="<?php
                                    echo htmlentities(
                                        $student['nick'] .
                                            ' 학생 전체 문제 해결 과정 요약',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>">

                            <?php
                            echo htmlentities(
                                $student['user_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            </a>

                    </strong>

                </td>


                <!-- ==================================================
                 문제별 현황
                 ================================================== -->

                <?php
                foreach (
                    $contest_problems
                    as
                    $problem_num => $problem
                ) {
                ?>

                    <td style="text-align:center;">


                        <?php

                        if (
                            isset(
                                $student['problems'][$problem_num]
                            )
                        ) {

                            $p =
                                $student['problems'][$problem_num];


                            // ================================================
                            // 교사 관찰 메모 수
                            // ================================================

                            $problem_note_count = 0;


                            if (
                                isset(
                                    $teacher_note_count_map[$student['user_id']][$p['problem_id']]
                                )
                            ) {

                                $problem_note_count =
                                    intval(
                                        $teacher_note_count_map[$student['user_id']][$p['problem_id']]
                                    );
                            }


                            // ================================================
                            // 최신 결과
                            // ================================================

                            $result_num =
                                intval(
                                    $p['latest_result']
                                );


                            $result_text = "-";


                            if (
                                isset(
                                    $judge_result[$result_num]
                                )
                            ) {

                                $result_text =
                                    $judge_result[$result_num];
                            }

                        ?>


                            <a
                                href="solution_process_view.php?sid=<?php
                                                                    echo intval(
                                                                        $p['latest_solution_id']
                                                                    );
                                                                    ?>"
                                style="
                            text-decoration:none;
                            display:inline-block;
                            min-width:70px;
                        "
                                title="<?php
                                        echo htmlentities(
                                            $result_text .
                                                ' / 제출 ' .
                                                intval(
                                                    $p['submit_count']
                                                ) .
                                                '회 / AI ' .
                                                intval(
                                                    $p['ai_count']
                                                ) .
                                                '회',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>">


                                <strong>


                                    <?php
                                    // ============================================
                                    // 채점 결과 표시
                                    // ============================================

                                    $result_label = "-";
                                    $result_class = "grey";


                                    switch ($result_num) {

                                        // Accepted
                                        case 4:

                                            $result_label =
                                                "AC";

                                            $result_class =
                                                "green";

                                            break;


                                        // Presentation Error
                                        case 5:

                                            $result_label =
                                                "PE";

                                            $result_class =
                                                "orange";

                                            break;


                                        // Wrong Answer
                                        case 6:

                                            $result_label =
                                                "WA";

                                            $result_class =
                                                "red";

                                            break;


                                        // Time Limit Exceeded
                                        case 7:

                                            $result_label =
                                                "TLE";

                                            $result_class =
                                                "orange";

                                            break;


                                        // Memory Limit Exceeded
                                        case 8:

                                            $result_label =
                                                "MLE";

                                            $result_class =
                                                "orange";

                                            break;


                                        // Output Limit Exceeded
                                        case 9:

                                            $result_label =
                                                "OLE";

                                            $result_class =
                                                "orange";

                                            break;


                                        // Runtime Error
                                        case 10:

                                            $result_label =
                                                "RE";

                                            $result_class =
                                                "red";

                                            break;


                                        // Compile Error
                                        case 11:

                                            $result_label =
                                                "CE";

                                            $result_class =
                                                "orange";

                                            break;


                                        // 기타
                                        default:

                                            if (
                                                isset(
                                                    $judge_result[$result_num]
                                                )
                                            ) {

                                                $result_label =
                                                    $judge_result[$result_num];
                                            } else {

                                                $result_label =
                                                    "-";
                                            }


                                            $result_class =
                                                "grey";

                                            break;
                                    }
                                    ?>


                                    <span
                                        class="ui <?php
                                                    echo $result_class;
                                                    ?> tiny label"
                                        style="
                                    min-width:42px;
                                    text-align:center;
                                    white-space:nowrap;
                                ">

                                        <?php
                                        echo htmlentities(
                                            $result_label,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </span>

                                </strong>


                                <br>


                                <!-- 제출 횟수 -->

                                <span
                                    style="
                                font-size:0.85em;
                                color:#777;
                                white-space:nowrap;
                            ">

                                    <?php
                                    echo intval(
                                        $p['submit_count']
                                    );
                                    ?>회

                                </span>


                                <?php
                                // ============================================
                                // 반복 제출 / AI
                                // ============================================

                                $problem_ai_count =
                                    intval(
                                        $p['ai_count']
                                    );


                                $problem_submit_count =
                                    intval(
                                        $p['submit_count']
                                    );


                                $is_repeated_attempt =
                                    (
                                        $problem_submit_count >= 5 &&
                                        $result_num !== 4
                                    );


                                // ============================================
                                // 반복 시도 유형
                                //
                                // WA / CE / RE / 기타
                                // ============================================

                                $repeat_type = "";


                                if (
                                    $problem_submit_count >= 5 &&
                                    $result_num !== 4
                                ) {

                                    // Wrong Answer
                                    if (
                                        $result_num === 6
                                    ) {

                                        $repeat_type =
                                            "wa";
                                    }

                                    // Compile Error
                                    else if (
                                        $result_num === 11
                                    ) {

                                        $repeat_type =
                                            "ce";
                                    }

                                    // Runtime Error
                                    else if (
                                        $result_num === 10
                                    ) {

                                        $repeat_type =
                                            "re";
                                    }

                                    // 기타 미해결
                                    else {

                                        $repeat_type =
                                            "other";
                                    }
                                }
                                ?>


                                <!-- =========================================
                             반복 시도
                             ========================================= -->

                                <?php if ($is_repeated_attempt) { ?>

                                    <br>


                                    <?php if ($repeat_type === "ce") { ?>

                                        <span
                                            class="ui mini orange basic label"
                                            style="
                                        margin-top:4px;
                                        font-size:0.75em;
                                        white-space:nowrap;
                                    "
                                            title="컴파일 오류 상태로 5회 이상 제출했습니다.">
                                            CE 반복
                                        </span>


                                    <?php } else if (
                                        $repeat_type === "wa"
                                    ) { ?>

                                        <span
                                            class="ui mini red basic label"
                                            style="
                                        margin-top:4px;
                                        font-size:0.75em;
                                        white-space:nowrap;
                                    "
                                            title="틀림 상태로 5회 이상 제출했습니다.">
                                            WA 반복
                                        </span>


                                    <?php } else if (
                                        $repeat_type === "re"
                                    ) { ?>

                                        <span
                                            class="ui mini red basic label"
                                            style="
                                        margin-top:4px;
                                        font-size:0.75em;
                                        white-space:nowrap;
                                    "
                                            title="런타임 오류 상태로 5회 이상 제출했습니다.">
                                            RE 반복
                                        </span>


                                    <?php } else { ?>

                                        <span
                                            class="ui mini grey basic label"
                                            style="
                                        margin-top:4px;
                                        font-size:0.75em;
                                        white-space:nowrap;
                                    "
                                            title="5회 이상 제출했지만 아직 해결되지 않았습니다.">
                                            반복 시도
                                        </span>

                                    <?php } ?>

                                <?php } ?>


                                <!-- =========================================
                             AI 사용
                             ========================================= -->

                                <?php if (
                                    $problem_ai_count > 0
                                ) { ?>

                                    <br>

                                    <span
                                        class="ui mini basic label"
                                        style="
                                    margin-top:4px;
                                    font-size:0.75em;
                                    white-space:nowrap;
                                ">
                                        AI <?php
                                            echo intval(
                                                $problem_ai_count
                                            );
                                            ?>회
                                    </span>

                                <?php } ?>


                                <!-- =========================================
                             교사 관찰 메모
                             ========================================= -->

                                <?php if (
                                    $problem_note_count > 0
                                ) { ?>

                                    <br>

                                    <span
                                        class="ui mini blue basic label"
                                        style="
                                    margin-top:4px;
                                    font-size:0.75em;
                                    white-space:nowrap;
                                "
                                        title="작성된 교사 관찰 메모">
                                        메모 <?php
                                            echo intval(
                                                $problem_note_count
                                            );
                                            ?>
                                    </span>

                                <?php } ?>


                            </a>


                        <?php

                        } else {

                            echo "-";
                        }

                        ?>


                    </td>

                <?php } ?>


                <!-- ==================================================
                 총 제출
                 ================================================== -->

                <td
                    style="
                    text-align:center;
                    white-space:nowrap;
                ">

                    <?php
                    echo intval(
                        $student['total_submit']
                    );
                    ?>

                </td>


                <!-- ==================================================
                 AI
                 ================================================== -->

                <td
                    style="
                    text-align:center;
                    white-space:nowrap;
                    min-width:55px;
                ">

                    <?php
                    echo intval(
                        $student['total_ai']
                    );
                    ?>회

                </td>


                <!-- ==================================================
                 해결
                 ================================================== -->

                <td
                    style="
                    text-align:center;
                    white-space:nowrap;
                ">

                    <?php
                    echo intval(
                        $student['solved_count']
                    );
                    ?>

                    /

                    <?php
                    echo count(
                        $contest_problems
                    );
                    ?>

                </td>


            </tr>


        <?php } ?>


    </tbody>

</table>