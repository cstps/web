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
    "
>

    <!-- ======================================================
        학생별 문제 진행 현황
        ====================================================== -->

    <h3 class="ui dividing header">
        학생별 문제 진행 현황
    </h3>
    <?php
    // ============================================================
    // 확인 필요 학생 수 계산
    //
    // 한 문제라도
    // - 5회 이상 제출
    // - 최종 결과가 AC가 아님
    // 이면 확인 필요 학생으로 계산
    // ============================================================

    $attention_student_count = 0;

    foreach ($student_matrix as $student) {

        $needs_attention = false;

        foreach ($contest_problems as $problem_num => $problem) {

            if (
                isset(
                    $student['problems'][$problem_num]
                )
            ) {

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

                    $needs_attention = true;
                    break;
                }
            }
        }

        if ($needs_attention) {
            $attention_student_count++;
        }
        
    }
    ?>
    <?php
    // ============================================================
    // 문제별 학급 진행 현황 계산
    //
    // solved    : 최종 결과 AC
    // working   : 1회 이상 제출했지만 아직 AC 아님
    // nosubmit  : 해당 문제 제출 없음
    // ============================================================

    $problem_class_summary = array();

    $total_student_count =
        count($student_matrix);


    foreach ($contest_problems as $problem_num => $problem) {

        $solved_count = 0;
        $working_count = 0;
        $nosubmit_count = 0;

        $total_submit_count = 0;
        $ai_student_count = 0;


        foreach ($student_matrix as $student) {

            if (
                !isset(
                    $student['problems'][$problem_num]
                )
            ) {

                // 해당 문제를 한 번도 제출하지 않음
                $nosubmit_count++;

                continue;
            }


            $problem_data =
                $student['problems'][$problem_num];


            $submit_count =
                intval(
                    $problem_data['submit_count']
                );


            $result_num =
                intval(
                    $problem_data['latest_result']
                );


            $ai_count =
                intval(
                    $problem_data['ai_count']
                );


            // 전체 제출 횟수
            $total_submit_count +=
                $submit_count;


            // 해당 문제에서 AI를 한 번이라도 사용한 학생
            if ($ai_count > 0) {

                $ai_student_count++;
            }


            // AC
            if ($result_num === 4) {

                $solved_count++;

            }
            // 제출했지만 아직 해결하지 못함
            else {

                $working_count++;
            }
        }


        $problem_class_summary[$problem_num] = array(

            'label' =>
                $problem['label'],

            'problem_id' =>
                intval(
                    $problem['problem_id']
                ),

            'solved' =>
                $solved_count,

            'working' =>
                $working_count,

            'nosubmit' =>
                $nosubmit_count,

            'total_submit' =>
                $total_submit_count,

            'ai_students' =>
                $ai_student_count
        );
    }
    ?>
    <div
        class="ui small message"
        style="
            margin-bottom:15px;
        "
    >
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
        "
    >

        <strong>
            문제별 학급 진행 현황
        </strong>


        <div
            style="
                overflow-x:auto;
                margin-top:12px;
            "
        >

            <table
                class="ui very compact celled table"
            >

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

                        <td class="center aligned">

                            <strong>
                                <?php
                                echo htmlentities(
                                    $summary['label'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </strong>

                            <br>

                            <span
                                style="
                                    font-size:0.8em;
                                    color:#777;
                                "
                            >
                                <?php
                                echo intval(
                                    $summary['problem_id']
                                );
                                ?>
                            </span>

                        </td>


                        <!-- 해결 -->

                        <td class="center aligned">

                            <a
                                href="javascript:void(0);"
                                class="ui green basic label"
                                onclick="applySummaryFilter(
                                    'solved',
                                    '<?php echo intval($problem_num); ?>'
                                );"
                                title="이 문제를 해결한 학생만 표시"
                            >
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

                                    echo $solved_count;
                                    ?>명

                                    <span style="font-weight:normal;">
                                        (<?php echo $solved_percent; ?>%)
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
                                        '<?php echo intval($problem_num); ?>'
                                    );"
                                    title="이 문제를 제출했지만 아직 해결하지 못한 학생만 표시"
                                >
                                    <?php echo $working_count; ?>명

                                    <span style="font-weight:normal;">
                                        (<?php echo $working_percent; ?>%)
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
                                        '<?php echo intval($problem_num); ?>'
                                    );"
                                    title="이 문제를 아직 제출하지 않은 학생만 표시"
                                >
                                    <?php echo $nosubmit_count; ?>명

                                    <span style="font-weight:normal;">
                                        (<?php echo $nosubmit_percent; ?>%)
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


                        <!-- AI 활용 -->

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

    <div
        class="ui small buttons"
        style="margin-bottom:15px;"
    >

        <button
            type="button"
            class="ui button active"
            data-filter="all"
            onclick="filterStudents('all', this)"
        >
            전체
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="working"
            onclick="filterStudents('working', this)"
        >
            진행 중
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="partial"
            onclick="filterStudents('partial', this)"
        >
            일부 해결
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="complete"
            onclick="filterStudents('complete', this)"
        >
            전체 해결
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="ai"
            onclick="filterStudents('ai', this)"
        >
            AI 사용
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="attention"
            onclick="filterStudents('attention', this)"
        >
            확인 필요
        </button>

        <button
            type="button"
            class="ui button"
            data-filter="nosubmit"
            onclick="filterStudents('nosubmit', this)"
        >
            미제출
        </button>

    </div>
    <div
        style="
            margin-top:10px;
            margin-bottom:15px;
        "
    >

        <strong>
            문제 선택:
        </strong>

        <button
            type="button"
            class="ui mini button active problem-filter"
            onclick="selectProblemFilter('all', this)"
        >
            전체
        </button>


        <?php foreach ($contest_problems as $problem_num => $problem) { ?>

            <button
                type="button"
                class="ui mini button problem-filter"
                onclick="selectProblemFilter(
                    '<?php echo intval($problem_num); ?>',
                    this
                )"
            >

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
     현재 적용 중인 필터 표시
     ====================================================== -->

    <div
        id="current-filter-info"
        class="ui tiny message"
        style="
            margin-top:10px;
            margin-bottom:15px;
            display:none;
        "
    >
    </div>

    <div style="overflow-x:auto;">

    <table class="ui celled compact table">

        <thead>

            <tr>

                <th>학생</th>

                <?php foreach ($contest_problems as $problem) { ?>

                    <th style="text-align:center;">

                        <?php
                        echo htmlentities(
                            $problem['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </th>

                <?php } ?>

                <th style="text-align:center;">
                    총 제출
                </th>

                <th style="text-align:center;">
                    AI
                </th>

                <th style="text-align:center;">
                    해결
                </th>

            </tr>

        </thead>


        <tbody>

        <?php foreach ($student_matrix as $student) { ?>

        <?php
        // ============================================================
        // 현재 학생의 "확인 필요" 문제 수 계산
        //
        // 기준:
        // - 해당 문제 5회 이상 제출
        // - 아직 AC(result=4)가 아님
        // ============================================================

        $student_attention_count = 0;

        foreach ($contest_problems as $problem_num => $problem) {

            if (
                isset(
                    $student['problems'][$problem_num]
                )
            ) {

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
            // ========================================================
            // 문제별 필터용 데이터
            //
            // data-problem-0-submit
            // data-problem-0-result
            // data-problem-1-submit
            // data-problem-1-result
            // ...
            // ========================================================

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
                    echo intval($problem_num);
                ?>-submit="<?php
                    echo intval($problem_submit);
                ?>"

                data-problem-<?php
                    echo intval($problem_num);
                ?>-result="<?php
                    echo intval($problem_result);
                ?>"

            <?php } ?>

        >

                <td>

                    <strong>

                    <?php
                    echo htmlentities(
                        $student['user_id'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                    </strong>

                </td>


                <?php foreach ($contest_problems as $problem_num => $problem) { ?>

                    <td style="text-align:center;">

                    <?php

                    if (
                        isset(
                            $student['problems'][
                                $problem_num
                            ]
                        )
                    ) {

                        $p =
                            $student['problems'][
                                $problem_num
                            ];

                        $result_num =
                            intval(
                                $p['latest_result']
                            );

                        $result_text = "-";

                        if (
                            isset(
                                $judge_result[
                                    $result_num
                                ]
                            )
                        ) {

                            $result_text =
                                $judge_result[
                                    $result_num
                                ];
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
                                    $result_text.
                                    ' / 제출 '.
                                    intval($p['submit_count']).
                                    '회 / AI '.
                                    intval($p['ai_count']).
                                    '회',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                        >

                            <strong>

                            <?php

                                // ============================================================
                                // 채점 결과별 표시
                                // ============================================================

                                $result_label = "-";
                                $result_class = "grey";

                                switch ($result_num) {

                                    // Accepted
                                    case 4:
                                        $result_label = "AC";
                                        $result_class = "green";
                                        break;

                                    // Presentation Error
                                    case 5:
                                        $result_label = "PE";
                                        $result_class = "orange";
                                        break;

                                    // Wrong Answer
                                    case 6:
                                        $result_label = "WA";
                                        $result_class = "red";
                                        break;

                                    // Time Limit Exceeded
                                    case 7:
                                        $result_label = "TLE";
                                        $result_class = "orange";
                                        break;

                                    // Memory Limit Exceeded
                                    case 8:
                                        $result_label = "MLE";
                                        $result_class = "orange";
                                        break;

                                    // Output Limit Exceeded
                                    case 9:
                                        $result_label = "OLE";
                                        $result_class = "orange";
                                        break;

                                    // Runtime Error
                                    case 10:
                                        $result_label = "RE";
                                        $result_class = "red";
                                        break;

                                    // Compile Error
                                    case 11:
                                        $result_label = "CE";
                                        $result_class = "orange";
                                        break;

                                    // 기타
                                    default:

                                        if (
                                            isset(
                                                $judge_result[
                                                    $result_num
                                                ]
                                            )
                                        ) {

                                            $result_label =
                                                $judge_result[
                                                    $result_num
                                                ];

                                        }
                                        else {

                                            $result_label = "-";
                                        }

                                        $result_class = "grey";
                                        break;
                                }

                                ?>

                                <span
                                    class="ui <?php echo $result_class; ?> tiny label"
                                    style="
                                        min-width:42px;
                                        text-align:center;
                                    "
                                >
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
                                "
                            >

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
                                // ============================================================
                                // 반복 시도 유형
                                // ============================================================

                                $repeat_type = "";

                                if ($problem_submit_count >= 5) {

                                    // Wrong Answer
                                    if ($result_num === 6) {
                                        $repeat_type = "wa";
                                    }

                                    // Compile Error
                                    else if ($result_num === 11) {
                                        $repeat_type = "ce";
                                    }

                                    // Runtime Error
                                    else if ($result_num === 10) {
                                        $repeat_type = "re";
                                    }

                                    // 그 외 미해결
                                    else if ($result_num !== 4) {
                                        $repeat_type = "other";
                                    }
                                }
                                ?>
                                    <!-- ★ 반복 시도 표시 추가 -->
                                    <?php if ($is_repeated_attempt) { ?>

                                    <br>

                                    <?php if ($repeat_type === "ce") { ?>

                                        <span
                                            class="ui mini orange basic label"
                                            style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                            "
                                            title="컴파일 오류 상태로 5회 이상 제출했습니다."
                                        >
                                            CE 반복
                                        </span>

                                    <?php } else if ($repeat_type === "wa") { ?>

                                        <span
                                            class="ui mini red basic label"
                                            style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                            "
                                            title="틀린 답 상태로 5회 이상 제출했습니다."
                                        >
                                            WA 반복
                                        </span>

                                    <?php } else if ($repeat_type === "re") { ?>

                                        <span
                                            class="ui mini red basic label"
                                            style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                            "
                                            title="런타임 오류 상태로 5회 이상 제출했습니다."
                                        >
                                            RE 반복
                                        </span>

                                    <?php } else { ?>

                                        <span
                                            class="ui mini grey basic label"
                                            style="
                                                margin-top:4px;
                                                font-size:0.75em;
                                            "
                                            title="5회 이상 제출했지만 아직 해결되지 않았습니다."
                                        >
                                            반복 시도
                                        </span>

                                    <?php } ?>

                                <?php } ?>

                                <?php if ($problem_ai_count > 0) { ?>

                                    <br>

                                    <span
                                        class="ui mini basic label"
                                        style="
                                            margin-top:4px;
                                            font-size:0.75em;
                                        "
                                    >
                                        AI <?php echo $problem_ai_count; ?>회
                                    </span>

                            <?php } ?>

                        </a>

                    <?php

                    }
                    else {

                        echo "-";
                    }

                    ?>

                    </td>

                <?php } ?>


                <td style="text-align:center;">

                    <?php
                    echo intval(
                        $student['total_submit']
                    );
                    ?>

                </td>


                <td style="text-align:center;">

                    <?php
                    echo intval(
                        $student['total_ai']
                    );
                    ?>회

                </td>


                <td style="text-align:center;">

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
    <script>
        var currentStudentFilter =
            sessionStorage.getItem("contest_process_student_filter")
            || "all";

        var currentProblemFilter =
            sessionStorage.getItem("contest_process_problem_filter")
            || "all";

        function filterStudents(type, button){

            currentStudentFilter = type;

            sessionStorage.setItem(
                "contest_process_student_filter",
                type
            );

            var buttons =
                document.querySelectorAll(
                    "[data-filter]"
                );

            for(var j=0; j<buttons.length; j++){
                buttons[j].classList.remove("active");
            }

            if(button){
                button.classList.add("active");
            }

            applyStudentFilters();
            updateCurrentFilterInfo();
        }

        function selectProblemFilter(problemNum, button){

            currentProblemFilter = problemNum;

            sessionStorage.setItem(
                "contest_process_problem_filter",
                problemNum
            );

            var buttons =
                document.querySelectorAll(
                    ".problem-filter"
                );

            for(var i=0; i<buttons.length; i++){
                buttons[i].classList.remove("active");
            }

            if(button){
                button.classList.add("active");
            }

            applyStudentFilters();
            updateCurrentFilterInfo();
        }

        // ============================================================
        // 문제별 학급 현황 표에서 직접 필터 적용
        //
        // 예:
        // 진행 중 6명 클릭
        // → 진행 중 + 해당 문제
        //
        // 미제출 4명 클릭
        // → 미제출 + 해당 문제
        //
        // 해결 10명 클릭
        // → solved + 해당 문제
        // ============================================================

        function applySummaryFilter(studentFilter, problemNum){

            currentStudentFilter =
                studentFilter;

            currentProblemFilter =
                problemNum;


            // --------------------------------------------------------
            // 자동 새로고침 후에도 현재 필터 유지
            // --------------------------------------------------------

            sessionStorage.setItem(
                "contest_process_student_filter",
                studentFilter
            );

            sessionStorage.setItem(
                "contest_process_problem_filter",
                problemNum
            );


            // --------------------------------------------------------
            // 상단 상태 버튼 표시 갱신
            // --------------------------------------------------------

            var studentButtons =
                document.querySelectorAll(
                    "[data-filter]"
                );

            for(var i=0; i<studentButtons.length; i++){

                studentButtons[i]
                    .classList
                    .remove("active");


                if(
                    studentButtons[i]
                        .getAttribute("data-filter")
                    === studentFilter
                ){

                    studentButtons[i]
                        .classList
                        .add("active");
                }
            }


            // --------------------------------------------------------
            // 문제 버튼 표시 갱신
            // --------------------------------------------------------

            var problemButtons =
                document.querySelectorAll(
                    ".problem-filter"
                );

            for(var j=0; j<problemButtons.length; j++){

                problemButtons[j]
                    .classList
                    .remove("active");


                var clickText =
                    problemButtons[j]
                        .getAttribute("onclick")
                    || "";


                if(
                    clickText.indexOf(
                        "'" + problemNum + "'"
                    ) !== -1
                ){

                    problemButtons[j]
                        .classList
                        .add("active");
                }
            }


            // --------------------------------------------------------
            // 실제 학생 필터 적용
            // --------------------------------------------------------

            applyStudentFilters();
            updateCurrentFilterInfo();


            // --------------------------------------------------------
            // 학생별 문제 진행 현황 위치로 이동
            // --------------------------------------------------------

            var studentTable =
                document.querySelector(
                    ".student-row"
                );

            if(studentTable){

                studentTable.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }
        }

        function applyStudentFilters(){

            var rows =
                document.querySelectorAll(
                    ".student-row"
                );


            for(var i=0; i<rows.length; i++){

                var row = rows[i];


                // ====================================================
                // 학생 전체 데이터
                // ====================================================

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


                var show = true;
                
                var attentionCount =
                parseInt(
                    row.getAttribute(
                        "data-attention-count"
                    )
                ) || 0;

                // ====================================================
                // 특정 문제가 선택된 경우
                // ====================================================

                if(currentProblemFilter !== "all"){

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
                        resultAttr !== null
                            ? parseInt(resultAttr)
                            : -1;


                    // ------------------------------------------------
                    // 전체 + 특정 문제
                    //
                    // 해당 문제를 한 번이라도 제출한 학생
                    // ------------------------------------------------

                    if(currentStudentFilter === "all"){

                        show =
                            pSubmit > 0;
                    }
                    else if(currentStudentFilter === "solved"){

                        // 선택한 문제를 해결한 학생
                        show =
                            pSubmit > 0 &&
                            pResult === 4;
                    }


                    // ------------------------------------------------
                    // 진행 중 + 특정 문제
                    //
                    // 해당 문제를 제출했지만 아직 AC가 아님
                    // ------------------------------------------------

                    else if(currentStudentFilter === "working"){

                        show =
                            pSubmit > 0 &&
                            pResult !== 4;
                    }


                    // ------------------------------------------------
                    // 미제출 + 특정 문제
                    //
                    // 다른 문제의 제출 여부와 관계없이
                    // 선택한 문제를 아직 제출하지 않은 학생
                    // ------------------------------------------------

                    else if(currentStudentFilter === "nosubmit"){

                        show =
                            pSubmit === 0;
                    }


                    // ------------------------------------------------
                    // 일부 해결
                    //
                    // 현재는 학생 전체 기준 유지
                    // ------------------------------------------------

                    else if(currentStudentFilter === "partial"){

                        show =
                            solvedCount > 0 &&
                            solvedCount < problemCount;
                    }


                    // ------------------------------------------------
                    // 전체 해결
                    // ------------------------------------------------

                    else if(currentStudentFilter === "complete"){

                        show =
                            problemCount > 0 &&
                            solvedCount === problemCount;
                    }


                    // ------------------------------------------------
                    // AI 사용
                    //
                    // 현재는 학생 전체 AI 사용 기준
                    // ------------------------------------------------

                    else if(currentStudentFilter === "ai"){

                        show =
                            totalAI > 0;
                    }
                    else if(currentStudentFilter === "attention"){

                        // 선택한 문제를 5회 이상 제출했고
                        // 아직 AC가 아닌 학생
                        show =
                            pSubmit >= 5 &&
                            pResult !== 4;
                    }

                }


                // ====================================================
                // 문제를 선택하지 않은 경우
                // 학생 전체 기준 필터
                // ====================================================

                else {

                    if(currentStudentFilter === "working"){

                        // 한 번 이상 제출했지만
                        // 해결한 문제가 아직 하나도 없음
                        show =
                            totalSubmit > 0 &&
                            solvedCount === 0;

                    }

                    else if(currentStudentFilter === "partial"){

                        // 한 문제 이상 해결했지만
                        // 전체 문제를 해결하지 않음
                        show =
                            solvedCount > 0 &&
                            solvedCount < problemCount;

                    }

                    else if(currentStudentFilter === "complete"){

                        // 모든 문제 해결
                        show =
                            problemCount > 0 &&
                            solvedCount === problemCount;

                    }

                    else if(currentStudentFilter === "ai"){

                        // AI 한 번 이상 사용
                        show =
                            totalAI > 0;

                    }
                    else if(currentStudentFilter === "attention"){

                        show =
                            attentionCount > 0;

                    }

                    else if(currentStudentFilter === "nosubmit"){

                        // 대회 전체에서 한 번도 제출하지 않음
                        show =
                            totalSubmit === 0;

                    }
                    

                    else {

                        // 전체
                        show = true;
                    }
                }


                // ====================================================
                // 화면 표시
                // ====================================================

                row.style.display =
                    show ? "" : "none";
            }
        }
        // ============================================================
        // 저장된 필터 복원
        // ============================================================

        document.addEventListener("DOMContentLoaded", function(){

            // 학생 상태 필터 버튼 복원
            var studentButtons =
                document.querySelectorAll(
                    "[data-filter]"
                );

            for(var i=0; i<studentButtons.length; i++){

                var btn =
                    studentButtons[i];

                btn.classList.remove("active");

                if(
                    btn.getAttribute("data-filter")
                    === currentStudentFilter
                ){
                    btn.classList.add("active");
                }
            }


            // 문제 선택 버튼 복원
            var problemButtons =
                document.querySelectorAll(
                    ".problem-filter"
                );

            for(var j=0; j<problemButtons.length; j++){

                problemButtons[j].classList.remove(
                    "active"
                );
            }


            if(currentProblemFilter === "all"){

                // 첫 번째 문제 필터가 전체 버튼
                if(problemButtons.length > 0){
                    problemButtons[0].classList.add(
                        "active"
                    );
                }

            }
            else {

                // onclick 내용에서 해당 문제 번호를 찾아 활성화
                for(var k=0; k<problemButtons.length; k++){

                    var clickText =
                        problemButtons[k]
                            .getAttribute("onclick")
                            || "";

                    if(
                        clickText.indexOf(
                            "'" +
                            currentProblemFilter +
                            "'"
                        ) !== -1
                    ){
                        problemButtons[k]
                            .classList.add("active");
                    }
                }
            }


            // 저장된 조건 실제 적용
            applyStudentFilters();
            updateCurrentFilterInfo();

        });
        
        function updateCurrentFilterInfo(){

            var info =
                document.getElementById(
                    "current-filter-info"
                );

            if(!info){
                return;
            }


            var studentText = "";

            if(currentStudentFilter === "all"){
                studentText = "전체";
            }
            else if(currentStudentFilter === "working"){
                studentText = "진행 중";
            }
            else if(currentStudentFilter === "partial"){
                studentText = "일부 해결";
            }
            else if(currentStudentFilter === "complete"){
                studentText = "전체 해결";
            }
            else if(currentStudentFilter === "ai"){
                studentText = "AI 사용";
            }
            else if(currentStudentFilter === "attention"){
                studentText = "확인 필요";
            }
            else if(currentStudentFilter === "nosubmit"){
                studentText = "미제출";
            }
            else if(currentStudentFilter === "solved"){
                studentText = "해결";
            }


            var problemText = "전체 문제";

            if(currentProblemFilter !== "all"){

                var problemButtons =
                    document.querySelectorAll(
                        ".problem-filter"
                    );

                for(var i=0; i<problemButtons.length; i++){

                    var clickText =
                        problemButtons[i]
                            .getAttribute("onclick")
                        || "";

                    if(
                        clickText.indexOf(
                            "'" +
                            currentProblemFilter +
                            "'"
                        ) !== -1
                    ){

                        problemText =
                            problemButtons[i]
                                .innerText
                                .trim();

                        break;
                    }
                }
            }


            // 기본 상태는 숨김
            if(
                currentStudentFilter === "all" &&
                currentProblemFilter === "all"
            ){

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
        setInterval(function(){

            window.location.reload();

        }, 30000);
        </script>

    </div>

    <h2 class="ui dividing header">
        학생 문제 해결 과정 현황
    </h2>


    <div class="ui segment">

        <strong>대회</strong>

        &nbsp;

        <?php
        echo intval($cid);
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


    <?php if (count($view_process_list) == 0) { ?>

        <div class="ui message">
            기록된 학생 과정이 없습니다.
        </div>

    <?php } else { ?>


        <table
            class="ui celled compact table"
        >

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

            <?php foreach ($view_process_list as $item) { ?>

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
                                $PID[
                                    $item['problem_num']
                                ]
                            )
                        ) {

                            echo htmlentities(
                                $PID[
                                    $item['problem_num']
                                ],
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
                                $judge_result[
                                    $result_num
                                ]
                            )
                        ) {

                            echo htmlentities(
                                $judge_result[
                                    $result_num
                                ],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                        }
                        else {

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

                            echo $ai_count."회";

                        }
                        else {

                            echo "미사용";
                        }

                        ?>

                    </td>


                    <td>

                        <?php

                        if (
                            intval(
                                $item['has_plan']
                            ) == 1
                        ) {

                            echo "작성";

                        }
                        else {

                            echo "-";
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
                        >
                            과정
                        </a>

                    </td>

                </tr>

            <?php } ?>

            </tbody>

        </table>


    <?php } ?>


    <div
        style="
            text-align:center;
            margin-top:25px;
        "
    >

        <a
            href="status.php?cid=<?php echo intval($cid); ?>"
            class="ui button"
        >
            대회 Status
        </a>

    </div>

</div>


<?php

include(
    "template/$OJ_TEMPLATE/footer.php"
);

?>