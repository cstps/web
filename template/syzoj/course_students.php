<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css">

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_view.php?course_id=<?php echo intval($course_id); ?>">
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            학생 관리
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


    <!-- ========================================================
     수업 학생 종합현황
     ======================================================== -->

    <div
        class="ui five small statistics course-student-summary"
        style="margin-top:1.5rem;">

        <div class="statistic">

            <div class="value">
                <?php
                echo intval(
                    $view_active_student_count
                );
                ?>
            </div>

            <div class="label">
                수강 중
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_started_contest_count
                );
                ?>

                /

                <?php
                echo intval(
                    $view_course_contest_count
                );
                ?>

            </div>

            <div class="label">
                현재 진행 차시
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <a
                    href="#course-student-table"
                    class="course-summary-filter"
                    data-attention-filter="attention">
                    <?php
                    echo intval(
                        $view_attention_student_count
                    );
                    ?>
                </a>

            </div>

            <div class="label">
                확인 필요 학생
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <a
                    href="#course-student-table"
                    class="course-summary-filter"
                    data-attention-filter="missed">
                    <?php
                    echo intval(
                        $view_missed_student_count
                    );
                    ?>
                </a>

            </div>

            <div class="label">
                미참여 학생
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <a
                    href="#course-student-table"
                    class="course-summary-filter"
                    data-attention-filter="retry">
                    <?php
                    echo intval(
                        $view_retry_unsolved_student_count
                    );
                    ?>
                </a>

            </div>

            <div class="label">
                반복 미해결 학생
            </div>

        </div>

    </div>

    <?php
    if (!empty($view_message)) {
    ?>

        <div class="ui positive message">
            <?php
            echo htmlspecialchars(
                $view_message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php
    }
    ?>


    <?php
    if (!empty($view_error_message)) {
    ?>

        <div class="ui negative message">
            <?php
            echo htmlspecialchars(
                $view_error_message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    <?php
    }
    ?>

    <h3 class="ui dividing header" style="margin-top: 2rem;">
        학생 목록
    </h3>

    <div class="ui form" style="margin-bottom: 1rem;">

        <div class="fields">

            <div class="eight wide field">
                <label>학생 검색</label>

                <input
                    type="text"
                    id="course-student-search"
                    placeholder="번호, 아이디, 이름, 학교 검색">
            </div>


            <div class="four wide field">
                <label>수강 상태</label>

                <select
                    id="course-student-status-filter"
                    class="ui dropdown">
                    <option value="all">전체</option>
                    <option value="active">수강 중</option>
                    <option value="inactive">수강 종료</option>
                </select>
            </div>


            <div class="four wide field">
                <label>확인 필요</label>

                <select
                    id="course-student-attention-filter"
                    class="ui dropdown">

                    <option value="all">
                        전체
                    </option>

                    <option value="attention">
                        확인 필요 있음
                    </option>

                    <option value="missed">
                        미참여
                    </option>

                    <option value="retry">
                        반복 미해결
                    </option>

                    <option value="none">
                        확인 필요 없음
                    </option>

                </select>
            </div>

        </div>

    </div>

    <div class="course-table-scroll">
        <?php
        if (empty($view_students)) {
        ?>

            <div class="ui info message">
                등록된 학생이 없습니다.
            </div>

        <?php
        } else {
        ?>

            <table
                id="course-student-table"
                class="ui compact celled striped table course-student-overview-table">

                <thead>
                    <tr>
                        <th class="center aligned">번호</th>
                        <th>학생</th>
                        <th class="center aligned course-student-action-column">
                            수업 해결
                        </th>

                        <th class="center aligned course-student-action-column">
                            수업제출
                        </th>

                        <th class="center aligned course-student-action-column">
                            참여차시
                        </th>
                        <th class="center aligned course-student-action-column">
                            메모
                        </th>

                        <th class="center aligned course-student-attention-column">
                            확인 필요
                        </th>

                        <th class="center aligned course-student-view-column">
                            학습현황
                        </th>
                        <th class="center aligned course-student-manage-column">
                            관리
                        </th>
                        <th class="course-student-last-column">
                            최근 활동
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    foreach ($view_students as $student) {

                        $student_status =
                            intval($student['status']);

                        $account_exists =
                            isset($student['account_user_id']) &&
                            $student['account_user_id'] !== null &&
                            $student['account_user_id'] !== '';

                        $missed_started_count =
                            isset(
                                $student['missed_started_contest_count']
                            )
                            ? intval(
                                $student['missed_started_contest_count']
                            )
                            : 0;


                        $retry_unsolved_count =
                            isset(
                                $student['retry_unsolved_count']
                            )
                            ? intval(
                                $student['retry_unsolved_count']
                            )
                            : 0;


                        $has_attention =
                            (
                                $student_status === 1 &&
                                (
                                    $missed_started_count > 0 ||
                                    $retry_unsolved_count > 0
                                )
                            );
                    ?>

                        <tr
                            class="course-student-row<?php
                                                        if ($student_status !== 1) {
                                                            echo ' course-student-inactive';
                                                        }
                                                        ?>"

                            data-status="<?php
                                            echo ($student_status === 1)
                                                ? 'active'
                                                : 'inactive';
                                            ?>"

                            data-attention="<?php
                                            echo $has_attention
                                                ? 'attention'
                                                : 'none';
                                            ?>"

                            data-missed="<?php
                                            echo $missed_started_count > 0
                                                ? 'yes'
                                                : 'no';
                                            ?>"

                            data-retry="<?php
                                        echo $retry_unsolved_count > 0
                                            ? 'yes'
                                            : 'no';
                                        ?>">
                            <td class="center aligned">

                                <?php
                                if (
                                    $view_can_manage_students &&
                                    intval($view_course['status']) === 1
                                ) {
                                ?>

                                    <form
                                        method="post"
                                        action="course_students.php?course_id=<?php echo intval($course_id); ?>"
                                        class="ui form"
                                        style="margin:0;">

                                        <?php echo $view_csrf_input; ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_student_no">

                                        <input
                                            type="hidden"
                                            name="target_user_id"
                                            value="<?php
                                                    echo htmlspecialchars(
                                                        $student['user_id'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?>">

                                        <div class="ui action input">

                                            <input
                                                type="text"
                                                name="student_no"
                                                maxlength="20"
                                                value="<?php
                                                        echo htmlspecialchars(
                                                            isset($student['student_no'])
                                                                ? $student['student_no']
                                                                : '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>"
                                                style="width:80px; font-size:14px;">

                                            <button
                                                type="submit"
                                                class="ui tiny basic button"
                                                title="학생 번호 수정">
                                                저장
                                            </button>

                                        </div>

                                    </form>

                                <?php
                                } else {
                                ?>

                                    <?php
                                    echo htmlspecialchars(
                                        isset($student['student_no'])
                                            ? $student['student_no']
                                            : '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                <?php
                                }
                                ?>

                            </td>


                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        isset($student['nick'])
                                            ? $student['nick']
                                            : $student['user_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </strong>

                                <div style="font-size:0.85em; color:#777;">

                                    <?php
                                    echo htmlspecialchars(
                                        $student['user_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                    <?php
                                    if (!$account_exists) {
                                    ?>

                                        <span class="ui tiny red label">
                                            계정 없음
                                        </span>

                                    <?php
                                    }
                                    ?>

                                </div>
                                <div style="font-size:0.85em; color:#777;">

                                    <?php
                                    echo htmlspecialchars(
                                        $student['school'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </div>

                            </td>




                            <td class="center aligned course-student-action-column">

                                <strong>
                                    <?php
                                    echo intval(
                                        $student['course_solved_count']
                                    );
                                    ?>
                                </strong>

                                /

                                <?php
                                echo intval(
                                    $view_course_problem_count
                                );
                                ?>

                            </td>


                            <td class="center aligned course-student-action-column">
                                <?php
                                echo isset($student['course_submit_count'])
                                    ? intval($student['course_submit_count'])
                                    : 0;
                                ?>
                            </td>


                            <td class="center aligned course-student-action-column">

                                <strong>
                                    <?php
                                    echo isset(
                                        $student['participated_contest_count']
                                    )
                                        ? intval(
                                            $student['participated_contest_count']
                                        )
                                        : 0;
                                    ?>
                                </strong>

                                /

                                <?php
                                echo intval(
                                    $view_course_contest_count
                                );
                                ?>

                            </td>


                            <td class="center aligned course-student-action-column">

                                <?php
                                echo isset(
                                    $student['course_memo_count']
                                )
                                    ? intval(
                                        $student['course_memo_count']
                                    )
                                    : 0;
                                ?>

                            </td>

                            <td class="center aligned course-student-attention-column">

                                <div class="course-student-attention-labels">

                                    <?php

                                    $missed_started_count =
                                        isset(
                                            $student['missed_started_contest_count']
                                        )
                                        ? intval(
                                            $student['missed_started_contest_count']
                                        )
                                        : 0;


                                    $retry_unsolved_count =
                                        isset(
                                            $student['retry_unsolved_count']
                                        )
                                        ? intval(
                                            $student['retry_unsolved_count']
                                        )
                                        : 0;


                                    if ($student_status !== 1) {

                                        echo '-';
                                    } else {

                                        $has_attention =
                                            false;


                                        if ($missed_started_count > 0) {

                                            $has_attention = true;
                                    ?>

                                            <a
                                                class="ui tiny orange basic label"
                                                title="미참여 차시 확인"
                                                href="course_student_view.php?course_id=<?php
                                                                                        echo intval($course_id);
                                                                                        ?>&user_id=<?php
                                                                                                    echo urlencode($student['user_id']);
                                                                                                    ?>&focus=missed#course-learning-status">
                                                미참여
                                                <?php echo $missed_started_count; ?>
                                            </a>

                                        <?php
                                        }


                                        if ($retry_unsolved_count > 0) {

                                            $has_attention = true;
                                        ?>

                                            <a
                                                class="ui tiny red basic label"
                                                title="반복 미해결 문제 확인"
                                                href="course_student_view.php?course_id=<?php
                                                                                        echo intval($course_id);
                                                                                        ?>&user_id=<?php
                                                                                                    echo urlencode($student['user_id']);
                                                                                                    ?>&focus=retry#course-learning-status">
                                                반복 미해결
                                                <?php echo $retry_unsolved_count; ?>
                                            </a>

                                        <?php
                                        }


                                        if (!$has_attention) {
                                        ?>

                                            <span class="ui tiny basic label">
                                                -
                                            </span>

                                    <?php
                                        }
                                    }
                                    ?>
                                </div>

                            </td>


                            <!-- ========================================================
                        추가: 학생 학습현황
                        ======================================================== -->

                            <td class="center aligned course-student-view-column">

                                <a
                                    class="ui tiny teal basic button course-action-button"
                                    href="course_student_view.php?course_id=<?php
                                                                            echo intval($course_id);
                                                                            ?>&user_id=<?php
                                                                                        echo urlencode($student['user_id']);
                                                                                        ?>">
                                    학습현황
                                </a>

                            </td>




                            <td class="center aligned course-student-manage-column">

                                <?php
                                if (
                                    $view_can_manage_students &&
                                    intval($view_course['status']) === 1
                                ) {
                                ?>

                                    <?php
                                    if ($student_status === 1) {
                                    ?>

                                        <form
                                            method="post"
                                            action="course_students.php?course_id=<?php echo intval($course_id); ?>"
                                            style="display:inline;"
                                            onsubmit="return confirm('이 학생의 수강을 종료하시겠습니까?');">

                                            <?php echo $view_csrf_input; ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate_student">

                                            <input
                                                type="hidden"
                                                name="target_user_id"
                                                value="<?php
                                                        echo htmlspecialchars(
                                                            $student['user_id'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>">

                                            <button
                                                type="submit"
                                                class="ui tiny red basic button course-action-button">
                                                수강 종료
                                            </button>

                                        </form>

                                    <?php
                                    } else {
                                    ?>

                                        <form
                                            method="post"
                                            action="course_students.php?course_id=<?php echo intval($course_id); ?>"
                                            style="display:inline;"
                                            onsubmit="return confirm('이 학생을 다시 수강 등록하시겠습니까?');">

                                            <?php echo $view_csrf_input; ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="reactivate_student">

                                            <input
                                                type="hidden"
                                                name="target_user_id"
                                                value="<?php
                                                        echo htmlspecialchars(
                                                            $student['user_id'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                        ?>">

                                            <button
                                                type="submit"
                                                class="ui tiny blue basic button course-action-button">
                                                재등록
                                            </button>

                                        </form>

                                    <?php
                                    }
                                    ?>

                                <?php
                                } else {
                                ?>

                                    -

                                <?php
                                }
                                ?>

                            </td>

                            <td class="course-student-last-column">
                                <?php
                                if (!empty($student['course_last_activity'])) {

                                    echo htmlspecialchars(
                                        $student['course_last_activity'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                } else {

                                    echo '활동 없음';
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
    </div>

    <?php
    if ($view_can_manage_students) {
    ?>

        <div class="ui segment">

            <h3 class="ui header">
                학생 추가
            </h3>





            <?php
            if (intval($view_course['status']) === 1) {
            ?>

                <form
                    class="ui form"
                    method="post"
                    action="course_students.php?course_id=<?php echo intval($course_id); ?>">

                    <?php echo $view_csrf_input; ?>

                    <input
                        type="hidden"
                        name="action"
                        value="add_single">


                    <div class="fields">

                        <div class="ten wide field">

                            <label>
                                학생 아이디
                            </label>

                            <input
                                type="text"
                                name="student_user_id"
                                maxlength="48"
                                required
                                placeholder="1024.kr 사용자 아이디">

                        </div>


                        <div class="six wide field">

                            <label>
                                학생 번호
                            </label>

                            <input
                                type="text"
                                name="student_no"
                                maxlength="20"
                                placeholder="선택 입력">

                        </div>

                    </div>


                    <button
                        class="ui blue button"
                        type="submit">
                        <i class="user plus icon"></i>
                        학생 추가
                    </button>

                </form>

            <?php
            } else {
            ?>

                <div class="ui warning message">
                    종료된 수업에는 학생을 추가할 수 없습니다.
                </div>

            <?php
            }
            ?>

        </div>

    <?php
    }
    ?>
    <?php
    if (
        $view_can_manage_students &&
        intval($view_course['status']) === 1
    ) {
    ?>

        <div class="ui segment">

            <h3 class="ui header">
                학생 일괄 추가
            </h3>
            <?php
            if (!empty($view_bulk_results)) {
            ?>

                <div class="ui segment">

                    <h3 class="ui header">
                        일괄 등록 결과
                    </h3>


                    <div class="ui four small statistics">

                        <div class="statistic">
                            <div class="value">
                                <?php echo intval($view_bulk_summary['added']); ?>
                            </div>
                            <div class="label">
                                신규 등록
                            </div>
                        </div>


                        <div class="statistic">
                            <div class="value">
                                <?php echo intval($view_bulk_summary['reactivated']); ?>
                            </div>
                            <div class="label">
                                재등록
                            </div>
                        </div>


                        <div class="statistic">
                            <div class="value">
                                <?php echo intval($view_bulk_summary['duplicate']); ?>
                            </div>
                            <div class="label">
                                이미 등록
                            </div>
                        </div>


                        <div class="statistic">
                            <div class="value">
                                <?php echo intval($view_bulk_summary['failed']); ?>
                            </div>
                            <div class="label">
                                실패
                            </div>
                        </div>

                    </div>


                    <table class="ui compact celled table">

                        <thead>
                            <tr>
                                <th>행</th>
                                <th>학생 아이디</th>
                                <th>처리 결과</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php
                            foreach ($view_bulk_results as $result) {
                            ?>

                                <tr>

                                    <td>
                                        <?php echo intval($result['line']); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $result['user_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <?php
                                        if ($result['result'] === 'added') {
                                        ?>
                                            <span class="ui tiny green label">
                                                등록
                                            </span>

                                        <?php
                                        } elseif ($result['result'] === 'reactivated') {
                                        ?>
                                            <span class="ui tiny blue label">
                                                재등록
                                            </span>

                                        <?php
                                        } elseif ($result['result'] === 'duplicate') {
                                        ?>
                                            <span class="ui tiny grey label">
                                                기존
                                            </span>

                                        <?php
                                        } else {
                                        ?>
                                            <span class="ui tiny red label">
                                                실패
                                            </span>
                                        <?php
                                        }
                                        ?>

                                        <?php
                                        echo htmlspecialchars(
                                            $result['message'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </td>

                                </tr>

                            <?php
                            }
                            ?>

                        </tbody>

                    </table>

                </div>

            <?php
            }
            ?>


            <p>
                Excel에서 학생 아이디와 학생 번호 열을 복사한 뒤
                아래 입력란에 그대로 붙여넣을 수 있습니다.
            </p>


            <div class="ui info message">

                <div class="header">
                    입력 형식
                </div>

                <p>
                    학생 아이디만 입력하거나,
                    학생 아이디와 학생 번호를 두 열로 입력할 수 있습니다.
                </p>

                <pre>
student01	1
student02	2
student03	3
</pre>

            </div>


            <form
                class="ui form"
                method="post"
                action="course_students.php?course_id=<?php echo intval($course_id); ?>">

                <?php echo $view_csrf_input; ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_bulk">


                <div class="field">

                    <label>
                        학생 목록
                    </label>

                    <textarea
                        name="bulk_students"
                        rows="10"
                        placeholder="Excel에서 학생 목록을 복사하여 붙여넣으세요."
                        required></textarea>

                </div>


                <button
                    class="ui teal button"
                    type="submit">
                    <i class="users icon"></i>
                    학생 일괄 추가
                </button>

            </form>

        </div>

    <?php
    }
    ?>

</div>
<script>
    (function() {

        var searchInput =
            document.getElementById(
                'course-student-search'
            );

        var statusFilter =
            document.getElementById(
                'course-student-status-filter'
            );

        var attentionFilter =
            document.getElementById(
                'course-student-attention-filter'
            );


        if (
            !searchInput ||
            !statusFilter ||
            !attentionFilter
        ) {
            return;
        }


        function filterStudents() {

            var keyword =
                searchInput.value
                .trim()
                .toLowerCase();

            var selectedStatus =
                statusFilter.value;

            var selectedAttention =
                attentionFilter.value;

            var rows =
                document.querySelectorAll(
                    '#course-student-table tbody .course-student-row'
                );


            rows.forEach(function(row) {

                var rowText =
                    row.textContent
                    .toLowerCase();

                var rowStatus =
                    row.getAttribute(
                        'data-status'
                    );

                var rowAttention =
                    row.getAttribute(
                        'data-attention'
                    );

                var rowMissed =
                    row.getAttribute(
                        'data-missed'
                    );

                var rowRetry =
                    row.getAttribute(
                        'data-retry'
                    );


                var keywordMatch =
                    keyword === '' ||
                    rowText.indexOf(keyword) !== -1;


                var statusMatch =
                    selectedStatus === 'all' ||
                    rowStatus === selectedStatus;


                var attentionMatch =
                    false;


                if (selectedAttention === 'all') {

                    attentionMatch = true;

                } else if (
                    selectedAttention === 'attention'
                ) {

                    attentionMatch =
                        rowAttention === 'attention';

                } else if (
                    selectedAttention === 'missed'
                ) {

                    attentionMatch =
                        rowMissed === 'yes';

                } else if (
                    selectedAttention === 'retry'
                ) {

                    attentionMatch =
                        rowRetry === 'yes';

                } else if (
                    selectedAttention === 'none'
                ) {

                    attentionMatch =
                        rowAttention === 'none';
                }


                if (
                    keywordMatch &&
                    statusMatch &&
                    attentionMatch
                ) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }

            });
        }


        searchInput.addEventListener(
            'input',
            filterStudents
        );

        attentionFilter.addEventListener(
            'change',
            filterStudents
        );

        var summaryFilters =
            document.querySelectorAll(
                '.course-summary-filter'
            );


        summaryFilters.forEach(
            function(link) {

                link.addEventListener(
                    'click',
                    function() {

                        var filterValue =
                            this.getAttribute(
                                'data-attention-filter'
                            );


                        if (
                            filterValue &&
                            attentionFilter
                        ) {

                            attentionFilter.value =
                                filterValue;


                            if (
                                typeof $ !== 'undefined' &&
                                $(attentionFilter)
                                .dropdown
                            ) {

                                $(attentionFilter)
                                    .dropdown(
                                        'set selected',
                                        filterValue
                                    );
                            }


                            filterStudents();
                        }
                    }
                );
            }
        );
        statusFilter.addEventListener(
            'change',
            filterStudents
        );

    })();
</script>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>