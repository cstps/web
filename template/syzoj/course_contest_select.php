<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>
<style>
.course-contest-select-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f9fafb !important;
}
.course-contest-select-scroll {
    max-height: 520px;
    overflow: auto;
    border: 1px solid #ddd;
}


@media (max-width: 768px) {
    .course-contest-select-scroll {
        max-height: 420px;
    }
}
</style>

<div class="course-page">

    <div class="course-page-header">

        <a
            class="ui small basic button"
            href="course_contest_add.php?course_id=<?php
                echo intval($course_id);
            ?>"
        >
            <i class="left arrow icon"></i>
            차시 추가로 돌아가기
        </a>


        <h1 class="ui header">

            <?php
            echo htmlspecialchars(
                $view_mode_title,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </h1>


        <div class="course-page-description">

            <?php
            echo htmlspecialchars(
                $view_course['course_name'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

            ·

            <?php
            echo intval($lesson_no);
            ?>차시

        </div>

    </div>


    <div class="ui info message">

        <?php
        echo htmlspecialchars(
            $view_mode_description,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>

    </div>


    <!-- ======================================================
         검색
         ====================================================== -->

    <div class="ui segment">

        <form
            class="ui form"
            method="get"
            action="course_contest_select.php"
        >

            <input
                type="hidden"
                name="course_id"
                value="<?php echo intval($course_id); ?>"
            >

            <input
                type="hidden"
                name="lesson_no"
                value="<?php echo intval($lesson_no); ?>"
            >

            <input
                type="hidden"
                name="mode"
                value="<?php
                    echo htmlspecialchars(
                        $mode,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >


            <div class="fields">

                <div class="twelve wide field">

                    <label>
                        대회 검색
                    </label>

                    <input
                        type="text"
                        name="keyword"
                        value="<?php
                            echo htmlspecialchars(
                                $keyword,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        placeholder="대회 번호 또는 제목"
                    >

                </div>


                <div class="four wide field">

                    <label>
                        &nbsp;
                    </label>

                    <button
                        type="submit"
                        class="ui blue fluid button"
                    >
                        <i class="search icon"></i>
                        검색
                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- ======================================================
         검색 정책 안내
         ====================================================== -->

    <?php
    if ($mode === 'copy') {
    ?>

        <div class="ui small message">

            <strong>문제 가져오기</strong>

            <div style="margin-top:0.4rem;">

                본인이 만든 대회는 항상 선택할 수 있습니다.

                <?php
                if ($is_admin) {
                ?>

                    관리자는 모든 활성 대회를 선택할 수 있습니다.

                <?php
                }
                elseif ($is_contest_creator) {
                ?>

                    다른 사용자가 만든 대회는
                    생성자가 복사를 허용한 경우에만 선택할 수 있습니다.

                <?php
                }
                ?>

            </div>

        </div>

    <?php
    }
    else {
    ?>

        <div class="ui small warning message">

            <strong>기존 대회 연결</strong>

            <div style="margin-top:0.4rem;">

                기존 대회와 제출 기록을 그대로 연결하므로
                일반 교사는 자신이 생성한 대회만 선택할 수 있습니다.

                <?php
                if ($is_admin) {
                ?>
                    관리자는 전체 대회를 선택할 수 있습니다.
                <?php
                }
                ?>

            </div>

        </div>

    <?php
    }
    ?>


    <!-- ======================================================
         대회 목록
         ====================================================== -->

    <h3
        class="ui dividing header"
        style="margin-top:2rem;"
    >
        대회 목록
    </h3>


    <?php
    if (empty($view_contests)) {
    ?>

        <div class="ui message">
            조건에 맞는 대회가 없습니다.
        </div>

    <?php
    }
    else {
    ?>

        <div class="course-contest-select-scroll">

            <table
                class="ui compact celled striped table course-contest-select-table"
                style="margin:0;"
            >

                <thead>

                    <tr>
                        <th class="center aligned">
                            선택
                        </th>

                        <th class="center aligned">
                            대회번호
                        </th>

                        <th>
                            제목
                        </th>

                        <th>
                            생성자
                        </th>

                        <th class="center aligned">
                            문제
                        </th>

                        <th class="center aligned">
                            상태
                        </th>

                        <?php
                        if ($mode === 'copy') {
                        ?>

                            <th class="center aligned">
                                복사
                            </th>

                        <?php
                        }
                        ?>

                        <th>
                            시작
                        </th>

                        <th>
                            종료
                        </th>

                        

                    </tr>

                </thead>


                <tbody>

                <?php
                foreach ($view_contests as $contest) {

                    $contest_id =
                        intval($contest['contest_id']);

                    $course_link_count =
                        intval($contest['course_link_count']);

                    $contest_state =
                        isset($contest['contest_state'])
                            ? $contest['contest_state']
                            : 'unscheduled';


                    switch ($contest_state) {

                        case 'upcoming':

                            $state_label =
                                '시작 전';

                            $state_class =
                                'blue';

                            break;


                        case 'running':

                            $state_label =
                                '진행 중';

                            $state_class =
                                'green';

                            break;


                        case 'ended':

                            $state_label =
                                '종료';

                            $state_class =
                                'grey';

                            break;


                        default:

                            $state_label =
                                '시간 미설정';

                            $state_class =
                                'orange';

                            break;
                    }


                    $is_owner =
                        isset($contest['user_id']) &&
                        trim($contest['user_id']) ===
                        $user_id;


                    $can_select =
                        true;


                    // ----------------------------------------
                    // 기존 Contest 연결(link)인 경우에만
                    // 이미 다른 Course와 관계가 있으면 선택 불가
                    //
                    // copy는 문제 구성만 가져오므로
                    // 기존 Course 연결 여부와 관계없이 사용 가능
                    // ----------------------------------------

                    if (
                        $mode === 'link' &&
                        $course_link_count > 0
                    ) {
                        $can_select = false;
                    }


                    // ----------------------------------------
                    // copy 정책
                    // ----------------------------------------

                    if (
                        $mode === 'copy' &&
                        !$is_admin &&
                        !$is_owner
                    ) {

                        if (
                            !$is_contest_creator ||
                            intval($contest['allow_copy']) !== 1
                        ) {

                            $can_select = false;
                        }
                    }


                    // ----------------------------------------
                    // link 정책
                    // ----------------------------------------

                    if (
                        $mode === 'link' &&
                        !$is_admin &&
                        !$is_owner
                    ) {

                        $can_select = false;
                    }
                ?>

                    <tr>
                        <td class="center aligned">

                            <?php
                            if (
                                $mode === 'link' &&
                                $course_link_count > 0
                            ) {
                            ?>

                                <span class="ui tiny grey label">
                                    이미 다른 수업에 연결
                                </span>

                            <?php
                            }
                            elseif (!$can_select) {
                            ?>

                                <span class="ui tiny grey label">
                                    선택 불가
                                </span>

                            <?php
                            }
                            elseif ($mode === 'copy') {
                            ?>

                                <a
                                    class="ui tiny teal button"
                                    href="course_contest_problem_select.php?course_id=<?php
                                        echo intval($course_id);
                                    ?>&lesson_no=<?php
                                        echo intval($lesson_no);
                                    ?>&source_contest_id=<?php
                                        echo $contest_id;
                                    ?>"
                                >
                                    문제 선택
                                </a>

                            <?php
                            }
                            else {
                            ?>

                                <a
                                    class="ui tiny blue button"
                                    href="course_contest_add.php?course_id=<?php
                                        echo intval($course_id);
                                    ?>&preview_existing=1&link_lesson_no=<?php
                                        echo intval($lesson_no);
                                    ?>&existing_contest_id=<?php
                                        echo $contest_id;
                                    ?>"
                                >
                                    연결 확인
                                </a>

                            <?php
                            }
                            ?>

                        </td>

                        <td class="center aligned">

                            <?php
                            echo $contest_id;
                            ?>

                        </td>


                        <td>

                            <a
                                href="contest.php?cid=<?php
                                    echo $contest_id;
                                ?>"
                                target="_blank"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $contest['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </a>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $contest['user_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                            <?php
                            if ($is_owner) {
                            ?>

                                <span class="ui tiny blue basic label">
                                    내 대회
                                </span>

                            <?php
                            }
                            ?>

                        </td>


                        <td class="center aligned">

                            <?php
                            echo intval(
                                $contest['problem_count']
                            );
                            ?>

                        </td>


                        <td class="center aligned">

                            <span class="ui tiny <?php
                                echo $state_class;
                            ?> label">

                                <?php
                                echo $state_label;
                                ?>

                            </span>

                        </td>


                        <?php
                        if ($mode === 'copy') {
                        ?>

                            <td class="center aligned">

                                <?php
                                if ($is_owner || $is_admin) {
                                ?>

                                    <span class="ui tiny blue basic label">
                                        사용 가능
                                    </span>

                                <?php
                                }
                                elseif (
                                    intval(
                                        $contest['allow_copy']
                                    ) === 1
                                ) {
                                ?>

                                    <span class="ui tiny green label">
                                        허용
                                    </span>

                                <?php
                                }
                                else {
                                ?>

                                    <span class="ui tiny grey label">
                                        금지
                                    </span>

                                <?php
                                }
                                ?>

                            </td>

                        <?php
                        }
                        ?>


                        <td>

                            <?php
                            echo !empty(
                                $contest['start_time']
                            )
                                ? htmlspecialchars(
                                    $contest['start_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '-';
                            ?>

                        </td>


                        <td>

                            <?php
                            echo !empty(
                                $contest['end_time']
                            )
                                ? htmlspecialchars(
                                    $contest['end_time'],
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

        </div>

    <?php
    }
    ?>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>