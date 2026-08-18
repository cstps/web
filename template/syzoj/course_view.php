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
            href="course_list.php"
            class="ui small basic button"
        >
            <i class="left arrow icon"></i>
            수업 목록
        </a>

        <h1 class="ui header">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </h1>


        <div class="course-page-description">

            <?php echo intval($view_course['school_year']); ?>학년도

            ·

            <?php
            $semester = intval($view_course['semester']);

            if ($semester == 1) {
                echo '1학기';
            }
            elseif ($semester == 2) {
                echo '2학기';
            }
            elseif ($semester > 0) {
                echo $semester.'학기';
            }
            else {
                echo '학기 구분 없음';
            }
            ?>

            <?php
            if (!empty($view_course['school'])) {
            ?>
                ·
                <?php
                echo htmlspecialchars(
                    $view_course['school'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            <?php
            }
            ?>

        </div>

    </div>


    <?php
    if (!empty($view_course['description'])) {
    ?>

        <div class="ui segment">

            <?php
            echo nl2br(
                htmlspecialchars(
                    $view_course['description'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
            ?>

        </div>

    <?php
    }
    ?>
    <div class="course-actions">

        <a
            class="ui blue button"
            href="course_students.php?course_id=<?php echo intval($course_id); ?>"
        >
            <i class="users icon"></i>

            <?php
            if ($view_can_manage_students) {
                echo '학생 관리';
            }
            else {
                echo '학생 보기';
            }
            ?>
        </a>



        <?php
        if ($view_can_manage_teachers) {
        ?>

            <a
                class="ui teal basic button"
                href="course_teachers.php?course_id=<?php echo intval($course_id); ?>"
            >
                <i class="user tie icon"></i>
                교사 관리
            </a>

        <?php
        }
        ?>
        <?php
        if ($view_can_edit) {
        ?>

            <a
                class="ui basic button"
                href="course_edit.php?course_id=<?php echo intval($course_id); ?>"
            >
                <i class="edit icon"></i>
                수업 정보 수정
            </a>

        <?php
        }
        ?>
        <?php
        if ($view_can_edit) {
        ?>

            <form
                method="post"
                action="course_status.php"
                style="display:inline;"
                onsubmit="return confirm(
                    '<?php
                    if (intval($view_course['status']) === 1) {
                        echo '이 수업을 종료하시겠습니까?';
                    }
                    else {
                        echo '이 수업을 다시 시작하시겠습니까?';
                    }
                    ?>'
                );"
            >

                <?php include("./csrf.php"); ?>

                <input
                    type="hidden"
                    name="course_id"
                    value="<?php echo intval($course_id); ?>"
                >

                <?php
                if (intval($view_course['status']) === 1) {
                ?>

                    <input
                        type="hidden"
                        name="status"
                        value="0"
                    >

                    <button
                        type="submit"
                        class="ui red basic button"
                    >
                        <i class="stop icon"></i>
                        수업 종료
                    </button>

                <?php
                }
                else {
                ?>

                    <input
                        type="hidden"
                        name="status"
                        value="1"
                    >

                    <button
                        type="submit"
                        class="ui green basic button"
                    >
                        <i class="play icon"></i>
                        수업 재개
                    </button>

                <?php
                }
                ?>

            </form>

        <?php
        }
        ?>
    </div>

    

    <!-- ======================================================
    요약
    ====================================================== -->

    <div class="ui three statistics">

        <div class="statistic">

            <div class="value">
                <?php echo intval($view_student_count); ?>
            </div>

            <div class="label">
                학생
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php echo count($view_teachers); ?>
            </div>

            <div class="label">
                담당 교사
            </div>

        </div>


        <div class="statistic">

            <div class="value">
                <?php echo count($view_contests); ?>
            </div>

            <div class="label">
                차시
            </div>

        </div>

    </div>


    <!-- ======================================================
         담당 교사
         ====================================================== -->

    <h3 class="ui dividing header">
        담당 교사
    </h3>


    <?php
    if (empty($view_teachers)) {
    ?>

        <div class="ui message">
            등록된 담당 교사가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <table class="ui celled table">

            <thead>
                <tr>
                    <th>아이디</th>
                    <th>이름</th>
                    <th>소속</th>
                    <th>역할</th>
                </tr>
            </thead>

            <tbody>

            <?php
            foreach ($view_teachers as $teacher) {

                switch ($teacher['role']) {

                    case 'owner':
                        $role_label = '책임교사';
                        break;

                    case 'teacher':
                        $role_label = '담당교사';
                        break;

                    case 'assistant':
                        $role_label = '보조교사';
                        break;

                    default:
                        $role_label = '-';
                }
            ?>

                <tr>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $teacher['user_id'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($teacher['nick'])
                                ? $teacher['nick']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($teacher['school'])
                                ? $teacher['school']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo $role_label; ?>
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
         연결 대회
         ====================================================== -->

    <h3 class="ui dividing header">
        수업 차시
    </h3>


    <?php
    if (
        $view_can_manage_contests &&
        intval($view_course['status']) === 1
    ) {
    ?>

        <div style="margin-bottom:1rem;">

            <a
                class="ui teal button"
                href="course_contest_add.php?course_id=<?php
                    echo intval($course_id);
                ?>"
            >
                <i class="plus icon"></i>
                차시 추가
            </a>

        </div>

    <?php
    }
    ?>


    <?php
    if (empty($view_contests)) {
    ?>

        <div class="ui message">
            연결된 차시가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <table class="ui celled table course-contest-table">

            <thead>
                <tr>
                    <th>차시</th>
                    <th>대회 번호</th>
                    <th>수업 내용</th>
                    <th>시작</th>
                    <th>종료</th>
                    <th>공개</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php
            foreach ($view_contests as $contest) {
            ?>
                
                <tr>
                    <td>
                        <?php
                        echo intval($contest['lesson_no']);
                        ?>
                    </td>

                    <td>
                        <?php
                        echo intval($contest['contest_id']);
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($contest['title'])
                                ? $contest['title']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($contest['start_time'])
                                ? $contest['start_time']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($contest['end_time'])
                                ? $contest['end_time']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>
                    <td class="center aligned">

                        <?php
                        if ($view_can_manage_contests) {
                        ?>

                            <form
                                method="post"
                                action="course_contest_visibility.php"
                                style="display:inline;"
                            >

                                <?php include("./csrf.php"); ?>

                                <input
                                    type="hidden"
                                    name="course_id"
                                    value="<?php echo intval($course_id); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="contest_id"
                                    value="<?php echo intval($contest['contest_id']); ?>"
                                >

                                <?php
                                if (intval($contest['visible']) === 1) {
                                ?>

                                    <input
                                        type="hidden"
                                        name="visible"
                                        value="0"
                                    >

                                    <button
                                        type="submit"
                                        class="ui tiny green basic button"
                                    >
                                        공개
                                    </button>

                                <?php
                                }
                                else {
                                ?>

                                    <input
                                        type="hidden"
                                        name="visible"
                                        value="1"
                                    >

                                    <button
                                        type="submit"
                                        class="ui tiny grey basic button"
                                    >
                                        숨김
                                    </button>

                                <?php
                                }
                                ?>

                            </form>

                        <?php
                        }
                        else {
                        ?>

                            <?php
                            if (intval($contest['visible']) === 1) {
                                echo '공개';
                            }
                            else {
                                echo '숨김';
                            }
                            ?>

                        <?php
                        }
                        ?>

                    </td>
                    <td class="center aligned">

                        <a
                            class="ui tiny blue button"
                            href="contest.php?cid=<?php
                                echo intval($contest['contest_id']);
                            ?>"
                        >
                            대회 보기
                        </a>
                        <?php
                        if ($view_can_manage_contests) {
                        ?>

                            <a
                                class="ui tiny teal basic button"
                                href="course_contest_edit.php?course_id=<?php
                                    echo intval($course_id);
                                ?>&contest_id=<?php
                                    echo intval($contest['contest_id']);
                                ?>"
                            >
                                수정
                            </a>

                            <a
                                class="ui tiny orange basic button"
                                href="course_contest_problem_edit.php?course_id=<?php
                                    echo intval($course_id);
                                ?>&contest_id=<?php
                                    echo intval($contest['contest_id']);
                                ?>"
                            >
                                문제 구성
                            </a>

                            <form
                                method="post"
                                action="course_contest_remove.php"
                                style="display:inline;"
                                onsubmit="return confirm(
                                    '이 차시를 수업에서 제거하시겠습니까?\n\n학생의 기존 제출 및 해결과정은 삭제되지 않습니다.'
                                );"
                            >

                                <?php include("./csrf.php"); ?>

                                <input
                                    type="hidden"
                                    name="course_id"
                                    value="<?php echo intval($course_id); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="contest_id"
                                    value="<?php echo intval($contest['contest_id']); ?>"
                                >

                                <button
                                    type="submit"
                                    class="ui tiny red basic button"
                                >
                                    차시 제거
                                </button>

                            </form>

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
    <?php
    if (
        $view_can_manage_contests &&
        !empty($view_removed_contests)
    ) {
    ?>

        <h3
            class="ui dividing header"
            style="margin-top:2rem;"
        >
            제거된 차시
        </h3>

        <table class="ui celled table">

            <thead>
                <tr>
                    <th>차시</th>
                    <th>대회 번호</th>
                    <th>제목</th>
                    <th>제거 상태</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php
            foreach ($view_removed_contests as $contest) {
            ?>

                <tr>

                    <td>
                        <?php
                        echo intval($contest['lesson_no']);
                        ?>차시
                    </td>

                    <td>
                        <?php
                        echo intval($contest['contest_id']);
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            isset($contest['title'])
                                ? $contest['title']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <span class="ui tiny grey label">
                            제거됨
                        </span>
                    </td>

                    <td class="center aligned">

                        <form
                            method="post"
                            action="course_contest_restore.php"
                            style="display:inline;"
                            onsubmit="return confirm('이 차시를 다시 복원하시겠습니까?');"
                        >

                            <?php include("./csrf.php"); ?>

                            <input
                                type="hidden"
                                name="course_id"
                                value="<?php echo intval($course_id); ?>"
                            >

                            <input
                                type="hidden"
                                name="contest_id"
                                value="<?php echo intval($contest['contest_id']); ?>"
                            >

                            <button
                                type="submit"
                                class="ui tiny blue basic button"
                            >
                                복원
                            </button>

                        </form>

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
include("template/$OJ_TEMPLATE/footer.php");
?>