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
            차시 추가
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


    <div class="ui stackable two column grid">

        <!-- ======================================================
        1. 문제 구성을 가져와 새 Contest 생성
        ======================================================= -->

        <div class="column">

            <div class="ui segment">

                <h3 class="ui teal header">
                    새 차시 만들기
                </h3>

                <p>
                    기존 대회의 문제 구성을 가져와
                    Course 전용 Contest를 새로 생성합니다.
                    기존 제출 기록은 가져오지 않습니다.
                </p>

                <form
                    class="ui form"
                    method="get"
                    action="course_contest_problem_select.php"
                >

                    <input
                        type="hidden"
                        name="course_id"
                        value="<?php echo intval($course_id); ?>"
                    >


                    <div class="field">

                        <label>
                            차시 번호
                        </label>

                        <input
                            type="number"
                            name="lesson_no"
                            min="1"
                            value="<?php
                                echo intval($view_next_lesson_no);
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            문제 구성을 가져올 원본 대회 번호
                        </label>

                        <input
                            type="number"
                            name="source_contest_id"
                            min="1"
                            placeholder="예: 2916"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="ui teal button"
                    >
                        <i class="arrow right icon"></i>
                        문제 선택으로 이동
                    </button>

                </form>

            </div>

        </div>


        <!-- ======================================================
        2. 기존 Contest와 채점기록 연결
        ======================================================= -->

        <div class="column">

            <div class="ui segment">

                <h3 class="ui orange header">
                    기존 대회 연결
                </h3>

                <p>
                    <p>
                        대회 진행 상태와 관계없이 기존 Contest를 그대로 연결합니다.
                        기존 기록을 원본에서 직접 참조해 Course 학습현황에 반영합니다.
                    </p>
                </p>

                <form
                    class="ui form"
                    method="get"
                    action="course_contest_add.php"
                >

                    <input
                        type="hidden"
                        name="course_id"
                        value="<?php echo intval($course_id); ?>"
                    >

                    <input
                        type="hidden"
                        name="preview_existing"
                        value="1"
                    >


                    <div class="field">

                        <label>
                            차시 번호
                        </label>

                        <input
                            type="number"
                            name="link_lesson_no"
                            min="1"
                            value="<?php
                                echo intval($view_link_lesson_no);
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            연결할 기존 대회 번호
                        </label>

                        <input
                            type="number"
                            name="existing_contest_id"
                            min="1"
                            value="<?php
                                echo $view_existing_contest_id > 0
                                    ? intval($view_existing_contest_id)
                                    : '';
                            ?>"
                            placeholder="예: 2500"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="ui orange button"
                    >
                        <i class="search icon"></i>
                        연결 전 확인
                    </button>

                </form>

            </div>

        </div>

    </div>


    <!-- ==========================================================
    기존 대회 미리보기 결과
    =========================================================== -->

    <?php
    if ($view_link_preview_requested) {
    ?>

        <?php
        if ($view_link_error_message !== '') {
        ?>

            <div class="ui negative message">

                <div class="header">
                    기존 대회를 연결할 수 없습니다.
                </div>

                <?php
                echo htmlspecialchars(
                    $view_link_error_message,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php
        }
        elseif (is_array($view_link_candidate)) {

            $contest_state =
                isset($view_link_candidate['contest_state'])
                    ? strtolower(
                        trim($view_link_candidate['contest_state'])
                    )
                    : 'unscheduled';

            $contest_state_labels = array(
                'upcoming'    => '시작 전',
                'running'     => '진행 중',
                'ended'       => '종료',
                'unscheduled' => '시간 미설정'
            );

            $contest_state_label =
                isset($contest_state_labels[$contest_state])
                    ? $contest_state_labels[$contest_state]
                    : '알 수 없음';
        ?>

            <div class="ui segment">

                <h3 class="ui header">
                    기존 대회 연결 미리보기
                </h3>


                <table class="ui celled definition table">

                    <tbody>

                        <tr>
                            <td>대회 번호</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_candidate['contest_id']
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>제목</td>
                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $view_link_candidate['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>대회 상태</td>
                            <td>
                                <span class="ui small basic label">
                                    <?php
                                    echo htmlspecialchars(
                                        $contest_state_label,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td>생성자</td>
                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $view_link_candidate['user_id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>시작 시간</td>
                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $view_link_candidate['start_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>종료 시간</td>
                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $view_link_candidate['end_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <td>문제 수</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats['problem_count']
                                );
                                ?>개
                            </td>
                        </tr>

                        <tr>
                            <td>현재 Course 학생</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'active_student_count'
                                    ]
                                );
                                ?>명
                            </td>
                        </tr>

                        <tr>
                            <td>제출 기록이 있는 Course 학생</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'course_submitter_count'
                                    ]
                                );
                                ?>명
                            </td>
                        </tr>

                        <tr>
                            <td>Course 학생 제출 수</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'course_submission_count'
                                    ]
                                );
                                ?>회
                            </td>
                        </tr>

                        <tr>
                            <td>Course 외부 제출자</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'outside_submitter_count'
                                    ]
                                );
                                ?>명
                            </td>
                        </tr>

                        <tr>
                            <td>전체 제출자 / 제출 수</td>
                            <td>
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'total_submitter_count'
                                    ]
                                );
                                ?>명 /
                                <?php
                                echo intval(
                                    $view_link_stats[
                                        'total_submission_count'
                                    ]
                                );
                                ?>회
                            </td>
                        </tr>

                    </tbody>

                </table>


                <?php
                if (
                    intval(
                        $view_link_stats['outside_submitter_count']
                    ) > 0
                ) {
                ?>

                    <div class="ui warning message">

                        Course에 등록되지 않은 제출자가 있습니다.
                        연결 후 Course 통계에는 현재 Course 학생의
                        기록만 반영됩니다.

                    </div>

                <?php
                }
                ?>


                <div class="ui info message">

                    기존 Contest, 문제 구성과 제출 기록은 변경하지 않습니다.
                    Course와의 연결 관계만 추가하며 처음에는 숨김 상태로
                    등록됩니다. 차시를 공개하면 활성 Course 학생에게
                    해당 대회의 참가 권한이 자동으로 부여됩니다.

                </div>


                <form
                    method="post"
                    action="course_contest_link.php"
                    onsubmit="return confirm(
                        '이 기존 대회와 채점기록을 현재 수업에 연결하시겠습니까?'
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
                        value="<?php
                            echo intval(
                                $view_link_candidate['contest_id']
                            );
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="lesson_no"
                        value="<?php
                            echo intval($view_link_lesson_no);
                        ?>"
                    >


                    <button
                        type="submit"
                        class="ui orange button"
                    >
                        <i class="linkify icon"></i>
                        기존 대회 연결
                    </button>

                </form>

            </div>

        <?php
        }
        ?>

    <?php
    }
    ?>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>