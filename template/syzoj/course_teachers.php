<?php
include("template/$OJ_TEMPLATE/header.php");
require_once('./include/csrf_check.php');
?>

<link
    rel="stylesheet"
    href="template/<?php echo $OJ_TEMPLATE; ?>/css/course.css"
>

<div class="course-page">

    <div class="course-page-header">

        <a
            href="course_view.php?course_id=<?php echo intval($course_id); ?>"
            class="ui small basic button"
        >
            <i class="left arrow icon"></i>
            수업으로 돌아가기
        </a>

        <h1 class="ui header">
            교사 관리
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
                    <th>등록일</th>
                    <th></th>
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

                        <?php
                        if ($teacher['role'] === 'owner') {
                        ?>

                            <span class="ui small blue label">
                                책임교사
                            </span>

                        <?php
                        }
                        else {
                        ?>

                            <form
                                method="post"
                                action="course_teacher_update.php"
                                style="display:flex; align-items:center; gap:6px;"
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
                                            $teacher['user_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                                <select
                                    name="role"
                                    class="ui compact dropdown"
                                >

                                    <option
                                        value="teacher"
                                        <?php
                                        if ($teacher['role'] === 'teacher') {
                                            echo 'selected';
                                        }
                                        ?>
                                    >
                                        담당교사
                                    </option>

                                    <option
                                        value="assistant"
                                        <?php
                                        if ($teacher['role'] === 'assistant') {
                                            echo 'selected';
                                        }
                                        ?>
                                    >
                                        보조교사
                                    </option>

                                </select>

                                <button
                                    type="submit"
                                    class="ui tiny blue basic button"
                                >
                                    변경
                                </button>

                            </form>

                        <?php
                        }
                        ?>

                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $teacher['joined_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td class="center aligned">

                        <?php
                        if ($teacher['role'] !== 'owner') {
                        ?>

                            <form
                                method="post"
                                action="course_teacher_delete.php"
                                style="display:inline;"
                                onsubmit="return confirm(
                                    '이 교사를 수업에서 제외하시겠습니까?'
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
                                    name="user_id"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $teacher['user_id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >

                                <button
                                    type="submit"
                                    class="ui tiny red basic button"
                                >
                                    제외
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
    if (!empty($view_removed_teachers)) {
    ?>

        <h3
            class="ui dividing header"
            style="margin-top:2rem;"
        >
            제외된 교사
        </h3>


        <table class="ui celled table">

            <thead>
                <tr>
                    <th>아이디</th>
                    <th>이름</th>
                    <th>소속</th>
                    <th>이전 역할</th>
                    <th>제외일</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php
            foreach ($view_removed_teachers as $teacher) {

                switch ($teacher['role']) {

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


                    <td>
                        <?php
                        echo htmlspecialchars(
                            $teacher['updated_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>


                    <td class="center aligned">

                        <form
                            method="post"
                            action="course_teachers.php?course_id=<?php
                                echo intval($course_id);
                            ?>"
                            style="display:inline;"
                            onsubmit="return confirm(
                                '이 교사를 다시 등록하시겠습니까?'
                            );"
                        >

                            <?php include("./csrf.php"); ?>

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?php
                                    echo htmlspecialchars(
                                        $teacher['user_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >

                            <input
                                type="hidden"
                                name="role"
                                value="<?php
                                    echo htmlspecialchars(
                                        $teacher['role'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                ?>"
                            >

                            <button
                                type="submit"
                                class="ui tiny blue basic button"
                            >
                                다시 등록
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

    <div style="margin-top:1rem;">

        <h3
            class="ui dividing header"
            style="margin-top:2rem;"
        >
            교사 추가
        </h3>


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


        <form
            method="post"
            action="course_teachers.php?course_id=<?php echo intval($course_id); ?>"
            class="ui form"
        >

            <?php include("./csrf.php"); ?>

            <div class="fields">

                <div class="eight wide field">

                    <label>
                        사용자 아이디
                    </label>

                    <input
                        type="text"
                        name="user_id"
                        maxlength="48"
                        required
                    >

                </div>


                <div class="four wide field">

                    <label>
                        역할
                    </label>

                    <select
                        name="role"
                        class="ui dropdown"
                        required
                    >
                        <option value="teacher">
                            담당교사
                        </option>

                        <option value="assistant">
                            보조교사
                        </option>
                    </select>

                </div>


                <div class="four wide field">

                    <label>&nbsp;</label>

                    <button
                        type="submit"
                        class="ui teal button"
                    >
                        <i class="plus icon"></i>
                        교사 추가
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>