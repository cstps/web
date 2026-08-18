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
            새 수업 만들기
        </h1>

        <div class="course-page-description">
            새로운 수업 과정을 생성합니다.
        </div>

    </div>


    <div class="ui segment">

        <form
            class="ui form"
            method="post"
            action="course_create.php"
        >

            <?php include("./csrf.php"); ?>


            <!-- 수업명 -->

            <div class="required field">

                <label>
                    수업명
                </label>

                <input
                    type="text"
                    name="course_name"
                    maxlength="100"
                    placeholder="예: 프로그래밍 기초"
                    required
                >

            </div>


            <!-- 학교 -->

            <div class="field">

                <label>
                    학교
                </label>

                <input
                    type="text"
                    name="school"
                    maxlength="100"
                    placeholder="예: 경남온라인학교"
                >

            </div>


            <div class="two fields">


                <!-- 학년도 -->

                <div class="required field">

                    <label>
                        학년도
                    </label>

                    <input
                        type="number"
                        name="school_year"
                        min="2000"
                        max="2100"
                        value="<?php
                            echo intval($view_default_year);
                        ?>"
                        required
                    >

                </div>


                <!-- 학기 -->

                <div class="required field">

                    <label>
                        학기
                    </label>

                    <select
                        name="semester"
                        class="ui dropdown"
                        required
                    >

                        <option value="1">
                            1학기
                        </option>

                        <option value="2">
                            2학기
                        </option>

                    </select>

                </div>

            </div>


            <!-- 설명 -->

            <div class="field">

                <label>
                    수업 설명
                </label>

                <textarea
                    name="description"
                    rows="5"
                    maxlength="1000"
                    placeholder="수업에 대한 간단한 설명을 입력하세요."
                ></textarea>

            </div>


            <div style="margin-top:1.5rem;">

                <button
                    type="submit"
                    class="ui teal button"
                >
                    <i class="plus icon"></i>
                    수업 생성
                </button>


                <a
                    href="course_list.php"
                    class="ui basic button"
                >
                    취소
                </a>

            </div>

        </form>

    </div>

</div>


<?php
include("template/$OJ_TEMPLATE/footer.php");
?>