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
            문제 구성 수정
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
            <?php echo intval($view_contest['lesson_no']); ?>차시
            ·
            <?php
            echo htmlspecialchars(
                $view_contest['title'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>

    </div>


    <div class="ui segment">

        <h3 class="ui header">
            문제은행에서 문제 찾기
        </h3>

        <form
            class="ui form"
            method="get"
            action="course_contest_problem_edit.php"
        >

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


            <div class="fields">

                <div class="twelve wide field">

                    <input
                        type="text"
                        name="search"
                        value="<?php
                            echo htmlspecialchars(
                                $view_search,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        placeholder="문제 번호, 제목 또는 출처"
                    >

                </div>


                <div class="four wide field">

                    <button
                        type="submit"
                        class="ui blue button"
                    >
                        <i class="search icon"></i>
                        검색
                    </button>

                </div>

            </div>

        </form>

    </div>


    <form
        class="ui form"
        method="post"
        action="course_contest_problem_update.php"
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


        <div class="ui segment">

            <h3 class="ui dividing header">
                현재 문제 구성
            </h3>

            <?php
            if (empty($view_current_problems)) {
            ?>

                <div class="ui warning message">
                    현재 등록된 문제가 없습니다.
                </div>

            <?php
            }
            else {
            ?>

                <table class="ui celled striped table">

                    <thead>
                        <tr>
                            <th class="center aligned">
                                사용
                            </th>

                            <th class="center aligned">
                                순서
                            </th>

                            <th class="center aligned">
                                문제 번호
                            </th>

                            <th>
                                제목
                            </th>

                            <th class="center aligned">
                                점수
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    foreach ($view_current_problems as $problem) {

                        $problem_id =
                            intval($problem['problem_id']);

                        $problem_label =
                            chr(
                                ord('A') +
                                intval($problem['num'])
                            );
                    ?>

                        <tr>

                            <td class="center aligned">

                                <input
                                    type="checkbox"
                                    name="problem_ids[]"
                                    value="<?php echo $problem_id; ?>"
                                    checked
                                >

                            </td>


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


                            <td class="center aligned">
                                <?php echo $problem_id; ?>
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

                                <input
                                    type="number"
                                    name="score[<?php echo $problem_id; ?>]"
                                    min="0"
                                    step="1"
                                    value="<?php
                                        echo isset($problem['score'])
                                            ? intval($problem['score'])
                                            : 100;
                                    ?>"
                                    style="width:100px;"
                                >

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
        if (!empty($view_source_problems)) {
        ?>

            <div class="ui segment">

                <h3 class="ui dividing header">
                    원본 대회에서 문제 추가
                </h3>

                <table class="ui celled striped table">

                    <thead>
                        <tr>
                            <th class="center aligned">
                                추가
                            </th>

                            <th class="center aligned">
                                문제 번호
                            </th>

                            <th>
                                제목
                            </th>

                            <th class="center aligned">
                                점수
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    foreach ($view_source_problems as $problem) {

                        $problem_id =
                            intval($problem['problem_id']);

                        $already_added =
                            isset(
                                $view_current_problem_map[$problem_id]
                            );
                    ?>

                        <tr>

                            <td class="center aligned">

                                <?php
                                if ($already_added) {
                                ?>

                                    <span class="ui tiny grey label">
                                        등록됨
                                    </span>

                                <?php
                                }
                                else {
                                ?>

                                    <input
                                        type="checkbox"
                                        name="problem_ids[]"
                                        value="<?php echo $problem_id; ?>"
                                    >

                                <?php
                                }
                                ?>

                            </td>


                            <td class="center aligned">
                                <?php echo $problem_id; ?>
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
                                if ($already_added) {
                                ?>

                                    <?php
                                    echo isset(
                                        $view_current_problem_map[$problem_id]['score']
                                    )
                                        ? intval(
                                            $view_current_problem_map[$problem_id]['score']
                                        )
                                        : 100;
                                    ?>

                                <?php
                                }
                                else {
                                ?>

                                    <input
                                        type="number"
                                        name="score[<?php echo $problem_id; ?>]"
                                        min="0"
                                        step="1"
                                        value="<?php
                                            echo isset($problem['score'])
                                                ? intval($problem['score'])
                                                : 100;
                                        ?>"
                                        style="width:100px;"
                                    >

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

            </div>

                <?php
        }
        ?>


        <?php
        if ($view_search !== '') {
        ?>

            <div class="ui segment">

                <h3 class="ui dividing header">
                    문제은행 검색 결과
                </h3>


                <?php
                if (
                    empty(
                        $view_search_problem_rows
                    )
                ) {
                ?>

                    <div class="ui info message">
                        검색된 문제가 없습니다.
                    </div>

                <?php
                }
                else {
                ?>

                    <table class="ui celled striped table">

                        <thead>

                            <tr>

                                <th class="center aligned">
                                    추가
                                </th>

                                <th class="center aligned">
                                    문제 번호
                                </th>

                                <th>
                                    제목
                                </th>

                                <th>
                                    출처
                                </th>

                                <th class="center aligned">
                                    점수
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php
                        foreach (
                            $view_search_problem_rows
                            as $problem
                        ) {

                            $problem_id =
                                intval(
                                    $problem[
                                        'problem_id'
                                    ]
                                );

                            $already_added =
                                isset(
                                    $view_current_problem_map[
                                        $problem_id
                                    ]
                                );

                            $is_source_problem =
                                isset(
                                    $view_source_problem_map[
                                        $problem_id
                                    ]
                                );
                        ?>

                            <tr>

                                <td class="center aligned">

                                    <?php
                                    if ($already_added) {
                                    ?>

                                        <span
                                            class="ui tiny grey label"
                                        >
                                            등록됨
                                        </span>

                                    <?php
                                    }
                                    elseif ($is_source_problem) {
                                    ?>

                                        <span
                                            class="ui tiny blue label"
                                        >
                                            원본 목록
                                        </span>

                                    <?php
                                    }
                                    else {
                                    ?>

                                        <input
                                            type="checkbox"
                                            name="problem_ids[]"
                                            value="<?php
                                                echo $problem_id;
                                            ?>"
                                        >

                                    <?php
                                    }
                                    ?>

                                </td>


                                <td class="center aligned">

                                    <?php
                                    echo $problem_id;
                                    ?>

                                </td>


                                <td>

                                    <a
                                        href="problem.php?id=<?php
                                            echo $problem_id;
                                        ?>"
                                        target="_blank"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $problem['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                    </a>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        isset(
                                            $problem['source']
                                        )
                                            ? $problem['source']
                                            : '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </td>


                                <td class="center aligned">

                                    <?php
                                    if (
                                        !$already_added &&
                                        !$is_source_problem
                                    ) {
                                    ?>

                                        <input
                                            type="number"
                                            name="score[<?php
                                                echo $problem_id;
                                            ?>]"
                                            min="0"
                                            step="1"
                                            value="100"
                                            style="width:100px;"
                                        >

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

            </div>

        <?php
        }
        ?>


        <div class="ui warning message">

            <div class="header">
                문제 구성 변경 시 주의
            </div>

            <p>
                기존 제출 기록과 학생의 해결과정은 삭제하지 않습니다.
                문제를 제거해도 이전 제출 기록은 그대로 보존됩니다.
            </p>

        </div>


        <button
            type="submit"
            class="ui orange button"
            onclick="return confirm('문제 구성을 변경하시겠습니까?');"
        >
            <i class="save icon"></i>
            문제 구성 저장
        </button>

    </form>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>