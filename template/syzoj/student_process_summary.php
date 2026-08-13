<?php

$show_title =
    "학생 문제 해결 과정 요약 - $OJ_NAME";

include(
    "template/$OJ_TEMPLATE/header.php"
);

?>

<div
    class="ui container"
    style="
        max-width:1100px;
        margin-top:25px;
        margin-bottom:40px;
    "
>


    <!-- ======================================================
         제목
         ====================================================== -->

    <h2 class="ui dividing header">

        학생 문제 해결 과정 요약

    </h2>


    <!-- ======================================================
         학생 / 대회 정보
         ====================================================== -->

    <div class="ui segment">

        <strong>학생</strong>

        &nbsp;

        <?php
        echo htmlentities(
            $student_user_id,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>


        <?php if ($student_nick !== "") { ?>

            &nbsp;

            <span style="color:#777;">

                (<?php
                echo htmlentities(
                    $student_nick,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>)

            </span>

        <?php } ?>


        &nbsp;&nbsp;&nbsp;


        <strong>대회</strong>

        &nbsp;

        <?php echo intval($cid); ?>

        &nbsp;

        <?php
        echo htmlentities(
            $contest_title,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>


    <!-- ======================================================
         전체 요약
         ====================================================== -->

    <div
        class="ui four statistics"
        style="
            margin-top:25px;
            margin-bottom:30px;
        "
    >


        <!-- 해결 -->

        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $solved_count
                );
                ?>

                /

                <?php
                echo intval(
                    $total_problem_count
                );
                ?>

            </div>

            <div class="label">
                해결 문제
            </div>

        </div>


        <!-- 제출 -->

        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $total_submit_count
                );
                ?>

            </div>

            <div class="label">
                총 제출
            </div>

        </div>


        <!-- AI -->

        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $total_ai_count
                );
                ?>

            </div>

            <div class="label">
                AI 활용
            </div>

        </div>


        <!-- 확인 필요 -->

        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $attention_problem_count
                );
                ?>

            </div>

            <div class="label">
                확인 필요 문제
            </div>

        </div>

    </div>


    <!-- ======================================================
         추가 요약
         ====================================================== -->

    <div class="ui message">

        <strong>
            최초 계획 작성
        </strong>

        &nbsp;

        <?php
        echo intval(
            $plan_problem_count
        );
        ?>

        /

        <?php
        echo intval(
            $total_problem_count
        );
        ?>

        문제


        &nbsp;&nbsp;&nbsp;


        <strong>
            AI 활용 문제
        </strong>

        &nbsp;
        
        <?php
        echo intval(
            $ai_problem_count
        );
        ?>

        /

        <?php
        echo intval(
            $total_problem_count
        );
        ?>

        문제

        &nbsp;&nbsp;&nbsp;

        <strong>
            평균 해결 시도
        </strong>

        &nbsp;

        <?php if ($solved_attempt_count > 0) { ?>

            <?php
            echo number_format(
                $average_solved_attempt,
                1
            );
            ?>회

        <?php } else { ?>

            -

        <?php } ?>

    </div>


    <!-- ======================================================
         문제별 현황
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:30px;"
    >

        문제별 진행 현황

    </h3>


    <div style="overflow-x:auto;">

        <table
            class="ui celled compact table"
        >

            <thead>

                <tr>

                    <th class="center aligned">
                        문제
                    </th>

                    <th class="center aligned">
                        과정 흐름
                    </th>

                    <th class="center aligned">
                        최종 결과
                    </th>

                    <th class="center aligned">
                        제출
                    </th>

                    <th class="center aligned"
                        style="
                        min-width:75px;
                        white-space:nowrap;
                    ">
                        AI
                    </th>

                    <th class="center aligned">
                        최초 계획
                    </th>

                    <th class="center aligned">
                        상태
                    </th>

                    <th class="center aligned"
                        style="
                        width:8%;
                        min-width:70px;
                        white-space:nowrap;
                        "
                    >
                        과정
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php
            foreach (
                $student_problem_summary
                as
                $problem_num => $problem
            ) {
            ?>

                <tr>


                    <!-- 문제 -->

                    <td>

                        <strong>

                            <?php
                            echo htmlentities(
                                $problem['label'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </strong>

                        &nbsp;

                        <a
                            href="problem.php?id=<?php
                                echo intval(
                                    $problem['problem_id']
                                );
                            ?>"
                        >

                            <?php
                            echo intval(
                                $problem['problem_id']
                            );
                            ?>

                        </a>


                        <?php
                        if (
                            isset(
                                $problem['title']
                            ) &&
                            $problem['title'] !== ""
                        ) {
                        ?>

                            <br>

                            <span
                                style="
                                    font-size:0.85em;
                                    color:#777;
                                "
                            >

                                <?php
                                echo htmlentities(
                                    $problem['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </span>

                        <?php } ?>

                    </td>

                    <!-- 과정 흐름 -->

                    <td class="center aligned">

                        <?php

                        $result_history =
                            isset(
                                $problem['result_history']
                            )
                                ? $problem['result_history']
                                : array();


                        if (
                            count(
                                $result_history
                            ) == 0
                        ) {

                            echo
                                "<span style='color:#999;'>-</span>";

                        }
                        else {

                            $history_labels =
                                array();


                            $ai_history =
                                isset(
                                    $problem['ai_history']
                                )
                                    ? $problem['ai_history']
                                    : array();


                            $history_labels =
                                array();


                            foreach (
                                $result_history
                                as
                                $index => $history_result
                            ) {

                                $history_result =
                                    intval(
                                        $history_result
                                    );


                                $history_label =
                                    "-";

                                $history_class =
                                    "grey";


                                switch ($history_result) {

                                    case 4:

                                        $history_label =
                                            "AC";

                                        $history_class =
                                            "green";

                                        break;


                                    case 5:

                                        $history_label =
                                            "PE";

                                        $history_class =
                                            "orange";

                                        break;


                                    case 6:

                                        $history_label =
                                            "WA";

                                        $history_class =
                                            "red";

                                        break;


                                    case 7:

                                        $history_label =
                                            "TLE";

                                        $history_class =
                                            "orange";

                                        break;


                                    case 8:

                                        $history_label =
                                            "MLE";

                                        $history_class =
                                            "orange";

                                        break;


                                    case 9:

                                        $history_label =
                                            "OLE";

                                        $history_class =
                                            "orange";

                                        break;


                                    case 10:

                                        $history_label =
                                            "RE";

                                        $history_class =
                                            "red";

                                        break;


                                    case 11:

                                        $history_label =
                                            "CE";

                                        $history_class =
                                            "orange";

                                        break;
                                }


                                // ========================================================
                                // 해당 제출에서 AI 사용 여부
                                // ========================================================

                                $used_ai = false;

                                if (
                                    isset(
                                        $ai_history[$index]
                                    )
                                ) {

                                    $used_ai =
                                        intval(
                                            $ai_history[$index]['ai_used']
                                        ) === 1;
                                }


                                // ========================================================
                                // 결과 라벨 생성
                                //
                                // AI 사용 시:
                                // WA AI
                                // CE AI
                                // AC AI
                                // ========================================================

                                $label_text =
                                    $history_label;

                                if ($used_ai) {

                                    $label_text .= " · AI";
                                }


                                $history_labels[] =
                                    "<span class='ui mini ".
                                    $history_class.
                                    " basic label' ".
                                    "style='white-space:nowrap;'>".
                                    htmlentities(
                                        $label_text,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ).
                                    "</span>";
                            }


                            echo
                                implode(
                                    " → ",
                                    $history_labels
                                );
                        }

                        ?>
                        <?php

                        $solved_at_attempt =
                            isset(
                                $problem[
                                    'solved_at_attempt'
                                ]
                            )
                                ? intval(
                                    $problem[
                                        'solved_at_attempt'
                                    ]
                                )
                                : 0;


                        if (
                            $solved_at_attempt > 0
                        ) {

                        ?>

                            <div
                                style="
                                    margin-top:6px;
                                    font-size:0.85em;
                                    color:#666;
                                "
                            >
                                <?php
                                echo $solved_at_attempt;
                                ?>회 만에 해결
                            </div>

                        <?php
                        }
                        else if (
                            count(
                                $result_history
                            ) > 0
                        ) {
                        ?>

                            <div
                                style="
                                    margin-top:6px;
                                    font-size:0.85em;
                                    color:#999;
                                "
                            >
                                아직 진행 중
                            </div>

                        <?php
                        }
                        ?>
                    </td>
                    <!-- 최종 결과 -->

                    <td class="center aligned">

                        <?php

                        $result_num =
                            intval(
                                $problem[
                                    'latest_result'
                                ]
                            );


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
                        }

                        ?>


                        <?php
                        if (
                            intval(
                                $problem[
                                    'submit_count'
                                ]
                            ) == 0
                        ) {
                        ?>

                            <span style="color:#999;">
                                미제출
                            </span>

                        <?php } else { ?>

                            <span
                                class="ui <?php
                                    echo $result_class;
                                ?> basic label"
                            >

                                <?php
                                echo $result_label;
                                ?>

                            </span>

                        <?php } ?>

                    </td>


                    <!-- 제출 -->

                    <td class="center aligned">

                        <?php
                        echo intval(
                            $problem[
                                'submit_count'
                            ]
                        );
                        ?>회

                    </td>


                    <!-- AI -->

                    <td 
                        class="center aligned"
                        style="
                            min-width:75px;
                            white-space:nowrap;
                        "
                    >

                        <?php

                        $problem_ai_count =
                            intval(
                                $problem[
                                    'ai_count'
                                ]
                            );


                        if (
                            $problem_ai_count > 0
                        ) {

                            echo
                                $problem_ai_count.
                                "회";

                        }
                        else {

                            echo
                                "미사용";
                        }

                        ?>
                        <?php

                            $ai_history =
                                isset(
                                    $problem['ai_history']
                                )
                                    ? $problem['ai_history']
                                    : array();


                            $ai_attempt_numbers =
                                array();


                            foreach (
                                $ai_history
                                as
                                $index => $ai_item
                            ) {

                                if (
                                    intval(
                                        $ai_item['ai_used']
                                    ) === 1
                                ) {

                                    $ai_attempt_numbers[] =
                                        intval($index) + 1;
                                }
                            }


                            if (
                                count(
                                    $ai_attempt_numbers
                                ) > 0
                            ) {
                            ?>

                                <div
                                    style="
                                        margin-top:5px;
                                        font-size:0.8em;
                                        color:#777;
                                        white-space:nowrap;
                                    "
                                >

                                    <?php
                                    echo implode(
                                        ", ",
                                        $ai_attempt_numbers
                                    );
                                    ?>차 제출

                                </div>

                            <?php } ?>

                    </td>


                    <!-- 최초 계획 -->

                    <td class="center aligned">

                        <?php
                        if (
                            intval(
                                $problem[
                                    'has_plan'
                                ]
                            ) === 1
                        ) {
                        ?>

                            <span
                                class="ui green basic label"
                            >
                                작성
                            </span>

                        <?php } else { ?>

                            <span style="color:#999;">
                                -
                            </span>

                        <?php } ?>

                    </td>


                    <!-- 상태 -->

                    <td class="center aligned">

                        <?php
                        if (
                            $problem[
                                'attention'
                            ]
                        ) {

                            $repeat_type =
                                $problem[
                                    'repeat_type'
                                ];


                            if (
                                $repeat_type ===
                                "ce"
                            ) {
                        ?>

                                <span
                                    class="ui orange basic label"
                                >
                                    CE 반복
                                </span>

                        <?php
                            }
                            else if (
                                $repeat_type ===
                                "wa"
                            ) {
                        ?>

                                <span
                                    class="ui red basic label"
                                >
                                    WA 반복
                                </span>

                        <?php
                            }
                            else if (
                                $repeat_type ===
                                "re"
                            ) {
                        ?>

                                <span
                                    class="ui red basic label"
                                >
                                    RE 반복
                                </span>

                        <?php
                            }
                            else {
                        ?>

                                <span
                                    class="ui grey basic label"
                                >
                                    반복 시도
                                </span>

                        <?php
                            }

                        }
                        else if (
                            $result_num === 4
                        ) {
                        ?>

                            <span
                                class="ui green basic label"
                            >
                                해결
                            </span>

                        <?php
                        }
                        else if (
                            intval(
                                $problem[
                                    'submit_count'
                                ]
                            ) > 0
                        ) {
                        ?>

                            <span
                                class="ui orange basic label"
                            >
                                진행 중
                            </span>

                        <?php
                        }
                        else {
                        ?>

                            <span style="color:#999;">
                                -
                            </span>

                        <?php } ?>

                    </td>


                    <!-- 과정 -->

                    <td class="center aligned"
                        style="
                            min-width:70px;
                            white-space:nowrap;
                        "
                    >

                        <?php
                        if (
                            intval(
                                $problem[
                                    'latest_solution_id'
                                ]
                            ) > 0
                        ) {
                        ?>

                            <a
                                href="solution_process_view.php?sid=<?php
                                    echo intval(
                                        $problem[
                                            'latest_solution_id'
                                        ]
                                    );
                                ?>"
                                class="ui mini basic button"
                                style="white-space:nowrap;"
                            >
                                과정
                            </a>

                        <?php } else { ?>

                            -

                        <?php } ?>

                    </td>


                </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>


    <!-- ======================================================
         하단 이동
         ====================================================== -->

    <div
        style="
            text-align:center;
            margin-top:30px;
        "
    >

        <a
            href="contest_process.php?cid=<?php
                echo intval($cid);
            ?>"
            class="ui button"
        >
            전체 과정 현황
        </a>


        <a
            href="status.php?cid=<?php
                echo intval($cid);
            ?>&user_id=<?php
                echo urlencode(
                    $student_user_id
                );
            ?>"
            class="ui basic button"
        >
            학생 제출 기록
        </a>

    </div>


</div>


<?php

include(
    "template/$OJ_TEMPLATE/footer.php"
);

?>