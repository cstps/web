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
            차시 수정
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


    <div class="ui segment">

        <form
            class="ui form"
            method="post"
            action="course_contest_update.php"
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
                value="<?php echo intval($contest_id); ?>"
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
                        echo intval($view_contest['lesson_no']);
                    ?>"
                    required
                >

            </div>

            <?php
            if ($view_link_type === 'created') {
            ?>

                <div class="field">

                    <label>
                        차시 제목
                    </label>

                    <input
                        type="text"
                        name="contest_title"
                        maxlength="100"
                        value="<?php
                            echo htmlspecialchars(
                                $view_contest['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        required
                    >

                </div>


                <div class="two fields">

                    <div class="field">

                        <label>
                            시작 시간
                        </label>

                        <input
                            type="datetime-local"
                            name="start_time"
                            value="<?php
                                echo date(
                                    'Y-m-d\TH:i',
                                    strtotime(
                                        $view_contest['start_time']
                                    )
                                );
                            ?>"
                            required
                        >

                    </div>


                    <div class="field">

                        <label>
                            종료 시간
                        </label>

                        <input
                            type="datetime-local"
                            name="end_time"
                            value="<?php
                                echo date(
                                    'Y-m-d\TH:i',
                                    strtotime(
                                        $view_contest['end_time']
                                    )
                                );
                            ?>"
                            required
                        >

                    </div>

                </div>

            <?php
            }
            else {
            ?>

                <div class="ui warning message">

                    <div class="header">
                        기존 대회 연결 차시
                    </div>

                    기존 대회 번호
                    <strong>
                        <?php echo intval($view_contest['contest_id']); ?>
                    </strong>
                    의 제목과 진행 시간은 Course에서 변경하지 않습니다.

                </div>


                <div class="field">

                    <label>
                        기존 대회 제목
                    </label>

                    <input
                        type="text"
                        value="<?php
                            echo htmlspecialchars(
                                $view_contest['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        readonly
                    >

                </div>


                <div class="two fields">

                    <div class="field">

                        <label>
                            시작 시간
                        </label>

                        <input
                            type="text"
                            value="<?php
                                echo htmlspecialchars(
                                    $view_contest['start_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            readonly
                        >

                    </div>


                    <div class="field">

                        <label>
                            종료 시간
                        </label>

                        <input
                            type="text"
                            value="<?php
                                echo htmlspecialchars(
                                    $view_contest['end_time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>"
                            readonly
                        >

                    </div>

                </div>

            <?php
            }
            ?>


            <div class="ui info message">

            현재 공개 상태:
            <strong>
                <?php
                echo intval($view_contest['visible']) === 1
                    ? '공개'
                    : '숨김';
                ?>
            </strong>

            <br>

            공개·숨김 변경은 수업 화면의 공개 상태 버튼에서
            처리합니다.

        </div>


            <?php
            if (
                $view_link_type === 'created' &&
                !empty($view_contest['source_contest_id'])
            ) {
            ?>

                <div class="ui info message">

                    원본 대회 번호:
                    <strong>
                        <?php
                        echo intval(
                            $view_contest['source_contest_id']
                        );
                        ?>
                    </strong>

                </div>

            <?php
            }
            ?>


            <button
                type="submit"
                class="ui teal button"
            >
                <i class="save icon"></i>
                수정 내용 저장
            </button>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>