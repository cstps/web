<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>


<div class="course-page">


    <!-- ======================================================
         상단
         ====================================================== -->

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_students.php?course_id=<?php echo intval($course_id); ?>"
        >
            <i class="left arrow icon"></i>
            학생 목록
        </a>


        <h1 class="ui header">

            <?php
            echo htmlspecialchars(
                isset($view_student['nick'])
                    ? $view_student['nick']
                    : $view_student['user_id'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

            <div class="sub header">
                학생 학습현황
            </div>

        </h1>


        <div class="course-page-description">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

    </div>


    <!-- ======================================================
         학생 기본 정보
         ====================================================== -->

    <div class="ui segment">

        <div class="ui relaxed horizontal list">

            <div class="item">

                <strong>번호</strong>

                <?php
                echo htmlspecialchars(
                    isset($view_student['student_no'])
                        ? $view_student['student_no']
                        : '-',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>


            <div class="item">

                <strong>아이디</strong>

                <?php
                echo htmlspecialchars(
                    $view_student['user_id'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>


            <div class="item">

                <strong>이름</strong>

                <?php
                echo htmlspecialchars(
                    isset($view_student['nick'])
                        ? $view_student['nick']
                        : '-',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>


            <div class="item">

                <strong>학교</strong>

                <?php
                echo htmlspecialchars(
                    isset($view_student['school'])
                        ? $view_student['school']
                        : '-',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>


            <div class="item">

                <strong>상태</strong>

                <?php
                if (intval($view_student['status']) === 1) {
                ?>

                    <span class="ui tiny green label">
                        수강 중
                    </span>

                <?php
                }
                else {
                ?>

                    <span class="ui tiny grey label">
                        수강 종료
                    </span>

                <?php
                }
                ?>

            </div>

        </div>

    </div>


    <!-- ======================================================
         수업 통계
         ====================================================== -->

    <div class="ui three statistics">

        <div class="statistic">

            <div class="value">
                <?php echo intval($view_course_solved_count); ?>
            </div>

            <div class="label">
                해결 문제
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php echo intval($view_course_submit_count); ?>
            </div>

            <div class="label">
                제출
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php
                echo intval(
                    $view_participated_contest_count
                );
                ?>
            </div>

            <div class="label">
                참여 차시
            </div>

        </div>

    </div>


    <!-- ======================================================
         대회별 현황
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;"
    >
        차시별 학습현황
    </h3>


    <?php
    if (empty($view_contests)) {
    ?>

        <div class="ui info message">
            이 수업에 연결된 차시가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <table class="ui celled striped table">

            <thead>

                <tr>

                    <th class="center aligned">
                        차시
                    </th>

                    <th>
                        제목
                    </th>

                    <th class="center aligned">
                        제출
                    </th>

                    <th class="center aligned">
                        해결
                    </th>

                    <th>
                        마지막 제출
                    </th>

                    <th class="center aligned">
                        보기
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php
            foreach ($view_contests as $contest) {

                $display_title =
                    isset($contest['title'])
                        ? $contest['title']
                        : '';
            ?>

                <tr>

                    <td class="center aligned">

                        <?php
                        echo intval(
                            $contest['lesson_no']
                        );
                        ?>차시

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $display_title,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </td>


                    <td class="center aligned">

                        <?php
                        echo intval(
                            $contest['submit_count']
                        );
                        ?>

                    </td>


                    <td class="center aligned">

                        <?php
                        echo intval(
                            $contest['solved_count']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo !empty(
                            $contest['last_submit_time']
                        )
                            ? htmlspecialchars(
                                $contest['last_submit_time'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            : '-';
                        ?>

                    </td>


                    <td class="center aligned">

                        <button
                            type="button"
                            class="ui tiny teal basic button course-problem-toggle"
                            data-contest-id="<?php
                                echo intval($contest['contest_id']);
                            ?>"
                        >
                            문제별 보기
                        </button>

                        <a
                            class="ui tiny blue basic button"
                            href="contest.php?cid=<?php
                                echo intval($contest['contest_id']);
                            ?>"
                        >
                            대회 보기
                        </a>

                    </td>

                </tr>
                <?php
                $contest_id =
                    intval($contest['contest_id']);

                $problem_rows =
                    isset($view_contest_problems[$contest_id])
                        ? $view_contest_problems[$contest_id]
                        : array();
                ?>


                <tr
                    id="course-problems-<?php echo intval($contest_id); ?>"
                    class="course-problem-detail"
                    style="display:none;"
                >

                    <td colspan="6">

                        <?php
                        if (empty($problem_rows)) {
                        ?>

                            <div class="ui small message">
                                등록된 문제가 없습니다.
                            </div>

                        <?php
                        }
                        else {
                        ?>

                            <table class="ui very compact celled table">

                                <thead>

                                    <tr>
                                        <th class="center aligned">
                                            문제
                                        </th>

                                        <th>
                                            제목
                                        </th>

                                        <th class="center aligned">
                                            제출
                                        </th>

                                        <th class="center aligned">
                                            상태
                                        </th>

                                        <th>
                                            최초 제출
                                        </th>

                                        <th>
                                            마지막 제출
                                        </th>

                                        <th class="center aligned">
                                            과정
                                        </th>
                                    </tr>

                                </thead>


                                <tbody>

                                <?php
                                foreach ($problem_rows as $problem) {

                                    $submit_count =
                                        intval($problem['submit_count']);

                                    $ac_count =
                                        intval($problem['ac_count']);

                                    $latest_result =
                                        isset($problem['latest_result'])
                                            ? $problem['latest_result']
                                            : null;


                                    // ----------------------------------------
                                    // 문제 문자
                                    // num=0 → A
                                    // num=1 → B
                                    // ----------------------------------------

                                    $problem_label =
                                        chr(
                                            ord('A') +
                                            intval($problem['num'])
                                        );
                                ?>

                                    <tr>

                                        <td class="center aligned">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $problem_label,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $problem['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>


                                        <td class="center aligned">

                                            <?php
                                            echo $submit_count;
                                            ?>

                                        </td>


                                        <td class="center aligned">

                                            <?php
                                            if ($submit_count === 0) {
                                            ?>

                                                <span class="ui tiny grey label">
                                                    미제출
                                                </span>

                                            <?php
                                            }
                                            elseif ($ac_count > 0) {
                                            ?>

                                                <span class="ui tiny green label">
                                                    해결
                                                </span>

                                            <?php
                                            }
                                            else {
                                            ?>

                                                <span class="ui tiny orange label">
                                                    진행 중
                                                </span>

                                            <?php
                                            }
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo !empty(
                                                $problem['first_submit_time']
                                            )
                                                ? htmlspecialchars(
                                                    $problem['first_submit_time'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                                : '-';
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo !empty(
                                                $problem['last_submit_time']
                                            )
                                                ? htmlspecialchars(
                                                    $problem['last_submit_time'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                                : '-';
                                            ?>

                                        </td>


                                        <td class="center aligned">

                                            <?php
                                            if (
                                                intval(
                                                    $problem['latest_solution_id']
                                                ) > 0
                                            ) {
                                            ?>
                                                <a
                                                    class="ui tiny teal basic button course-action-button"
                                                    href="solution_process_view.php?sid=<?php
                                                        echo intval(
                                                            $problem['latest_solution_id']
                                                        );
                                                    ?>&course_id=<?php
                                                        echo intval($course_id);
                                                    ?>"
                                                >
                                                    해결과정
                                                </a>

                                            <?php
                                            }
                                            else {
                                            ?>

                                                -

                                            <?php
                                            }
                                            ?>

                                        </td>

                                    </tr>

                                <?php
                                }
                                ?>

                                </tbody>

                            </table>

                        <?php
                        }
                        ?>

                    </td>

                </tr>

            <?php
            }
            ?>

            </tbody>

        </table>


    <?php
    }
    ?>
    <!-- ======================================================
     학생 누적 메모
     ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;"
    >
        교사 누적 메모
    </h3>


    <?php
    if (empty($view_student_memos)) {
    ?>

        <div class="ui info message">
            등록된 메모가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <div class="ui relaxed divided list">

            <?php
            foreach ($view_student_memos as $memo) {

                $memo_contest_id =
                    isset($memo['contest_id'])
                        ? intval($memo['contest_id'])
                        : 0;
            ?>

                <div class="item">

                    <div class="content">

                        <div class="header">

                            <?php
                            if ($memo_contest_id > 0) {

                                echo intval(
                                    $memo['lesson_no']
                                ).'차시';

                                if (!empty($memo['contest_title'])) {

                                    echo ' - ';

                                    echo htmlspecialchars(
                                        $memo['contest_title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                }
                            }
                            else {

                                echo '수업 전체';
                            }
                            ?>

                        </div>


                        <div class="meta">

                            <?php
                            echo htmlspecialchars(
                                $memo['created_by'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            ·

                            <?php
                            echo htmlspecialchars(
                                $memo['created_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>


                        <div
                            class="description"
                            style="margin-top:0.5rem;"
                        >

                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $memo['memo_text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );
                            ?>

                        </div>

                    </div>

                    <?php
                    $can_manage_memo =
                        isset($_SESSION[$OJ_NAME.'_administrator']) ||
                        (
                            isset($_SESSION[$OJ_NAME.'_user_id']) &&
                            $_SESSION[$OJ_NAME.'_user_id'] === $memo['created_by']
                        );
                    ?>

                    <?php
                    if ($can_manage_memo) {
                    ?>

                        <div style="margin-top:0.5rem;">

                            <a
                                class="ui tiny teal basic button"
                                href="course_student_memo_edit.php?course_id=<?php
                                    echo intval($course_id);
                                ?>&user_id=<?php
                                    echo urlencode($student_user_id);
                                ?>&memo_id=<?php
                                    echo intval($memo['id']);
                                ?>"
                            >
                                수정
                            </a>

                            <form
                                method="post"
                                action="course_student_memo_delete.php"
                                style="display:inline;"
                                onsubmit="return confirm('이 메모를 삭제하시겠습니까?');"
                            >

                                <?php include("./csrf.php"); ?>

                                <input
                                    type="hidden"
                                    name="course_id"
                                    value="<?php echo intval($course_id); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $student_user_id,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                                <input
                                    type="hidden"
                                    name="memo_id"
                                    value="<?php echo intval($memo['id']); ?>"
                                >

                                <button
                                    type="submit"
                                    class="ui tiny red basic button"
                                >
                                    삭제
                                </button>

                            </form>

                        </div>

                    <?php
                    }
                    ?>

                </div>

            <?php
            }
            ?>

        </div>

    <?php
    }
    ?>


    <!-- ======================================================
        새 메모 작성
        ====================================================== -->

    <div
        class="ui segment"
        style="margin-top:2rem;"
    >

        <h3 class="ui header">
            새 메모 작성
        </h3>


        <form
            class="ui form"
            method="post"
            action="course_student_memo_add.php"
        >

            <?php include("./csrf.php"); ?>

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="user_id"
                value="<?php
                    echo htmlspecialchars(
                        $student_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <div class="field">

                <label>
                    메모 대상
                </label>

                <select
                    name="contest_id"
                    class="ui dropdown"
                >

                    <option value="">
                        수업 전체
                    </option>

                    <?php
                    foreach ($view_contests as $contest) {
                    ?>

                        <option
                            value="<?php
                                echo intval(
                                    $contest['contest_id']
                                );
                            ?>"
                        >

                            <?php
                            echo intval(
                                $contest['lesson_no']
                            );
                            ?>차시

                            -

                            <?php
                            echo htmlspecialchars(
                                $contest['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </option>

                    <?php
                    }
                    ?>

                </select>

            </div>


            <div class="field">

                <label>
                    관찰 메모
                </label>

                <textarea
                    name="memo_text"
                    rows="5"
                    maxlength="5000"
                    required
                    placeholder="학생의 문제 해결 과정, 재시도 과정, AI 활용, 수업 참여 등의 관찰 내용을 기록하세요."
                ></textarea>

            </div>


            <button
                type="submit"
                class="ui teal button"
            >
                <i class="save icon"></i>
                메모 저장
            </button>

        </form>

    </div>
</div>

<script>
(function() {

    var toggleButtons =
        document.querySelectorAll(
            '.course-problem-toggle'
        );


    toggleButtons.forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                var contestId =
                    this.getAttribute(
                        'data-contest-id'
                    );

                var detailRow =
                    document.getElementById(
                        'course-problems-' + contestId
                    );


                if (!detailRow) {
                    return;
                }


                var isHidden =
                    detailRow.style.display === 'none' ||
                    detailRow.style.display === '';


                if (isHidden) {

                    detailRow.style.display =
                        'table-row';

                    this.textContent =
                        '문제별 닫기';

                }
                else {

                    detailRow.style.display =
                        'none';

                    this.textContent =
                        '문제별 보기';

                }

            }
        );

    });

})();
</script>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>