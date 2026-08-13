<?php
// AJAX 전용 상단 요약
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

                <?php

                $solved_count =
                    intval(
                        $summary[
                            'solved'
                        ]
                    );

                $working_count =
                    intval(
                        $summary[
                            'working'
                        ]
                    );

                $nosubmit_count =
                    intval(
                        $summary[
                            'nosubmit'
                        ]
                    );


                $solved_percent =
                    $total_student_count > 0
                        ? round(
                            $solved_count /
                            $total_student_count *
                            100
                        )
                        : 0;


                $working_percent =
                    $total_student_count > 0
                        ? round(
                            $working_count /
                            $total_student_count *
                            100
                        )
                        : 0;


                $nosubmit_percent =
                    $total_student_count > 0
                        ? round(
                            $nosubmit_count /
                            $total_student_count *
                            100
                        )
                        : 0;

                ?>


                <tr>

                    <td class="center aligned">

                        <strong>

                            <?php
                            echo htmlentities(
                                $summary[
                                    'label'
                                ],
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
                                $summary[
                                    'problem_id'
                                ]
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
                                '<?php
                                echo intval(
                                    $problem_num
                                );
                                ?>'
                            );"
                        >

                            <?php
                            echo $solved_count;
                            ?>명

                            <span
                                style="
                                    font-weight:normal;
                                "
                            >
                                (<?php
                                echo $solved_percent;
                                ?>%)
                            </span>

                        </a>

                    </td>


                    <!-- 진행 중 -->

                    <td class="center aligned">

                        <?php if (
                            $working_count > 0
                        ) { ?>

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
                            >

                                <?php
                                echo $working_count;
                                ?>명

                                <span
                                    style="
                                        font-weight:normal;
                                    "
                                >
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

                        <?php if (
                            $nosubmit_count > 0
                        ) { ?>

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
                            >

                                <?php
                                echo $nosubmit_count;
                                ?>명

                                <span
                                    style="
                                        font-weight:normal;
                                    "
                                >
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
                            $summary[
                                'total_submit'
                            ]
                        );
                        ?>회

                    </td>


                    <!-- AI -->

                    <td class="center aligned">

                        <?php
                        echo intval(
                            $summary[
                                'ai_students'
                            ]
                        );
                        ?>명

                    </td>

                </tr>

            <?php } ?>


            </tbody>

        </table>

    </div>

</div>