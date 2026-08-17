<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_view.php?course_id=<?php echo intval($course_id); ?>"
        >
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


    <!-- 학생 현황 -->

    <div class="ui two statistics">

        <div class="statistic">
            <div class="value">
                <?php echo intval($view_active_student_count); ?>
            </div>

            <div class="label">
                수강 중
            </div>
        </div>


        <div class="statistic">
            <div class="value">
                <?php echo intval($view_inactive_student_count); ?>
            </div>

            <div class="label">
                수강 종료
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
                    placeholder="번호, 아이디, 이름, 학교 검색"
                >
            </div>


            <div class="four wide field">
                <label>수강 상태</label>

                <select
                    id="course-student-status-filter"
                    class="ui dropdown"
                >
                    <option value="all">전체</option>
                    <option value="active">수강 중</option>
                    <option value="inactive">수강 종료</option>
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
        }
        else {
        ?>

            <table
                id="course-student-table"
                class="ui celled striped table"
            >

                <thead>
                    <tr>
                        <th class="center aligned">번호</th>
                        <th>아이디</th>
                        <th>이름</th>
                        <th>학교</th>
                        <th class="center aligned">수업 해결</th>
                        <th class="center aligned">수업 제출</th>
                        <th class="center aligned">상태</th>
                        <th class="two wide center aligned">학습현황</th>
                        <th class="two wide center aligned">관리</th>
                        <th>등록일</th>
                        <th>종료일</th>
                        
                    </tr>
                </thead>

                <tbody>

                <?php
                foreach ($view_students as $student) {

                    $student_status =
                        intval($student['status']);

                    $account_exists =
                        isset($student['nick']);
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
                    >

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
                                style="margin:0;"
                            >

                                <?php include("./csrf.php"); ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update_student_no"
                                >

                                <input
                                    type="hidden"
                                    name="target_user_id"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $student['user_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

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
                                        style="width:80px; font-size:14px;"
                                    >

                                    <button
                                        type="submit"
                                        class="ui tiny basic button"
                                        title="학생 번호 수정"
                                    >
                                        저장
                                    </button>

                                </div>

                            </form>

                        <?php
                        }
                        else {
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

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                isset($student['nick'])
                                    ? $student['nick']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                isset($student['school'])
                                    ? $student['school']
                                    : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </td>


                        <td class="center aligned">
                            <?php
                            echo isset($student['course_solved_count'])
                                ? intval($student['course_solved_count'])
                                : 0;
                            ?>
                        </td>


                        <td class="center aligned">
                            <?php
                            echo isset($student['course_submit_count'])
                                ? intval($student['course_submit_count'])
                                : 0;
                            ?>
                        </td>


                        <td class="center aligned">

                            <?php
                            if ($student_status === 1) {
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

                        </td>

                        <!-- ========================================================
                        추가: 학생 학습현황
                        ======================================================== -->

                        <td class="center aligned">

                            <a
                                class="ui tiny teal basic button course-action-button"
                                href="course_student_view.php?course_id=<?php
                                    echo intval($course_id);
                                ?>&user_id=<?php
                                    echo urlencode($student['user_id']);
                                ?>"
                            >
                                학습현황
                            </a>

                        </td>
                        <td class="center aligned">

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
                                        onsubmit="return confirm('이 학생의 수강을 종료하시겠습니까?');"
                                    >

                                        <?php include("./csrf.php"); ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate_student"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_user_id"
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $student['user_id'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="ui tiny red basic button course-action-button"
                                        >
                                            수강 종료
                                        </button>

                                    </form>

                                <?php
                                }
                                else {
                                ?>

                                    <form
                                        method="post"
                                        action="course_students.php?course_id=<?php echo intval($course_id); ?>"
                                        style="display:inline;"
                                        onsubmit="return confirm('이 학생을 다시 수강 등록하시겠습니까?');"
                                    >

                                        <?php include("./csrf.php"); ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reactivate_student"
                                        >

                                        <input
                                            type="hidden"
                                            name="target_user_id"
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $student['user_id'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="ui tiny blue basic button course-action-button"
                                        >
                                            재등록
                                        </button>

                                    </form>

                                <?php
                                }
                                ?>

                            <?php
                            }
                            else {
                            ?>

                                -

                            <?php
                            }
                            ?>

                        </td>
                        
                        <td>

                            <?php
                            echo htmlspecialchars(
                                $student['joined_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </td>
                        <td>

                            <?php
                            echo !empty($student['left_at'])
                                ? htmlspecialchars(
                                    $student['left_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '-';
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
                    action="course_students.php?course_id=<?php echo intval($course_id); ?>"
                >

                    <?php include("./csrf.php"); ?>

                    <input
                        type="hidden"
                        name="action"
                        value="add_single"
                    >


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
                                placeholder="1024.kr 사용자 아이디"
                            >

                        </div>


                        <div class="six wide field">

                            <label>
                                학생 번호
                            </label>

                            <input
                                type="text"
                                name="student_no"
                                maxlength="20"
                                placeholder="선택 입력"
                            >

                        </div>

                    </div>


                    <button
                        class="ui blue button"
                        type="submit"
                    >
                        <i class="user plus icon"></i>
                        학생 추가
                    </button>

                </form>

            <?php
            }
            else {
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
                                    }
                                    elseif ($result['result'] === 'reactivated') {
                                    ?>
                                        <span class="ui tiny blue label">
                                            재등록
                                        </span>

                                    <?php
                                    }
                                    elseif ($result['result'] === 'duplicate') {
                                    ?>
                                        <span class="ui tiny grey label">
                                            기존
                                        </span>

                                    <?php
                                    }
                                    else {
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
                action="course_students.php?course_id=<?php echo intval($course_id); ?>"
            >

                <?php include("./csrf.php"); ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_bulk"
                >


                <div class="field">

                    <label>
                        학생 목록
                    </label>

                    <textarea
                        name="bulk_students"
                        rows="10"
                        placeholder="Excel에서 학생 목록을 복사하여 붙여넣으세요."
                        required
                    ></textarea>

                </div>


                <button
                    class="ui teal button"
                    type="submit"
                >
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
            document.getElementById('course-student-search');

        var statusFilter =
            document.getElementById('course-student-status-filter');


        if (!searchInput || !statusFilter) {
            return;
        }


        function filterStudents() {

            var keyword =
                searchInput.value
                    .trim()
                    .toLowerCase();

            var selectedStatus =
                statusFilter.value;

            var rows =
                document.querySelectorAll(
                    '#course-student-table tbody .course-student-row'
                );


            rows.forEach(function(row) {

                var rowText =
                    row.textContent
                        .toLowerCase();

                var rowStatus =
                    row.getAttribute('data-status');


                var keywordMatch =
                    keyword === '' ||
                    rowText.indexOf(keyword) !== -1;


                var statusMatch =
                    selectedStatus === 'all' ||
                    rowStatus === selectedStatus;


                if (keywordMatch && statusMatch) {
                    row.style.display = '';
                }
                else {
                    row.style.display = 'none';
                }

            });
        }


        searchInput.addEventListener(
            'input',
            filterStudents
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