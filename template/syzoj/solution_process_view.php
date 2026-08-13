<?php

$show_title =
    "문제 해결 과정 - $OJ_NAME";

include(
    "template/$OJ_TEMPLATE/header.php"
);

?>

<div
    class="ui container"
    style="
        max-width:1000px;
        margin-top:25px;
        margin-bottom:40px;
    "
>


    <!-- ======================================================
         제목
         ====================================================== -->

    <h2 class="ui dividing header">

        문제 해결 과정

    </h2>


    <!-- ======================================================
         학생 / 문제 기본 정보
         ====================================================== -->

    <div class="ui segment">

        <div class="ui relaxed horizontal list">

            <div class="item">

                <strong>학생</strong>

                &nbsp;

                <?php
                echo htmlentities(
                    $solution_user_id,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>


            <div class="item">

                <strong>문제</strong>

                &nbsp;

                <a href="problem.php?id=<?php echo $problem_id; ?>">

                    <?php echo $problem_id; ?>

                </a>

            </div>


            <?php if ($contest_id > 0) { ?>

                <div class="item">

                    <strong>대회</strong>

                    &nbsp;

                    <a href="contest.php?cid=<?php echo $contest_id; ?>">

                        <?php echo $contest_id; ?>

                    </a>

                </div>

            <?php } ?>


            <div class="item">

                <strong>총 제출</strong>

                &nbsp;

                <?php echo intval($process_count); ?>회

            </div>

        </div>

    </div>


    <!-- ======================================================
         과정 기록이 없는 경우
         ====================================================== -->

    <?php if ($process_count == 0) { ?>


        <div class="ui message">

            기록된 문제 해결 과정이 없습니다.

        </div>


    <?php } else { ?>


        <!-- ==================================================
             제출 과정
             ================================================== -->

        <?php

        $submit_number = 0;

        foreach ($process_result as $process) {

            $submit_number++;

            $process_sid =
                intval(
                    $process['solution_id']
                );
            
            $source_diff =
                isset(
                    $process_diff_map[
                        $process_sid
                    ]
                )
                    ? $process_diff_map[
                        $process_sid
                    ]
                    : null;

            $result_num =
                intval(
                    $process['result']
                );

            $is_selected =
                ($process_sid == $sid);


            $plan_text =
                isset($process['plan_text'])
                    ? trim($process['plan_text'])
                    : "";


            $reflection =
                isset($process['reflection'])
                    ? trim($process['reflection'])
                    : "";


            $ai_used =
                isset($process['ai_used'])
                    ? intval($process['ai_used'])
                    : 0;


            $ai_type =
                isset($process['ai_usage_type'])
                    ? trim($process['ai_usage_type'])
                    : "none";


            $ai_prompt =
                isset($process['ai_prompt'])
                    ? trim($process['ai_prompt'])
                    : "";

        ?>


        <div
            class="ui segment"
            <?php
            if ($is_selected) {
                echo 'style="border:2px solid #777;"';
            }
            ?>
        >


            <!-- ==============================================
                 제출 제목
                 ============================================== -->

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    flex-wrap:wrap;
                    margin-bottom:15px;
                "
            >

                <div>

                    <span
                        class="ui large circular label"
                    >

                        <?php echo $submit_number; ?>

                    </span>


                    <strong style="font-size:1.1em;">

                        <?php echo $submit_number; ?>차 제출

                    </strong>


                    <?php if ($is_selected) { ?>

                        <span class="ui tiny label">

                            선택한 제출

                        </span>

                    <?php } ?>

                </div>


                <div style="color:#777;">

                    <?php

                    echo htmlentities(
                        $process['in_date'],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>

                </div>

            </div>


            <!-- ==============================================
                 채점 결과
                 ============================================== -->

            <div style="margin-bottom:15px;">

                <strong>채점 결과</strong>

                &nbsp;

                <?php

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

                    echo "Result ".
                        $result_num;
                }

                ?>

            </div>


            <!-- ==============================================
                 풀이 계획
                 ============================================== -->

            <?php if ($plan_text != "") { ?>


                <div
                    class="ui secondary segment"
                    style="margin-bottom:15px;"
                >

                    <strong>
                        풀이 계획
                    </strong>


                    <div
                        style="
                            margin-top:8px;
                            white-space:pre-wrap;
                            line-height:1.6;
                        "
                    ><?php

                        echo htmlentities(
                            $plan_text,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    ?></div>

                </div>


            <?php } ?>


            <!-- ==============================================
                 재제출 수정 사유
                 ============================================== -->

            <?php if ($reflection != "") { ?>


                <div style="margin-bottom:15px;">

                    <strong>
                        수정 내용
                    </strong>


                    <div
                        style="
                            margin-top:6px;
                            white-space:pre-wrap;
                        "
                    ><?php

                        echo htmlentities(
                            $reflection,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    ?></div>

                </div>


            <?php } ?>
            
            <!-- ======================================================
                실제 코드 변화
                ====================================================== -->

            <?php if ($source_diff !== null) { ?>

                <div
                    class="ui segment"
                    style="
                        margin-top:15px;
                        margin-bottom:15px;
                    "
                >

                    <strong>
                        실제 코드 변화
                    </strong>


                    <?php if ($source_diff['too_large']) { ?>

                        <div style="margin-top:8px; color:#777;">

                            코드가 커서 상세 변경 비교는 생략했습니다.

                        </div>

                    <?php } else { ?>


                        <div style="margin-top:8px;">

                            이전 제출

                            <strong>
                                #<?php
                                echo intval(
                                    $source_diff[
                                        'previous_solution_id'
                                    ]
                                );
                                ?>
                            </strong>

                            대비

                            &nbsp;&nbsp;


                            <span class="ui tiny green basic label">

                                +<?php
                                echo intval(
                                    $source_diff['added']
                                );
                                ?>줄

                            </span>


                            <span class="ui tiny red basic label">

                                -<?php
                                echo intval(
                                    $source_diff['deleted']
                                );
                                ?>줄

                            </span>

                        </div>


                        <?php if (!$source_diff['changed']) { ?>

                            <div style="margin-top:8px; color:#777;">

                                실제 코드 변경 없음

                            </div>

                        <?php } else { ?>


                            <details style="margin-top:12px;">

                                <summary
                                    style="
                                        cursor:pointer;
                                        font-weight:bold;
                                    "
                                >
                                    변경 코드 보기
                                </summary>


                                <pre
                                    style="
                                        margin-top:10px;
                                        padding:12px;
                                        background:#f8f8f8;
                                        overflow-x:auto;
                                        line-height:1.5;
                                    "
                                ><?php

                                foreach (
                                    $source_diff['diff_lines']
                                    as
                                    $diff_line
                                ) {

                                    $type =
                                        $diff_line['type'];

                                    $text =
                                        htmlentities(
                                            $diff_line['text'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );


                                    if ($type === 'add') {

                                        echo '<span style="color:#167d35;">+ '.
                                            $text.
                                            "</span>\n";

                                    }
                                    else if ($type === 'delete') {

                                        echo '<span style="color:#b21e2b;">- '.
                                            $text.
                                            "</span>\n";

                                    }
                                    else {

                                        echo '  '.$text."\n";
                                    }
                                }

                                ?></pre>

                            </details>

                        <?php } ?>

                    <?php } ?>

                </div>

            <?php } ?>

            <!-- ==============================================
                 AI 활용
                 ============================================== -->

            <div style="margin-bottom:10px;">

                <strong>
                    생성형 AI
                </strong>

                &nbsp;


                <?php if ($ai_used == 1) { ?>


                    <?php

                    if (
                        isset(
                            $ai_usage_names[
                                $ai_type
                            ]
                        )
                    ) {

                        echo htmlentities(
                            $ai_usage_names[
                                $ai_type
                            ],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    }
                    else {

                        echo htmlentities(
                            $ai_type,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    }

                    ?>


                <?php } else { ?>

                    사용하지 않음

                <?php } ?>

            </div>


            <!-- ==============================================
                 AI 질문
                 ============================================== -->

            <?php

            if (
                $ai_used == 1 &&
                $ai_prompt != ""
            ) {

            ?>


                <div
                    style="
                        margin-top:10px;
                        padding:12px;
                        background:#fafafa;
                        border-left:3px solid #ddd;
                    "
                >

                    <strong>
                        AI에 입력한 내용
                    </strong>


                    <div
                        style="
                            margin-top:7px;
                            white-space:pre-wrap;
                            line-height:1.6;
                        "
                    ><?php

                        echo htmlentities(
                            $ai_prompt,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    ?></div>

                </div>


            <?php } ?>


            <!-- ==============================================
                 제출 상세 정보
                 ============================================== -->

            <div
                style="
                    margin-top:18px;
                    padding-top:10px;
                    border-top:1px solid #eee;
                    color:#777;
                    font-size:0.9em;
                "
            >

                Solution ID:
                <?php echo $process_sid; ?>


                &nbsp;&nbsp;


                코드:
                <?php
                echo intval(
                    $process['code_length']
                );
                ?> bytes


                &nbsp;&nbsp;


                실행시간:
                <?php
                echo intval(
                    $process['time']
                );
                ?> ms


                &nbsp;&nbsp;


                메모리:
                <?php
                echo intval(
                    $process['memory']
                );
                ?> KB

            </div>


        </div>


        <?php } ?>


    <?php } ?>

<?php if (
    isset($can_manage_teacher_note) &&
    $can_manage_teacher_note
) { ?>

    <h3
        class="ui dividing header"
        style="margin-top:30px;"
    >
        교사 관찰 메모
    </h3>


    <!-- ======================================================
         신규 관찰 메모 작성
         ====================================================== -->

    <div class="ui segment">

        <form
            id="teacher-note-create-form"
            class="ui form"
            method="post"
            action="teacher_process_note_save.php"
        >

            <input
                type="hidden"
                name="cid"
                value="<?php echo intval($contest_id); ?>"
            >

            <input
                type="hidden"
                name="user_id"
                value="<?php
                    echo htmlentities(
                        $solution_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >

            <input
                type="hidden"
                name="problem_id"
                value="<?php
                    echo intval(
                        $problem_id
                    );
                ?>"
            >

            <input
                type="hidden"
                name="sid"
                value="<?php
                    echo intval(
                        $sid
                    );
                ?>"
            >


            <div class="field">

                <label>
                    관찰 내용
                </label>

                <textarea
                    name="note_text"
                    rows="4"
                    maxlength="2000"
                    placeholder="학생의 문제 해결 과정에서 관찰한 내용을 기록하세요."
                    required
                ></textarea>

            </div>


            <?php
            // ====================================================
            // POST 보안키는 이 페이지에서 한 번만 생성
            //
            // 수정/삭제 폼에서는 JavaScript로 이 값을 복사한다.
            // ====================================================

            require_once(
                "./include/set_post_key.php"
            );
            ?>


            <button
                type="submit"
                class="ui primary button"
            >
                메모 저장
            </button>

        </form>

    </div>


    <!-- ======================================================
         기존 관찰 메모 목록
         ====================================================== -->

    <?php if (
        isset($teacher_notes) &&
        count($teacher_notes) > 0
    ) { ?>

        <div class="ui relaxed divided list">

            <?php foreach ($teacher_notes as $note) { ?>

                <?php

                // =================================================
                // 현재 로그인 교사
                // =================================================

                $current_teacher_id =
                    isset(
                        $_SESSION[
                            $OJ_NAME.'_user_id'
                        ]
                    )
                        ? $_SESSION[
                            $OJ_NAME.'_user_id'
                        ]
                        : "";


                // =================================================
                // 이 메모를 작성한 교사인지 확인
                // =================================================

                $is_note_owner =
                    (
                        strcasecmp(
                            $current_teacher_id,
                            $note['teacher_id']
                        ) === 0
                    );


                // =================================================
                // 수정 / 삭제 권한
                //
                // administrator
                // 또는
                // 실제 작성자
                // =================================================

                $can_edit_this_note =
                    (
                        $is_note_admin ||
                        $is_note_owner
                    );

                ?>


                <div class="item">

                    <div class="content">


                        <!-- =========================================
                             작성자 / 작성시간
                             ========================================= -->

                        <div class="header">

                            <?php
                            echo htmlentities(
                                $note['teacher_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            &nbsp;

                            <span
                                style="
                                    font-weight:normal;
                                    color:#888;
                                    font-size:0.85em;
                                "
                            >

                                <?php
                                echo htmlentities(
                                    $note['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </span>


                            <?php
                            // =====================================
                            // 수정된 메모 표시
                            // =====================================

                            if (
                                isset(
                                    $note['updated_at']
                                ) &&
                                $note['updated_at'] != ""
                            ) {
                            ?>

                                <span
                                    style="
                                        margin-left:6px;
                                        color:#999;
                                        font-size:0.8em;
                                        font-weight:normal;
                                    "
                                >
                                    (수정됨)
                                </span>

                            <?php } ?>

                        </div>


                        <!-- =========================================
                             메모 내용
                             ========================================= -->

                        <div
                            class="description"
                            style="
                                margin-top:6px;
                                white-space:pre-wrap;
                                line-height:1.6;
                            "
                        >
                            <?php
                            echo htmlentities(
                                $note['note_text'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </div>


                        <!-- =========================================
                             수정
                             ========================================= -->

                        <?php if ($can_edit_this_note) { ?>

                            <div style="margin-top:10px;">

                                <details>

                                    <summary
                                        style="
                                            cursor:pointer;
                                            display:inline-block;
                                            color:#2185d0;
                                        "
                                    >
                                        수정
                                    </summary>


                                    <form
                                        class="ui form teacher-note-action-form"
                                        method="post"
                                        action="teacher_process_note_action.php"
                                        style="
                                            margin-top:10px;
                                            max-width:800px;
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >


                                        <input
                                            type="hidden"
                                            name="note_id"
                                            value="<?php
                                                echo intval(
                                                    $note['note_id']
                                                );
                                            ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="sid"
                                            value="<?php
                                                echo intval(
                                                    $sid
                                                );
                                            ?>"
                                        >


                                        <div class="field">

                                            <textarea
                                                name="note_text"
                                                rows="3"
                                                maxlength="2000"
                                                required
                                            ><?php
                                                echo htmlentities(
                                                    $note['note_text'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?></textarea>

                                        </div>


                                        <!--
                                            중요:
                                            여기서는
                                            set_post_key.php를
                                            다시 호출하지 않는다.

                                            페이지 하단 JavaScript가
                                            신규 메모 폼의 POST 키를
                                            이 폼으로 복사한다.
                                        -->


                                        <button
                                            type="submit"
                                            class="ui mini blue button"
                                        >
                                            수정 저장
                                        </button>

                                    </form>

                                </details>

                            </div>

                        <?php } ?>


                        <!-- =========================================
                             삭제
                             ========================================= -->

                        <?php if ($can_edit_this_note) { ?>

                            <form
                                class="teacher-note-action-form"
                                method="post"
                                action="teacher_process_note_action.php"
                                style="
                                    display:inline-block;
                                    margin-top:8px;
                                "
                                onsubmit="
                                    return confirm(
                                        '이 관찰 메모를 삭제하시겠습니까?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >


                                <input
                                    type="hidden"
                                    name="note_id"
                                    value="<?php
                                        echo intval(
                                            $note['note_id']
                                        );
                                    ?>"
                                >


                                <input
                                    type="hidden"
                                    name="sid"
                                    value="<?php
                                        echo intval(
                                            $sid
                                        );
                                    ?>"
                                >


                                <!--
                                    중요:
                                    삭제 폼에서도
                                    set_post_key.php를
                                    다시 호출하지 않는다.
                                -->


                                <button
                                    type="submit"
                                    class="ui mini red basic button"
                                >
                                    삭제
                                </button>

                            </form>

                        <?php } ?>


                    </div>

                </div>

            <?php } ?>

        </div>


    <?php } else { ?>

        <div class="ui small message">

            작성된 관찰 메모가 없습니다.

        </div>

    <?php } ?>


<?php } ?>


<!-- ============================================================
     관찰 메모 수정/삭제 폼에 POST 보안키 복사
     
     set_post_key.php는 위 신규 메모 폼에서 한 번만 실행한다.
     그 결과 생성된 hidden input을 수정/삭제 폼에 복사한다.
     ============================================================ -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function(){

        // ====================================================
        // POST 키가 생성된 신규 메모 폼
        // ====================================================

        var sourceForm =
            document.getElementById(
                "teacher-note-create-form"
            );


        if(!sourceForm){

            return;
        }


        // ====================================================
        // 신규 메모 폼의 hidden input 전체 조회
        // ====================================================

        var hiddenInputs =
            sourceForm.querySelectorAll(
                'input[type="hidden"]'
            );


        // ====================================================
        // 아래 값은 우리가 직접 만든 데이터이므로
        // 수정/삭제 폼으로 복사하면 안 된다.
        // ====================================================

        var skipNames = {

            cid: true,
            user_id: true,
            problem_id: true,
            sid: true

        };


        // ====================================================
        // 수정/삭제 폼
        // ====================================================

        var actionForms =
            document.querySelectorAll(
                ".teacher-note-action-form"
            );


        // ====================================================
        // set_post_key.php가 만든 hidden 값을 찾아
        // 모든 수정/삭제 폼에 복사
        // ====================================================

        for(
            var i = 0;
            i < hiddenInputs.length;
            i++
        ){

            var input =
                hiddenInputs[i];


            var name =
                input.getAttribute(
                    "name"
                );


            if(
                !name ||
                skipNames[name]
            ){

                continue;
            }


            for(
                var j = 0;
                j < actionForms.length;
                j++
            ){

                // 이미 같은 필드가 있으면 중복 생성 방지
                var existingInput =
                    actionForms[j]
                        .querySelector(
                            'input[name="' +
                            name +
                            '"]'
                        );


                if(existingInput){

                    continue;
                }


                var clonedInput =
                    input.cloneNode(
                        true
                    );


                actionForms[j]
                    .appendChild(
                        clonedInput
                    );
            }
        }
    }
);

</script>


<!-- ======================================================
     하단 버튼
     ====================================================== -->

<div
    style="
        text-align:center;
        margin-top:25px;
    "
>

    <button
        type="button"
        class="ui button"
        onclick="history.back();"
    >

        돌아가기

    </button>

</div>


</div>


<?php

include(
    "template/$OJ_TEMPLATE/footer.php"
);

?>