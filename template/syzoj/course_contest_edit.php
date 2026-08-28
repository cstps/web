<?php
include("template/$OJ_TEMPLATE/header.php");
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<style>

.course-lang-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.course-lang-option {
    position: relative;
    cursor: pointer;
}

.course-lang-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.course-lang-option span {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 38px;
    padding: 0 14px;

    border: 1px solid #d4d4d5;
    border-radius: 4px;

    background: #ffffff;

    font-weight: 600;
    cursor: pointer;
}

.course-lang-option input:checked + span {
    background: #00b5ad;
    border-color: #00b5ad;
    color: #ffffff;
}

.course-lang-option input:focus + span {
    outline: 2px solid rgba(0, 181, 173, 0.25);
}

</style>

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


                <!-- =========================================
                제출 가능 언어
                ========================================== -->

                <div class="field">

                    <label>
                        제출 가능 언어
                    </label>

                    <?php

                    $current_langmask =
                        intval($view_contest['langmask']);


                    // --------------------------------------------------------
                    // Course에서 표시할 언어
                    // Python3이 있으면 Python보다 우선 사용
                    // --------------------------------------------------------

                    $course_language_specs = array(

                        array(
                            'label' => 'C++',
                            'aliases' => array('C++')
                        ),

                        array(
                            'label' => 'Python',
                            'aliases' => array('Python3', 'Python')
                        ),

                        array(
                            'label' => 'JavaScript',
                            'aliases' => array('JavaScript')
                        ),

                        array(
                            'label' => 'Java',
                            'aliases' => array('Java')
                        )

                    );


                    $course_language_options =
                        array();


                    foreach (
                        $course_language_specs
                        as $language_spec
                    ) {

                        foreach (
                            $language_spec['aliases']
                            as $language_alias
                        ) {

                            $language_id =
                                array_search(
                                    $language_alias,
                                    $language_name,
                                    true
                                );


                            if ($language_id !== false) {

                                $course_language_options[
                                    intval($language_id)
                                ] =
                                    $language_spec['label'];

                                break;
                            }
                        }
                    }

                    ?>


                    <div class="course-lang-options">

                        <?php
                        foreach (
                            $course_language_options
                            as $language_id => $display_name
                        ) {

                            // HUSTOJ
                            // bit = 0 : 허용
                            // bit = 1 : 금지
                            $is_allowed =
                                (
                                    $current_langmask &
                                    (1 << $language_id)
                                ) === 0;
                        ?>

                            <label class="course-lang-option">

                                <input
                                    type="checkbox"
                                    name="lang[]"
                                    value="<?php echo intval($language_id); ?>"
                                    <?php
                                    echo $is_allowed
                                        ? 'checked'
                                        : '';
                                    ?>
                                >

                                <span>
                                    <?php
                                    echo htmlspecialchars(
                                        $display_name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                            </label>

                        <?php
                        }
                        ?>

                    </div>


                    <div
                        class="ui pointing basic label"
                        style="margin-top:8px;"
                    >
                        하나 이상의 언어를 선택하세요.
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
