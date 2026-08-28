<?php

$show_title =
    "학생 문제 해결 과정 현황 - $OJ_NAME";

include(
    "template/$OJ_TEMPLATE/header.php"
);

?>

<div
    class="ui container"
    style="
        max-width:1200px;
        margin-top:25px;
        margin-bottom:40px;
    ">

    <!-- ======================================================
         학생별 문제 진행 현황
         ====================================================== -->

    <h3 class="ui dividing header">
        학생별 문제 진행 현황
    </h3>





    <!-- ======================================================
         확인 필요 학생 수
         ====================================================== -->

    <div id="contest-process-summary-wrap">

        <div
            class="ui small message"
            style="
                margin-bottom:15px;
            ">

            현재 확인 필요 학생:

            <strong>

                <?php
                echo intval(
                    $attention_student_count
                );
                ?>명

            </strong>

        </div>


        <!-- ======================================================
            문제별 학급 진행 현황
            ====================================================== -->

        <div
            class="ui segment"
            style="
                margin-bottom:15px;
            ">

            <strong>
                문제별 학급 진행 현황
            </strong>


            <div
                style="
                    overflow-x:auto;
                    margin-top:12px;
                ">

                <table
                    class="ui very compact celled table">

                    <thead>

                        <tr>

                            <th class="center aligned">
                                문제
                            </th>

                            <th class="center aligned">
                                해결
                            </th>

                            <th class="center aligned">
                                진행 중
                            </th>

                            <th class="center aligned">
                                미제출
                            </th>

                            <th class="center aligned">
                                전체 제출
                            </th>

                            <th class="center aligned">
                                AI 활용 학생
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php
                        foreach (
                            $problem_class_summary
                            as
                            $problem_num => $summary
                        ) {
                        ?>

                            <tr>


                                <!-- 문제 -->

                                <td class="center aligned">

                                    <strong>

                                        <a
                                            href="problem.php?cid=<?php
                                                                    echo intval($cid);
                                                                    ?>&pid=<?php
                                                                            echo intval($problem_num);
                                                                            ?>"
                                            target="_blank"
                                            title="<?php
                                                    echo htmlentities(
                                                        $summary['label'] . ' 문제 보기',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?>"
                                            style="
                text-decoration:none;
            ">
                                            <?php
                                            echo htmlentities(
                                                $summary['label'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </a>

                                    </strong>

                                    <br>

                                    <span
                                        style="
            font-size:0.8em;
            color:#777;
        ">

                                        <?php
                                        echo intval(
                                            $summary['problem_id']
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- 해결 -->

                                <td class="center aligned">

                                    <?php

                                    $solved_count =
                                        intval(
                                            $summary['solved']
                                        );


                                    $solved_percent =
                                        $total_student_count > 0
                                        ? round(
                                            $solved_count /
                                                $total_student_count *
                                                100
                                        )
                                        : 0;

                                    ?>


                                    <a
                                        href="javascript:void(0);"
                                        class="ui green basic label"
                                        onclick="applySummaryFilter(
                                        'solved',
                                        '<?php
                                            echo intval(
                                                $problem_num
                                            );
                                            ?>'
                                    );"
                                        title="이 문제를 해결한 학생만 표시">

                                        <?php
                                        echo $solved_count;
                                        ?>명

                                        <span
                                            style="
                                            font-weight:normal;
                                        ">

                                            (<?php
                                                echo $solved_percent;
                                                ?>%)

                                        </span>

                                    </a>

                                </td>


                                <!-- 진행 중 -->

                                <td class="center aligned">

                                    <?php

                                    $working_count =
                                        intval(
                                            $summary['working']
                                        );


                                    $working_percent =
                                        $total_student_count > 0
                                        ? round(
                                            $working_count /
                                                $total_student_count *
                                                100
                                        )
                                        : 0;

                                    ?>


                                    <?php if ($working_count > 0) { ?>

                                        <a
                                            href="javascript:void(0);"
                                            class="ui orange basic label"
                                            onclick="applySummaryFilter(
                                            'working',
                                            '<?php
                                                echo intval(
                                                    $problem_num
                                                );
                                                ?>'
                                        );"
                                            title="이 문제를 제출했지만 아직 해결하지 못한 학생만 표시">

                                            <?php
                                            echo $working_count;
                                            ?>명

                                            <span
                                                style="
                                                font-weight:normal;
                                            ">

                                                (<?php
                                                    echo $working_percent;
                                                    ?>%)

                                            </span>

                                        </a>

                                    <?php } else { ?>

                                        0명 (0%)

                                    <?php } ?>

                                </td>


                                <!-- 미제출 -->

                                <td class="center aligned">

                                    <?php

                                    $nosubmit_count =
                                        intval(
                                            $summary['nosubmit']
                                        );


                                    $nosubmit_percent =
                                        $total_student_count > 0
                                        ? round(
                                            $nosubmit_count /
                                                $total_student_count *
                                                100
                                        )
                                        : 0;

                                    ?>


                                    <?php if ($nosubmit_count > 0) { ?>

                                        <a
                                            href="javascript:void(0);"
                                            class="ui grey basic label"
                                            onclick="applySummaryFilter(
                                            'nosubmit',
                                            '<?php
                                                echo intval(
                                                    $problem_num
                                                );
                                                ?>'
                                        );"
                                            title="이 문제를 아직 제출하지 않은 학생만 표시">

                                            <?php
                                            echo $nosubmit_count;
                                            ?>명

                                            <span
                                                style="
                                                font-weight:normal;
                                            ">

                                                (<?php
                                                    echo $nosubmit_percent;
                                                    ?>%)

                                            </span>

                                        </a>

                                    <?php } else { ?>

                                        0명 (0%)

                                    <?php } ?>

                                </td>


                                <!-- 전체 제출 -->

                                <td class="center aligned">

                                    <?php
                                    echo intval(
                                        $summary['total_submit']
                                    );
                                    ?>회

                                </td>


                                <!-- AI 활용 학생 -->

                                <td class="center aligned">

                                    <?php
                                    echo intval(
                                        $summary['ai_students']
                                    );
                                    ?>명

                                </td>


                            </tr>


                        <?php } ?>


                    </tbody>

                </table>

            </div>

        </div>
    </div>


    <!-- ======================================================
         학생 상태 필터
         ====================================================== -->

    <div
        class="ui small buttons"
        style="
            margin-bottom:15px;
        ">

        <button
            type="button"
            class="ui button active"
            data-filter="all"
            onclick="filterStudents(
                'all',
                this
            )">
            전체
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="working"
            onclick="filterStudents(
                'working',
                this
            )">
            진행 중
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="partial"
            onclick="filterStudents(
                'partial',
                this
            )">
            일부 해결
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="complete"
            onclick="filterStudents(
                'complete',
                this
            )">
            전체 해결
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="ai"
            onclick="filterStudents(
                'ai',
                this
            )">
            AI 사용
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="attention"
            onclick="filterStudents(
                'attention',
                this
            )">
            확인 필요
        </button>


        <button
            type="button"
            class="ui button"
            data-filter="nosubmit"
            onclick="filterStudents(
                'nosubmit',
                this
            )">
            미제출
        </button>

    </div>


    <!-- ======================================================
         문제 선택 필터
         ====================================================== -->

    <div
        style="
            margin-top:10px;
            margin-bottom:15px;
        ">

        <strong>
            문제 선택:
        </strong>


        <button
            type="button"
            class="ui mini button active problem-filter"
            onclick="selectProblemFilter(
                'all',
                this
            )">
            전체
        </button>


        <?php
        foreach (
            $contest_problems
            as
            $problem_num => $problem
        ) {
        ?>

            <button
                type="button"
                class="ui mini button problem-filter"
                onclick="selectProblemFilter(
                    '<?php
                        echo intval(
                            $problem_num
                        );
                        ?>',
                    this
                )">

                <?php
                echo htmlentities(
                    $problem['label'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </button>

        <?php } ?>

    </div>


    <!-- ======================================================
         현재 적용 중인 필터
         ====================================================== -->

    <div
        id="current-filter-info"
        class="ui tiny message"
        style="
            margin-top:10px;
            margin-bottom:15px;
            display:none;
        ">
    </div>


    <!-- ======================================================
        AJAX 갱신 대상 학생 현황 표
    ====================================================== -->

    <div
        id="student-process-table-wrap"
        style="
            overflow-x:auto;
        ">

        <?php
        // ====================================================
        // 최초 페이지 출력에서는 기존 PHP 데이터로
        // 학생 현황 표를 바로 출력한다.
        //
        // 30초 이후부터 AJAX 템플릿으로 교체된다.
        // ====================================================
        ?>

        <table class="ui celled compact table">

            <thead>

                <tr>

                    <th>
                        학생
                    </th>


                    <?php
                    foreach (
                        $contest_problems
                        as
                        $problem_num => $problem
                    ) {
                    ?>

                        <th
                            style="
                            text-align:center;
                        ">

                            <a
                                href="problem.php?cid=<?php
                                                        echo intval($cid);
                                                        ?>&pid=<?php
                                echo intval($problem_num);
                            ?>"
                                target="_blank"
                                title="<?php
                                        echo htmlentities(
                                            $problem['label'] . ' 문제 보기',
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


                <?php
                foreach (
                    $student_matrix
                    as
                    $student
                ) {
                ?>


                    <?php
                    // =================================================
                    // 현재 학생의 확인 필요 문제 수
                    // =================================================

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


                        $problem_data =
                            $student['problems'][$problem_num];


                        $problem_submit_count =
                            intval(
                                $problem_data['submit_count']
                            );


                        $problem_result_num =
                            intval(
                                $problem_data['latest_result']
                            );


                        if (
                            $problem_submit_count >= 5 &&
                            $problem_result_num !== 4
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
                        // =============================================
                        // 문제별 필터용 data 속성
                        // =============================================

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


                        <!-- 학생 -->

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


                        <!-- 문제별 상태 -->

                        <?php
                        foreach (
                            $contest_problems
                            as
                            $problem_num => $problem
                        ) {
                        ?>

                            <td
                                style="
                                text-align:center;
                            ">


                                <?php

                                if (
                                    isset(
                                        $student['problems'][$problem_num]
                                    )
                                ) {

                                    $p =
                                        $student['problems'][$problem_num];


                                    // =====================================
                                    // 교사 메모 수
                                    // =====================================

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


                                    // =====================================
                                    // 결과
                                    // =====================================

                                    $result_num =
                                        intval(
                                            $p['latest_result']
                                        );


                                    $result_text =
                                        "-";


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


                                        <?php

                                        $result_label = "-";
                                        $result_class = "grey";


                                        switch ($result_num) {

                                            case 4:

                                                $result_label =
                                                    "AC";

                                                $result_class =
                                                    "green";

                                                break;


                                            case 5:

                                                $result_label =
                                                    "PE";

                                                $result_class =
                                                    "orange";

                                                break;


                                            case 6:

                                                $result_label =
                                                    "WA";

                                                $result_class =
                                                    "red";

                                                break;


                                            case 7:

                                                $result_label =
                                                    "TLE";

                                                $result_class =
                                                    "orange";

                                                break;


                                            case 8:

                                                $result_label =
                                                    "MLE";

                                                $result_class =
                                                    "orange";

                                                break;


                                            case 9:

                                                $result_label =
                                                    "OLE";

                                                $result_class =
                                                    "orange";

                                                break;


                                            case 10:

                                                $result_label =
                                                    "RE";

                                                $result_class =
                                                    "red";

                                                break;


                                            case 11:

                                                $result_label =
                                                    "CE";

                                                $result_class =
                                                    "orange";

                                                break;


                                            default:

                                                if (
                                                    isset(
                                                        $judge_result[$result_num]
                                                    )
                                                ) {

                                                    $result_label =
                                                        $judge_result[$result_num];
                                                }

                                                $result_class =
                                                    "grey";

                                                break;
                                        }

                                        ?>


                                        <strong>

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


                                        $repeat_type =
                                            "";


                                        if ($is_repeated_attempt) {

                                            if ($result_num === 6) {

                                                $repeat_type =
                                                    "wa";
                                            } else if (
                                                $result_num === 11
                                            ) {

                                                $repeat_type =
                                                    "ce";
                                            } else if (
                                                $result_num === 10
                                            ) {

                                                $repeat_type =
                                                    "re";
                                            } else {

                                                $repeat_type =
                                                    "other";
                                            }
                                        }
                                        ?>


                                        <!-- 반복 -->

                                        <?php if (
                                            $is_repeated_attempt
                                        ) { ?>

                                            <br>


                                            <?php if (
                                                $repeat_type === "ce"
                                            ) { ?>

                                                <span
                                                    class="ui mini orange basic label"
                                                    style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                                white-space:nowrap;
                                            ">
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
                                            ">
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
                                            ">
                                                    RE 반복
                                                </span>


                                            <?php } else { ?>

                                                <span
                                                    class="ui mini grey basic label"
                                                    style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                                white-space:nowrap;
                                            ">
                                                    반복 시도
                                                </span>

                                            <?php } ?>

                                        <?php } ?>


                                        <!-- AI -->

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


                                        <!-- 교사 메모 -->

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


                        <!-- 총 제출 -->

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


                        <!-- AI -->

                        <td
                            style="
                            text-align:center;
                            white-space:nowrap;
                        ">

                            <?php
                            echo intval(
                                $student['total_ai']
                            );
                            ?>회

                        </td>


                        <!-- 해결 -->

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

    </div>


    <!-- ======================================================
         JavaScript
         ====================================================== -->

    <script>
        var currentStudentFilter =
            sessionStorage.getItem(
                "contest_process_student_filter"
            ) || "all";


        var currentProblemFilter =
            sessionStorage.getItem(
                "contest_process_problem_filter"
            ) || "all";


        // ============================================================
        // 학생 상태 필터
        // ============================================================

        function filterStudents(
            type,
            button
        ) {

            currentStudentFilter =
                type;


            sessionStorage.setItem(
                "contest_process_student_filter",
                type
            );


            var buttons =
                document.querySelectorAll(
                    "[data-filter]"
                );


            for (
                var j = 0; j < buttons.length; j++
            ) {

                buttons[j]
                    .classList
                    .remove(
                        "active"
                    );
            }


            if (button) {

                button
                    .classList
                    .add(
                        "active"
                    );
            }


            applyStudentFilters();

            updateCurrentFilterInfo();
        }


        // ============================================================
        // 문제 선택
        // ============================================================

        function selectProblemFilter(
            problemNum,
            button
        ) {

            currentProblemFilter =
                problemNum;


            sessionStorage.setItem(
                "contest_process_problem_filter",
                problemNum
            );


            var buttons =
                document.querySelectorAll(
                    ".problem-filter"
                );


            for (
                var i = 0; i < buttons.length; i++
            ) {

                buttons[i]
                    .classList
                    .remove(
                        "active"
                    );
            }


            if (button) {

                button
                    .classList
                    .add(
                        "active"
                    );
            }


            applyStudentFilters();

            updateCurrentFilterInfo();
        }


        // ============================================================
        // 문제별 요약 표에서 직접 필터 적용
        // ============================================================

        function applySummaryFilter(
            studentFilter,
            problemNum
        ) {

            currentStudentFilter =
                studentFilter;


            currentProblemFilter =
                problemNum;


            sessionStorage.setItem(
                "contest_process_student_filter",
                studentFilter
            );


            sessionStorage.setItem(
                "contest_process_problem_filter",
                problemNum
            );


            var studentButtons =
                document.querySelectorAll(
                    "[data-filter]"
                );


            for (
                var i = 0; i < studentButtons.length; i++
            ) {

                studentButtons[i]
                    .classList
                    .remove(
                        "active"
                    );


                if (
                    studentButtons[i]
                    .getAttribute(
                        "data-filter"
                    ) ===
                    studentFilter
                ) {

                    studentButtons[i]
                        .classList
                        .add(
                            "active"
                        );
                }
            }


            var problemButtons =
                document.querySelectorAll(
                    ".problem-filter"
                );


            for (
                var j = 0; j < problemButtons.length; j++
            ) {

                problemButtons[j]
                    .classList
                    .remove(
                        "active"
                    );


                var clickText =
                    problemButtons[j]
                    .getAttribute(
                        "onclick"
                    ) || "";


                if (
                    clickText.indexOf(
                        "'" +
                        problemNum +
                        "'"
                    ) !== -1
                ) {

                    problemButtons[j]
                        .classList
                        .add(
                            "active"
                        );
                }
            }


            applyStudentFilters();

            updateCurrentFilterInfo();


            var studentTable =
                document.querySelector(
                    ".student-row"
                );


            if (studentTable) {

                studentTable
                    .scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
            }
        }



        // ============================================================
        // 실제 필터 적용
        // ============================================================

        function applyStudentFilters() {

            var rows =
                document.querySelectorAll(
                    ".student-row"
                );


            for (
                var i = 0; i < rows.length; i++
            ) {

                var row =
                    rows[i];


                var totalSubmit =
                    parseInt(
                        row.getAttribute(
                            "data-total-submit"
                        )
                    ) || 0;


                var totalAI =
                    parseInt(
                        row.getAttribute(
                            "data-total-ai"
                        )
                    ) || 0;


                var solvedCount =
                    parseInt(
                        row.getAttribute(
                            "data-solved-count"
                        )
                    ) || 0;


                var problemCount =
                    parseInt(
                        row.getAttribute(
                            "data-problem-count"
                        )
                    ) || 0;


                var attentionCount =
                    parseInt(
                        row.getAttribute(
                            "data-attention-count"
                        )
                    ) || 0;


                var show =
                    true;


                // ====================================================
                // 특정 문제 선택
                // ====================================================

                if (
                    currentProblemFilter !==
                    "all"
                ) {

                    var pSubmit =
                        parseInt(
                            row.getAttribute(
                                "data-problem-" +
                                currentProblemFilter +
                                "-submit"
                            )
                        ) || 0;


                    var resultAttr =
                        row.getAttribute(
                            "data-problem-" +
                            currentProblemFilter +
                            "-result"
                        );


                    var pResult =
                        resultAttr !== null ?
                        parseInt(
                            resultAttr
                        ) :
                        -1;


                    if (
                        currentStudentFilter ===
                        "all"
                    ) {

                        show =
                            pSubmit > 0;
                    } else if (
                        currentStudentFilter ===
                        "solved"
                    ) {

                        show =
                            pSubmit > 0 &&
                            pResult === 4;
                    } else if (
                        currentStudentFilter ===
                        "working"
                    ) {

                        show =
                            pSubmit > 0 &&
                            pResult !== 4;
                    } else if (
                        currentStudentFilter ===
                        "nosubmit"
                    ) {

                        show =
                            pSubmit === 0;
                    } else if (
                        currentStudentFilter ===
                        "partial"
                    ) {

                        show =
                            solvedCount > 0 &&
                            solvedCount <
                            problemCount;
                    } else if (
                        currentStudentFilter ===
                        "complete"
                    ) {

                        show =
                            problemCount > 0 &&
                            solvedCount ===
                            problemCount;
                    } else if (
                        currentStudentFilter ===
                        "ai"
                    ) {

                        show =
                            totalAI > 0;
                    } else if (
                        currentStudentFilter ===
                        "attention"
                    ) {

                        show =
                            pSubmit >= 5 &&
                            pResult !== 4;
                    }
                }


                // ====================================================
                // 문제 전체
                // ====================================================
                else {


                    if (
                        currentStudentFilter ===
                        "working"
                    ) {

                        show =
                            totalSubmit > 0 &&
                            solvedCount === 0;
                    } else if (
                        currentStudentFilter ===
                        "partial"
                    ) {

                        show =
                            solvedCount > 0 &&
                            solvedCount <
                            problemCount;
                    } else if (
                        currentStudentFilter ===
                        "complete"
                    ) {

                        show =
                            problemCount > 0 &&
                            solvedCount ===
                            problemCount;
                    } else if (
                        currentStudentFilter ===
                        "ai"
                    ) {

                        show =
                            totalAI > 0;
                    } else if (
                        currentStudentFilter ===
                        "attention"
                    ) {

                        show =
                            attentionCount > 0;
                    } else if (
                        currentStudentFilter ===
                        "nosubmit"
                    ) {

                        show =
                            totalSubmit === 0;
                    } else {

                        show =
                            true;
                    }
                }


                row.style.display =
                    show ?
                    "" :
                    "none";
            }
        }


        // ============================================================
        // 현재 필터 표시
        // ============================================================

        function updateCurrentFilterInfo() {

            var info =
                document.getElementById(
                    "current-filter-info"
                );


            if (!info) {

                return;
            }


            var studentText =
                "";


            if (
                currentStudentFilter ===
                "all"
            ) {

                studentText =
                    "전체";
            } else if (
                currentStudentFilter ===
                "working"
            ) {

                studentText =
                    "진행 중";
            } else if (
                currentStudentFilter ===
                "partial"
            ) {

                studentText =
                    "일부 해결";
            } else if (
                currentStudentFilter ===
                "complete"
            ) {

                studentText =
                    "전체 해결";
            } else if (
                currentStudentFilter ===
                "ai"
            ) {

                studentText =
                    "AI 사용";
            } else if (
                currentStudentFilter ===
                "attention"
            ) {

                studentText =
                    "확인 필요";
            } else if (
                currentStudentFilter ===
                "nosubmit"
            ) {

                studentText =
                    "미제출";
            } else if (
                currentStudentFilter ===
                "solved"
            ) {

                studentText =
                    "해결";
            }


            var problemText =
                "전체 문제";


            if (
                currentProblemFilter !==
                "all"
            ) {

                var problemButtons =
                    document.querySelectorAll(
                        ".problem-filter"
                    );


                for (
                    var i = 0; i < problemButtons.length; i++
                ) {

                    var clickText =
                        problemButtons[i]
                        .getAttribute(
                            "onclick"
                        ) || "";


                    if (
                        clickText.indexOf(
                            "'" +
                            currentProblemFilter +
                            "'"
                        ) !== -1
                    ) {

                        problemText =
                            problemButtons[i]
                            .innerText
                            .trim();

                        break;
                    }
                }
            }


            if (
                currentStudentFilter ===
                "all" &&
                currentProblemFilter ===
                "all"
            ) {

                info.style.display =
                    "none";

                info.innerHTML =
                    "";

                return;
            }


            info.style.display =
                "block";


            info.innerHTML =
                "<strong>현재 필터:</strong> " +
                problemText +
                " / " +
                studentText;
        }


        // ============================================================
        // 페이지 로딩 시 저장된 필터 복원
        // ============================================================

        document.addEventListener(
            "DOMContentLoaded",
            function() {

                var studentButtons =
                    document.querySelectorAll(
                        "[data-filter]"
                    );


                for (
                    var i = 0; i < studentButtons.length; i++
                ) {

                    studentButtons[i]
                        .classList
                        .remove(
                            "active"
                        );


                    if (
                        studentButtons[i]
                        .getAttribute(
                            "data-filter"
                        ) ===
                        currentStudentFilter
                    ) {

                        studentButtons[i]
                            .classList
                            .add(
                                "active"
                            );
                    }
                }


                var problemButtons =
                    document.querySelectorAll(
                        ".problem-filter"
                    );


                for (
                    var j = 0; j < problemButtons.length; j++
                ) {

                    problemButtons[j]
                        .classList
                        .remove(
                            "active"
                        );
                }


                if (
                    currentProblemFilter ===
                    "all"
                ) {

                    if (
                        problemButtons.length >
                        0
                    ) {

                        problemButtons[0]
                            .classList
                            .add(
                                "active"
                            );
                    }
                } else {

                    for (
                        var k = 0; k < problemButtons.length; k++
                    ) {

                        var clickText =
                            problemButtons[k]
                            .getAttribute(
                                "onclick"
                            ) || "";


                        if (
                            clickText.indexOf(
                                "'" +
                                currentProblemFilter +
                                "'"
                            ) !== -1
                        ) {

                            problemButtons[k]
                                .classList
                                .add(
                                    "active"
                                );
                        }
                    }
                }


                applyStudentFilters();

                updateCurrentFilterInfo();
            }
        );


        // ============================================================
        // AJAX 학생 현황 갱신
        // ============================================================

        var studentProcessAjaxRunning =
            false;


        function refreshStudentProcessTable() {

            // 이전 AJAX 요청이 아직 진행 중이면
            // 중복 요청하지 않는다.
            if (
                studentProcessAjaxRunning
            ) {

                return;
            }


            studentProcessAjaxRunning =
                true;


            var xhr =
                new XMLHttpRequest();


            xhr.open(
                "GET",
                "contest_process_students_ajax.php?cid=" +
                encodeURIComponent(
                    <?php
                    echo intval(
                        $cid
                    );
                    ?>
                ) +
                "&_=" +
                new Date().getTime(),
                true
            );


            xhr.onreadystatechange =
                function() {

                    if (
                        xhr.readyState !== 4
                    ) {

                        return;
                    }


                    studentProcessAjaxRunning =
                        false;


                    if (
                        xhr.status !== 200
                    ) {

                        return;
                    }


                    var wrap =
                        document.getElementById(
                            "student-process-table-wrap"
                        );


                    if (!wrap) {

                        return;
                    }


                    // 학생 현황 표만 교체
                    wrap.innerHTML =
                        xhr.responseText;


                    // 교체된 새 행에
                    // 기존 필터를 다시 적용
                    applyStudentFilters();
                };


            xhr.send();
        }

        // ============================================================
        // 상단 요약 AJAX 갱신
        // ============================================================

        var contestProcessSummaryAjaxRunning =
            false;


        function refreshContestProcessSummary() {

            if (
                contestProcessSummaryAjaxRunning
            ) {
                return;
            }


            contestProcessSummaryAjaxRunning =
                true;


            var xhr =
                new XMLHttpRequest();


            xhr.open(
                "GET",
                "contest_process_summary_ajax.php?cid=" +
                encodeURIComponent(
                    <?php echo intval($cid); ?>
                ) +
                "&_=" +
                new Date().getTime(),
                true
            );


            xhr.onreadystatechange =
                function() {

                    if (
                        xhr.readyState !== 4
                    ) {
                        return;
                    }


                    contestProcessSummaryAjaxRunning =
                        false;


                    if (
                        xhr.status !== 200
                    ) {
                        return;
                    }


                    var wrap =
                        document.getElementById(
                            "contest-process-summary-wrap"
                        );


                    if (!wrap) {
                        return;
                    }


                    wrap.innerHTML =
                        xhr.responseText;
                };


            xhr.send();
        }
        // ============================================================
        // 30초마다 상단 요약 + 학생 현황 갱신
        // ============================================================

        function refreshContestProcessAll() {

            refreshContestProcessSummary();

            refreshStudentProcessTable();
        }

        // 기존 30초 → 5분으로 변경
        // 정상 갱신은 SSE가 담당하고,
        // 이 부분은 SSE 장애 시 비상 갱신용
        setInterval(
            refreshContestProcessAll,
            300000
        );

        // ============================================================
        // SSE - 제출/채점 결과 변경 즉시 갱신
        // ============================================================

        if (
            typeof(EventSource) !== "undefined"
        ) {

            var contestProcessEventSource =
                new EventSource(
                    "contest_process_stream.php?cid=" +
                    encodeURIComponent(
                        <?php echo intval($cid); ?>
                    )
                );


            let contestProcessRefreshTimer = null;

            contestProcessEventSource.addEventListener(
                "solution_update",
                function(event) {

                    console.log(
                        "contest process update:",
                        event.data
                    );

                    if (contestProcessRefreshTimer) {
                        clearTimeout(contestProcessRefreshTimer);
                    }

                    contestProcessRefreshTimer =
                        setTimeout(
                            function() {
                                refreshContestProcessAll();
                            },
                            1000
                        );
                }
            );


            contestProcessEventSource.onerror =
                function() {

                    console.log(
                        "contest process SSE reconnecting..."
                    );
                };
        }
    </script>


    <!-- ======================================================
         기존 학생 문제 해결 과정 현황
         ====================================================== -->

    <h2
        class="ui dividing header"
        style="
            margin-top:30px;
        ">
        학생 문제 해결 과정 현황
    </h2>


    <div class="ui segment">

        <strong>
            대회
        </strong>

        &nbsp;

        <?php
        echo intval(
            $cid
        );
        ?>

        &nbsp;&nbsp;

        <?php
        echo htmlentities(
            $contest_title,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>


    <?php if (
        count(
            $view_process_list
        ) == 0
    ) { ?>

        <div class="ui message">

            기록된 학생 과정이 없습니다.

        </div>


    <?php } else { ?>


        <table
            class="ui celled compact table">

            <thead>

                <tr>

                    <th>
                        학생
                    </th>

                    <th>
                        문제
                    </th>

                    <th>
                        제출 횟수
                    </th>

                    <th>
                        최종 결과
                    </th>

                    <th>
                        AI 사용
                    </th>

                    <th>
                        최초 계획
                    </th>

                    <th>
                        과정
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php
                foreach (
                    $view_process_list
                    as
                    $item
                ) {
                ?>

                    <tr>


                        <td>

                            <?php
                            echo htmlentities(
                                $item['user_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            if (
                                isset($PID) &&
                                $item['problem_num'] >= 0 &&
                                isset(
                                    $PID[$item['problem_num']]
                                )
                            ) {

                                echo htmlentities(
                                    $PID[$item['problem_num']],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );


                                echo " / ";
                            }


                            echo intval(
                                $item['problem_id']
                            );

                            ?>

                        </td>


                        <td>

                            <?php
                            echo intval(
                                $item['submit_count']
                            );
                            ?>회

                        </td>


                        <td>

                            <?php

                            $result_num =
                                intval(
                                    $item['latest_result']
                                );


                            if (
                                isset(
                                    $judge_result[$result_num]
                                )
                            ) {

                                echo htmlentities(
                                    $judge_result[$result_num],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            } else {

                                echo "-";
                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            $ai_count =
                                intval(
                                    $item['ai_count']
                                );


                            if ($ai_count > 0) {

                                echo
                                $ai_count .
                                    "회";
                            } else {

                                echo
                                "미사용";
                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            if (
                                intval(
                                    $item['has_plan']
                                ) === 1
                            ) {

                                echo
                                "작성";
                            } else {

                                echo
                                "-";
                            }

                            ?>

                        </td>


                        <td>

                            <a
                                href="solution_process_view.php?sid=<?php
                                                                    echo intval(
                                                                        $item['latest_solution_id']
                                                                    );
                                                                    ?>"
                                class="ui mini basic button"
                                style="
                                white-space:nowrap;
                            ">
                                과정
                            </a>

                        </td>


                    </tr>


                <?php } ?>


            </tbody>

        </table>


    <?php } ?>


    <!-- ======================================================
         하단
         ====================================================== -->

    <div
        style="
            text-align:center;
            margin-top:25px;
        ">

        <a
            href="status.php?cid=<?php
                                    echo intval(
                                        $cid
                                    );
                                    ?>"
            class="ui button">
            대회 Status
        </a>

    </div>


</div>


<?php

include(
    "template/$OJ_TEMPLATE/footer.php"
);

?>