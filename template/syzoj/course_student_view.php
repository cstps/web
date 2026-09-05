<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css">


<div class="course-page">


    <!-- ======================================================
         상단
         ====================================================== -->

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_students.php?course_id=<?php echo intval($course_id); ?>">
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
                } else {
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

                /

                <?php echo intval($view_course_problem_count); ?>

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

                /

                <?php
                echo intval(
                    $view_course_contest_count
                );
                ?>

            </div>

            <div class="label">
                참여 차시
            </div>

        </div>

    </div>


    <!-- ======================================================
         학습과정 요약
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;">
        학습과정 요약
    </h3>


    <div class="ui four cards">

        <div class="card">

            <div class="content">

                <div class="header">
                    시도한 문제
                </div>

                <div class="description">

                    <strong>
                        <?php
                        echo intval(
                            $view_attempted_problem_count
                        );
                        ?>
                    </strong>

                    /

                    <?php
                    echo intval(
                        $view_course_problem_count
                    );
                    ?>

                </div>

            </div>

        </div>


        <div class="card">

            <div class="content">

                <div class="header">
                    진행 중
                </div>

                <div class="description">

                    <strong>
                        <?php
                        echo intval(
                            $view_in_progress_problem_count
                        );
                        ?>
                    </strong>

                    개

                </div>

            </div>

        </div>


        <div class="card">

            <div class="content">

                <div class="header">
                    재시도한 문제
                </div>

                <div class="description">

                    <strong>
                        <?php
                        echo intval(
                            $view_retry_problem_count
                        );
                        ?>
                    </strong>

                    개

                </div>

            </div>

        </div>


        <div class="card">

            <div class="content">

                <div class="header">
                    시도 문제당 평균 제출
                </div>

                <div class="description">

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $view_average_submit_count,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </strong>

                    회

                </div>

            </div>

        </div>

    </div>
    <!-- ======================================================
         과정 기록
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;">
        과정 기록
    </h3>


    <div class="ui four statistics">

        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_plan_problem_count
                );
                ?>

                /

                <?php
                echo intval(
                    $view_attempted_problem_count
                );
                ?>

            </div>

            <div class="label">
                풀이계획 작성
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_reflection_problem_count
                );
                ?>

            </div>

            <div class="label">
                수정 메모 작성 문제
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_ai_problem_count
                );
                ?>

            </div>

            <div class="label">
                AI 활용 문제
            </div>

        </div>


        <div class="statistic">

            <div class="value">

                <?php
                echo intval(
                    $view_persistent_unsolved_count
                );
                ?>

            </div>

            <div class="label">
                반복 시도 중
            </div>

        </div>

    </div>


    <!-- ======================================================
     AI 활용 및 수정 기록
     ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;">
        AI 활용 및 수정 기록
    </h3>


    <div class="ui stackable two column grid">

        <!-- ==================================================
         AI 활용 유형
         ================================================== -->

        <div class="column">

            <div class="ui segment">

                <h4 class="ui header">
                    AI 활용 유형

                    <div class="sub header">
                        총 <?php
                            echo intval(
                                $view_ai_usage_record_count
                            );
                            ?>회 활용
                    </div>
                </h4>


                <?php
                if (
                    intval(
                        $view_ai_usage_record_count
                    ) <= 0
                ) {
                ?>

                    <div class="ui small info message">
                        AI 활용 기록이 없습니다.
                    </div>

                <?php
                } else {
                ?>

                    <table class="ui very basic compact table">

                        <tbody>

                            <tr>
                                <td>힌트·아이디어</td>
                                <td class="right aligned">
                                    <?php echo intval($view_ai_usage_counts['idea']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>문법 도움</td>
                                <td class="right aligned">
                                    <?php echo intval($view_ai_usage_counts['syntax']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>오류 수정</td>
                                <td class="right aligned">
                                    <?php echo intval($view_ai_usage_counts['debug']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>코드 생성</td>
                                <td class="right aligned">
                                    <?php echo intval($view_ai_usage_counts['generate']); ?>회
                                </td>
                            </tr>


                            <?php
                            if (
                                intval(
                                    $view_ai_usage_counts['understand']
                                ) > 0
                            ) {
                            ?>

                                <tr>
                                    <td>문제 이해</td>
                                    <td class="right aligned">
                                        <?php echo intval($view_ai_usage_counts['understand']); ?>회
                                    </td>
                                </tr>

                            <?php
                            }
                            ?>


                            <?php
                            if (
                                intval(
                                    $view_ai_usage_counts['explain']
                                ) > 0
                            ) {
                            ?>

                                <tr>
                                    <td>설명 요청</td>
                                    <td class="right aligned">
                                        <?php echo intval($view_ai_usage_counts['explain']); ?>회
                                    </td>
                                </tr>

                            <?php
                            }
                            ?>


                            <?php
                            if (
                                intval(
                                    $view_ai_usage_counts['other']
                                ) > 0
                            ) {
                            ?>

                                <tr>
                                    <td>기타·미분류</td>
                                    <td class="right aligned">
                                        <?php echo intval($view_ai_usage_counts['other']); ?>회
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

        </div>


        <!-- ==================================================
         수정 영역
         ================================================== -->

        <div class="column">

            <div class="ui segment">

                <h4 class="ui header">
                    재제출 수정 영역

                    <div class="sub header">
                        수정 기록 <?php
                                echo intval(
                                    $view_change_record_count
                                );
                                ?>회
                    </div>
                </h4>


                <?php
                if (
                    intval(
                        $view_change_record_count
                    ) <= 0
                ) {
                ?>

                    <div class="ui small info message">
                        수정 영역 기록이 없습니다.
                    </div>

                <?php
                } else {
                ?>

                    <table class="ui very basic compact table">

                        <tbody>

                            <tr>
                                <td>입력</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['input']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>출력</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['output']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>조건문</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['condition']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>반복문</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['loop']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>변수</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['variable']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>함수</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['function']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>배열 / 자료구조</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['data']); ?>회
                                </td>
                            </tr>

                            <tr>
                                <td>기타</td>
                                <td class="right aligned">
                                    <?php echo intval($view_change_type_counts['other']); ?>회
                                </td>
                            </tr>

                        </tbody>

                    </table>

                    <div
                        class="ui tiny grey message"
                        style="margin-top:1rem;">
                        한 번의 재제출에서 여러 수정 영역을 선택할 수 있으므로 항목별 합계는 수정 기록 횟수보다 클 수 있습니다.
                    </div>

                <?php
                }
                ?>

            </div>

        </div>

    </div>

    <!-- ======================================================
     차시별 과정 변화
     ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;">
        차시별 학습과정 현황
    </h3>


    <?php
    if (empty($view_lesson_process_summary)) {
    ?>

        <div class="ui info message">
            표시할 학습과정 정보가 없습니다.
        </div>

    <?php
    } else {
    ?>

        <div style="overflow-x:auto;">

            <table class="ui compact celled table">

                <thead>

                    <tr>

                        <th class="center aligned">
                            차시
                        </th>

                        <th>
                            제목
                        </th>

                        <th class="center aligned">
                            해결
                        </th>

                        <th class="center aligned">
                            시도
                        </th>

                        <th class="center aligned">
                            재시도
                        </th>

                        <th class="center aligned">
                            시도 문제당<br>평균 제출
                        </th>

                        <th class="center aligned">
                            계획
                        </th>

                        <th class="center aligned">
                            수정 메모
                        </th>

                        <th class="center aligned">
                            AI
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php
                    foreach (
                        $view_lesson_process_summary
                        as $lesson_summary
                    ) {
                    ?>

                        <tr>

                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['lesson_no']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $lesson_summary['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['solved_count']
                                );

                                echo '/';

                                echo intval(
                                    $lesson_summary['problem_count']
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['attempted_count']
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['retry_count']
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo htmlspecialchars(
                                    $lesson_summary['average_submit'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>

                                회

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['plan_count']
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['reflection_count']
                                );
                                ?>

                            </td>


                            <td class="center aligned">

                                <?php
                                echo intval(
                                    $lesson_summary['ai_count']
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

    <!-- ======================================================
         교사용 자동 분석
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;">
        교사용 학습기록 요약
    </h3>


    <?php
    if (empty($view_learning_analysis)) {
    ?>

        <div class="ui info message">
            분석할 수 있는 학습 기록이 아직 충분하지 않습니다.
        </div>

    <?php
    } else {
    ?>

        <div class="ui segment">

            <div class="ui relaxed list">

                <?php
                foreach (
                    $view_learning_analysis
                    as $analysis_text
                ) {
                ?>

                    <div class="item">

                        <i class="check circle outline icon"></i>

                        <div class="content">

                            <?php
                            echo htmlspecialchars(
                                $analysis_text,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>

                    </div>

                <?php
                }
                ?>

            </div>

        </div>

    <?php
    }
    ?>

    <div class="ui tiny grey message">

        이 내용은 학생의 제출 및 학습과정 기록을
        객관적인 수치로 요약한 자료입니다.

        차시별 문제 난이도와 학습 내용의 차이는
        반영하지 않으며 학생의 역량이나 성장을
        자동으로 판단하지 않습니다.

    </div>





    <!-- ======================================================
         차시별 학습현황
         ====================================================== -->

    <h3
        id="course-learning-status"
        class="ui dividing header"
        style="margin-top:2rem;">
        차시별 학습현황
    </h3>


    <?php
    if (empty($view_contests)) {
    ?>

        <div class="ui info message">
            이 수업에 연결된 차시가 없습니다.
        </div>

    <?php
    } else {
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

                    $contest_id =
                        intval($contest['contest_id']);


                    $display_title =
                        isset($contest['title'])
                        ? $contest['title']
                        : '';


                    $contest_submit_count =
                        isset($contest['submit_count'])
                        ? intval($contest['submit_count'])
                        : 0;


                    $contest_is_missed =
                        (
                            $view_focus === 'missed' &&
                            intval($contest['visible']) === 1 &&
                            intval($contest['has_started']) === 1 &&
                            $contest_submit_count === 0
                        );


                    $problem_rows =
                        isset($view_contest_problems[$contest_id])
                        ? $view_contest_problems[$contest_id]
                        : array();


                    $contest_has_retry =
                        false;


                    foreach ($problem_rows as $check_problem) {

                        $check_submit_count =
                            isset($check_problem['submit_count'])
                            ? intval($check_problem['submit_count'])
                            : 0;

                        $check_ac_count =
                            isset($check_problem['ac_count'])
                            ? intval($check_problem['ac_count'])
                            : 0;


                        if (
                            $check_submit_count >= 2 &&
                            $check_ac_count <= 0
                        ) {

                            $contest_has_retry = true;
                            break;
                        }
                    }
                ?>

                    <tr<?php
                        if ($contest_is_missed) {
                            echo ' class="warning"';
                        }
                        ?>>

                        <td class="center aligned">

                            <?php
                            echo intval(
                                $contest['lesson_no']
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $display_title,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>


                            <?php
                            if ($contest_is_missed) {
                            ?>

                                <span class="ui tiny orange basic label">
                                    미참여
                                </span>

                            <?php
                            }
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

                            echo '/';

                            echo intval(
                                $contest['problem_count']
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo !empty($contest['last_submit_time'])
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
                                                    ?>">
                                <?php
                                echo (
                                    $view_focus === 'retry' &&
                                    $contest_has_retry
                                )
                                    ? '문제별 닫기'
                                    : '문제별 보기';
                                ?>
                            </button>

                            <a
                                class="ui tiny blue basic button"
                                href="contest.php?cid=<?php
                                                        echo intval($contest['contest_id']);
                                                        ?>">
                                대회 보기
                            </a>

                        </td>

                        </tr>

                        <tr
                            id="course-problems-<?php echo intval($contest_id); ?>"
                            class="course-problem-detail"
                            style="<?php
                                    echo (
                                        $view_focus === 'retry' &&
                                        $contest_has_retry
                                    )
                                        ? 'display:table-row;'
                                        : 'display:none;';
                                    ?>">

                            <td colspan="6">

                                <?php
                                if (empty($problem_rows)) {
                                ?>

                                    <div class="ui small message">
                                        등록된 문제가 없습니다.
                                    </div>

                                <?php
                                } else {
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


                                                $is_persistent_unsolved =
                                                    (
                                                        $submit_count >= 2 &&
                                                        $ac_count <= 0
                                                    );


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

                                                <tr<?php
                                                    if (
                                                        $view_focus === 'retry' &&
                                                        $is_persistent_unsolved
                                                    ) {
                                                        echo ' class="negative"';
                                                    }
                                                    ?>>

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
                                                        } elseif ($ac_count > 0) {
                                                        ?>

                                                            <span class="ui tiny green label">
                                                                해결
                                                            </span>

                                                        <?php
                                                        } elseif ($is_persistent_unsolved) {
                                                        ?>

                                                            <span class="ui tiny red basic label">
                                                                반복 미해결
                                                            </span>

                                                        <?php
                                                        } else {
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
                                                        echo !empty($problem['first_submit_time'])
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
                                                        echo !empty($problem['last_submit_time'])
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
                                                            isset($problem['has_plan']) &&
                                                            intval($problem['has_plan']) === 1
                                                        ) {
                                                        ?>
                                                            <span class="ui mini basic label">
                                                                계획
                                                            </span>
                                                        <?php
                                                        }
                                                        ?>


                                                        <?php
                                                        if (
                                                            isset($problem['has_reflection']) &&
                                                            intval($problem['has_reflection']) === 1
                                                        ) {
                                                        ?>
                                                            <span class="ui mini basic label">
                                                                수정메모
                                                            </span>
                                                        <?php
                                                        }
                                                        ?>


                                                        <?php
                                                        if (
                                                            isset($problem['used_ai']) &&
                                                            intval($problem['used_ai']) === 1
                                                        ) {
                                                        ?>
                                                            <span class="ui mini basic label">
                                                                AI
                                                            </span>
                                                        <?php
                                                        }
                                                        ?>


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
                                                                                                                    ?>">
                                                                해결과정
                                                            </a>

                                                        <?php
                                                        } else {
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
    style="margin-top:2rem;">
    교사 누적 메모
</h3>


<?php
if (empty($view_student_memos)) {
?>

    <div class="ui info message">
        등록된 메모가 없습니다.
    </div>

<?php
} else {
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
                            ) . '차시';

                            if (!empty($memo['contest_title'])) {

                                echo ' - ';

                                echo htmlspecialchars(
                                    $memo['contest_title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            }
                        } else {

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
                        style="margin-top:0.5rem;">

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
                    isset($_SESSION[$OJ_NAME . '_administrator']) ||
                    (
                        isset($_SESSION[$OJ_NAME . '_user_id']) &&
                        $_SESSION[$OJ_NAME . '_user_id'] === $memo['created_by']
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
                                                                                                    ?>">
                            수정
                        </a>

                        <form
                            method="post"
                            action="course_student_memo_delete.php"
                            style="display:inline;"
                            onsubmit="return confirm('이 메모를 삭제하시겠습니까?');">

                            <?php include("./csrf.php"); ?>

                            <input
                                type="hidden"
                                name="course_id"
                                value="<?php echo intval($course_id); ?>">

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?php
                                        echo htmlspecialchars(
                                            $student_user_id,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>">

                            <input
                                type="hidden"
                                name="memo_id"
                                value="<?php echo intval($memo['id']); ?>">

                            <button
                                type="submit"
                                class="ui tiny red basic button">
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
    style="margin-top:2rem;">

    <h3 class="ui header">
        새 메모 작성
    </h3>


    <form
        class="ui form"
        method="post"
        action="course_student_memo_add.php">

        <?php include("./csrf.php"); ?>

        <input
            type="hidden"
            name="course_id"
            value="<?php echo intval($course_id); ?>">

        <input
            type="hidden"
            name="user_id"
            value="<?php
                    echo htmlspecialchars(
                        $student_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>">


        <div class="field">

            <label>
                메모 대상
            </label>

            <select
                name="contest_id"
                class="ui dropdown">

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
                                ?>">

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
                placeholder="학생의 문제 해결 과정, 재시도 과정, AI 활용, 수업 참여 등의 관찰 내용을 기록하세요."></textarea>

        </div>


        <button
            type="submit"
            class="ui teal button">
            <i class="save icon"></i>
            메모 저장
        </button>

    </form>

</div>

<!-- ======================================================
     문제별 교사 관찰 메모
     ====================================================== -->

<h3
    class="ui dividing header"
    style="margin-top:2rem;">

    문제별 관찰 메모

</h3>


<?php
if (empty($view_teacher_process_notes)) {
?>

    <div class="ui info message">
        등록된 문제별 관찰 메모가 없습니다.
    </div>

<?php
} else {
?>

    <div class="ui relaxed divided list">

        <?php
        foreach (
            $view_teacher_process_notes
            as $process_note
        ) {

            $lesson_no =
                isset($process_note['lesson_no'])
                ? intval($process_note['lesson_no'])
                : 0;


            $contest_title =
                isset($process_note['contest_title'])
                ? trim($process_note['contest_title'])
                : '';


            $problem_title =
                isset($process_note['problem_title'])
                ? trim($process_note['problem_title'])
                : '';


            $teacher_id =
                isset($process_note['teacher_id'])
                ? trim($process_note['teacher_id'])
                : '';


            $note_text =
                isset($process_note['note_text'])
                ? trim($process_note['note_text'])
                : '';


            if ($contest_title === '') {

                $contest_title =
                    $lesson_no > 0
                    ? $lesson_no . '차시'
                    : '차시 제목 없음';
            }


            if ($problem_title === '') {

                $problem_title =
                    '문제 ' .
                    intval(
                        $process_note['problem_id']
                    );
            }
        ?>

            <div class="item">

                <div class="content">

                    <div class="header">

                        <?php
                        if ($lesson_no > 0) {

                            echo intval($lesson_no);
                            echo '차시 - ';
                        }


                        echo htmlspecialchars(
                            $contest_title,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>


                    <div
                        style="
                            margin-top:0.35rem;
                            font-weight:600;
                        ">

                        <?php
                        echo htmlspecialchars(
                            $problem_title,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>


                    <div class="meta">

                        작성자:

                        <?php
                        echo htmlspecialchars(
                            $teacher_id,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                        ·

                        <?php
                        echo htmlspecialchars(
                            $process_note['created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                        <?php
                        if (
                            !empty($process_note['updated_at'])
                        ) {
                        ?>

                            · 수정:

                            <?php
                            echo htmlspecialchars(
                                $process_note['updated_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        <?php
                        }
                        ?>

                    </div>


                    <div
                        class="description"
                        style="margin-top:0.6rem;">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $note_text,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        );
                        ?>

                    </div>

                </div>

            </div>

        <?php
        }
        ?>

    </div>

<?php
}
?>


<!-- ======================================================
     세특 작성 참고자료
     ====================================================== -->

<h3
    class="ui dividing header"
    style="margin-top:2rem;">
    세특 작성 참고자료
</h3>


<div class="ui segment">

    <div class="ui small grey message">

        학생의 제출 기록과 교사 관찰 메모를
        그대로 모은 참고자료입니다.

        자동 평가나 학생의 역량 판단은 포함하지 않습니다.

    </div>


    <?php
    if (
        empty($view_student_record_reference_text)
    ) {
    ?>

        <div class="ui info message">
            참고할 학습 기록이 없습니다.
        </div>

    <?php
    } else {
    ?>

        <div class="ui form">

            <div class="field">

                <textarea
                    id="student-record-reference"
                    rows="12"
                    readonly><?php
                                echo htmlspecialchars(
                                    $view_student_record_reference_text,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?></textarea>

            </div>

        </div>


        <button
            type="button"
            id="copy-student-record-reference"
            class="ui small teal basic button">

            <i class="copy outline icon"></i>
            참고자료 복사

        </button>

    <?php
    }
    ?>

</div>

<!-- ======================================================
     세특 초안 생성 자료 선택
     ====================================================== -->

<h3
    class="ui dividing header"
    style="margin-top:2rem;">
    초안 생성 자료 선택
</h3>


<div class="ui segment">

    <div class="ui small grey message">

        세특 초안 작성에 참고할 학습 자료를 선택합니다.

        선택한 자료는 아래 미리보기에서 확인할 수 있으며,
        교사가 직접 작성하는 세특 초안의 참고자료로 사용할 수 있습니다.

        외부 AI API를 이용한 초안 생성 기능은
        현재 제공하지 않습니다.

    </div>


    <form
        id="record-ai-generate-form"
        class="ui form"
        method="post">

        <?php include("./csrf.php"); ?>

        <input
            type="hidden"
            name="course_id"
            value="<?php echo intval($course_id); ?>">

        <input
            type="hidden"
            name="user_id"
            value="<?php
                    echo htmlspecialchars(
                        $student_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>">


        <div
            class="ui form"
            style="margin-bottom:1rem;">

            <div class="grouped fields">

                <label>
                    사용할 자료
                </label>


                <div class="field">

                    <div class="ui checkbox">

                        <input
                            type="checkbox"
                            id="record-source-participation"
                            class="record-source-choice"
                            name="source_types[]"
                            value="participation"
                            checked>

                        <label for="record-source-participation">
                            수업 참여 및 문제 해결 기록
                        </label>

                    </div>

                </div>


                <div class="field">

                    <div class="ui checkbox">

                        <input
                            type="checkbox"
                            id="record-source-plan"
                            class="record-source-choice"
                            name="source_types[]"
                            value="plan"
                            checked>

                        <label for="record-source-plan">
                            풀이계획 기록
                        </label>

                    </div>

                </div>


                <div class="field">

                    <div class="ui checkbox">

                        <input
                            type="checkbox"
                            id="record-source-retry"
                            class="record-source-choice"
                            name="source_types[]"
                            value="retry"
                            checked>

                        <label for="record-source-retry">
                            재시도 및 수정 기록
                        </label>

                    </div>

                </div>


                <div class="field">

                    <div class="ui checkbox">

                        <input
                            type="checkbox"
                            id="record-source-ai"
                            class="record-source-choice"
                            name="source_types[]"
                            value="ai">

                        <label for="record-source-ai">
                            AI 활용 기록
                        </label>

                    </div>

                </div>


                <div class="field">

                    <div class="ui checkbox">

                        <input
                            type="checkbox"
                            id="record-source-memo"
                            class="record-source-choice"
                            name="source_types[]"
                            value="memo"
                            checked>

                        <label for="record-source-memo">
                            교사 누적 메모 및 문제별 관찰 메모
                        </label>

                    </div>

                </div>

            </div>

        </div>


        <!-- 각 영역의 실제 데이터 -->

        <?php
        foreach (
            $view_record_ai_sources
            as $source_key => $source_text
        ) {
        ?>

            <textarea
                hidden
                class="record-source-data"
                data-source="<?php
                                echo htmlspecialchars(
                                    $source_key,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>"><?php
                                    echo htmlspecialchars(
                                        $source_text,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?></textarea>

        <?php
        }
        ?>


        <div class="field">

            <label>
                선택된 참고자료 미리보기
            </label>

            <textarea
                id="record-source-preview"
                rows="10"
                readonly></textarea>

        </div>

        <!-- <div
            class="field"
            style="margin-top:1rem;">

            <div class="ui checkbox">

                <input
                    type="checkbox"
                    id="confirm-external-ai"
                    name="confirm_external_ai"
                    value="1"
                    required>

                <label for="confirm-external-ai">
                    선택한 참고자료가 AI API로 전송되는 것을 확인했습니다.
                </label>

            </div>

        </div>


        <button
            type="submit"
            class="ui violet button">

            <i class="magic icon"></i>
            AI 초안 생성

        </button> -->


    </form>

</div>


<!-- ======================================================
     세특 초안
     ====================================================== -->

<h3
    class="ui dividing header"
    style="margin-top:2rem;">
    세특 초안
</h3>


<div class="ui segment">

    <div class="ui small grey message">

        교사가 학습기록과 관찰 내용을 참고하여
        직접 작성하는 초안입니다.

        실제 학교생활기록부에 자동 반영되지 않습니다.

    </div>


    <form
        class="ui form"
        method="post"
        action="course_student_record_draft_save.php">

        <?php include("./csrf.php"); ?>


        <input
            type="hidden"
            name="course_id"
            value="<?php echo intval($course_id); ?>">


        <input
            type="hidden"
            name="user_id"
            value="<?php
                    echo htmlspecialchars(
                        $student_user_id,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>">


        <div class="field">

            <textarea
                name="draft_text"
                id="student-record-draft"
                rows="10"
                maxlength="5000"
                placeholder="학생의 문제 해결 과정, 재시도 과정, AI 활용 기록 및 교사 관찰 내용을 바탕으로 세특 초안을 작성하세요."><?php
                                                                                            echo htmlspecialchars(
                                                                                                $view_record_draft,
                                                                                                ENT_QUOTES,
                                                                                                'UTF-8'
                                                                                            );
                                                                                            ?></textarea>

        </div>


        <?php
        if (
            $view_record_draft_updated_at !== ''
        ) {
        ?>

            <div
                class="ui tiny grey message">

                마지막 저장:

                <?php
                echo htmlspecialchars(
                    $view_record_draft_updated_at,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <?php
                if (
                    $view_record_draft_updated_by !== ''
                ) {
                ?>

                    ·

                    <?php
                    echo htmlspecialchars(
                        $view_record_draft_updated_by,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                <?php
                }
                ?>

            </div>

        <?php
        }
        ?>


        <button
            type="submit"
            class="ui teal button">

            <i class="save icon"></i>
            초안 저장

        </button>


        <button
            type="button"
            id="copy-student-record-draft"
            class="ui basic button">

            <i class="copy outline icon"></i>
            초안 복사

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

                    } else {

                        detailRow.style.display =
                            'none';

                        this.textContent =
                            '문제별 보기';

                    }

                }
            );

        });

    })();

    (function() {

        var copyButton =
            document.getElementById(
                'copy-student-record-reference'
            );

        var referenceTextarea =
            document.getElementById(
                'student-record-reference'
            );


        if (
            !copyButton ||
            !referenceTextarea
        ) {
            return;
        }


        copyButton.addEventListener(
            'click',
            function() {

                var text =
                    referenceTextarea.value;


                if (!text) {
                    return;
                }


                if (
                    navigator.clipboard &&
                    navigator.clipboard.writeText
                ) {

                    navigator.clipboard
                        .writeText(text)
                        .then(function() {

                            copyButton.textContent =
                                '복사 완료';

                            setTimeout(
                                function() {

                                    copyButton.innerHTML =
                                        '<i class="copy outline icon"></i> 참고자료 복사';

                                },
                                1500
                            );

                        });

                    return;
                }


                referenceTextarea.select();

                document.execCommand(
                    'copy'
                );

                copyButton.textContent =
                    '복사 완료';

            }
        );

    })();

    (function() {

        var copyButton =
            document.getElementById(
                'copy-student-record-draft'
            );

        var draft =
            document.getElementById(
                'student-record-draft'
            );


        if (
            !copyButton ||
            !draft
        ) {
            return;
        }


        copyButton.addEventListener(
            'click',
            function() {

                if (!draft.value) {
                    return;
                }


                navigator.clipboard
                    .writeText(
                        draft.value
                    )
                    .then(function() {

                        var oldHtml =
                            copyButton.innerHTML;

                        copyButton.textContent =
                            '복사 완료';

                        setTimeout(
                            function() {
                                copyButton.innerHTML =
                                    oldHtml;
                            },
                            1500
                        );

                    });

            }
        );

    })();
    (function() {

        var choices =
            document.querySelectorAll(
                '.record-source-choice'
            );

        var sourceElements =
            document.querySelectorAll(
                '.record-source-data'
            );

        var preview =
            document.getElementById(
                'record-source-preview'
            );


        if (!preview) {
            return;
        }


        var sourceMap = {};


        sourceElements.forEach(
            function(element) {

                var key =
                    element.getAttribute(
                        'data-source'
                    );

                sourceMap[key] =
                    element.value;
            }
        );


        function updateRecordSourcePreview() {

            var selectedTexts = [];


            choices.forEach(
                function(choice) {

                    if (!choice.checked) {
                        return;
                    }


                    var key =
                        choice.value;


                    if (
                        sourceMap[key] &&
                        sourceMap[key].trim() !== ''
                    ) {

                        selectedTexts.push(
                            sourceMap[key]
                        );
                    }

                }
            );


            preview.value =
                selectedTexts.join(
                    "\n\n"
                );
        }


        choices.forEach(
            function(choice) {

                choice.addEventListener(
                    'change',
                    updateRecordSourcePreview
                );

            }
        );


        updateRecordSourcePreview();

    })();
</script>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>