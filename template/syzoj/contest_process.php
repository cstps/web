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

            <tr
                class="student-row"

                data-total-submit="<?php
                    echo intval($student['total_submit']);
                ?>"

                data-total-ai="<?php
                    echo intval($student['total_ai']);
                ?>"

                data-solved-count="<?php
                    echo intval($student['solved_count']);
                ?>"

                data-problem-count="<?php
                    echo count($contest_problems);
                ?>"

                <?php foreach ($contest_problems as $problem_num => $problem) {

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

                    data-problem-<?php echo intval($problem_num); ?>-submit="<?php
                        echo $problem_submit;
                    ?>"

                    data-problem-<?php echo intval($problem_num); ?>-result="<?php
                        echo $problem_result;
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
                                ?>

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
        var currentStudentFilter = "all";
        var currentProblemFilter = "all";

        function filterStudents(type, button){

            currentStudentFilter = type;

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
        }

        function selectProblemFilter(problemNum, button){

            currentProblemFilter = problemNum;

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