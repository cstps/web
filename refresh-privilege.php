<?php

require_once("./include/db_info.inc.php");
require_once("./include/setlang.php");
require_once("./include/permission_functions.inc.php");

$view_title = "권한 정보 새로고침";


// ============================================================
// 1. 로그인 확인
// ============================================================

$user_id =
    isset($_SESSION[$OJ_NAME.'_user_id'])
        ? trim(
            (string)$_SESSION[$OJ_NAME.'_user_id']
        )
        : "";


if ($user_id === "") {

    $view_errors =
        "<h2>로그인이 필요합니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 2. 권한 세션 새로고침
//
// 처리 순서:
// 1) 기존 전역 권한 세션 제거
// 2) 기존 c{cid}, m{cid}, s{pid} 세션 제거
// 3) privilege.defunct='N'인 권한 다시 로딩
// 4) VIP 파생 대회 참가권한 다시 생성
// ============================================================

$refresh_success =
    oj_refresh_privilege_sessions(
        $user_id
    );


// ============================================================
// 3. 새로고침된 활성 권한 목록
//
// 화면 확인용이며 실제 권한 판정에는 사용하지 않는다.
// ============================================================

$active_privileges =
    array();


if ($refresh_success) {

    $privilege_rows =
        pdo_query(
            "SELECT
                rightstr,
                valuestr
             FROM privilege
             WHERE user_id=?
               AND defunct='N'
             ORDER BY rightstr ASC",
            $user_id
        );


    if ($privilege_rows) {

        foreach (
            $privilege_rows
            as
            $row
        ) {

            $rightstr =
                isset($row['rightstr'])
                    ? trim(
                        (string)$row['rightstr']
                    )
                    : "";

            $valuestr =
                isset($row['valuestr'])
                    ? trim(
                        (string)$row['valuestr']
                    )
                    : "true";


            if ($rightstr === "") {
                continue;
            }


            $active_privileges[] =
                array(
                    'rightstr' => $rightstr,
                    'valuestr' => $valuestr
                );
        }
    }
}


// ============================================================
// 4. 화면 출력
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/header.php"
);

?>


<div
    class="ui main container"
    style="
        max-width:900px;
        margin-top:30px;
        margin-bottom:50px;
    ">


    <!-- ======================================================
         페이지 제목
         ====================================================== -->

    <div class="ui clearing segment">

        <h2 class="ui left floated header">

            <i class="sync alternate icon"></i>

            <div class="content">

                권한 정보 새로고침

                <div class="sub header">
                    현재 로그인 계정의 권한 정보를 다시 불러옵니다.
                </div>

            </div>

        </h2>

    </div>


    <?php if ($refresh_success) { ?>


        <!-- ==================================================
             성공 메시지
             ================================================== -->

        <div class="ui positive icon message">

            <i class="check circle icon"></i>

            <div class="content">

                <div class="header">
                    권한 정보를 새로고침했습니다.
                </div>

                <p>
                    삭제되거나 비활성화된 권한은 제거하고,
                    현재 활성화된 권한만 다시 적용했습니다.
                </p>

            </div>

        </div>


        <!-- ==================================================
             사용자 정보
             ================================================== -->

        <div class="ui segment">

            <div class="ui relaxed divided list">

                <div class="item">

                    <i class="user large middle aligned icon"></i>

                    <div class="content">

                        <div class="header">
                            로그인 사용자
                        </div>

                        <div class="description">

                            <?php
                            echo htmlentities(
                                $user_id,
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </div>

                    </div>

                </div>


                <div class="item">

                    <i class="shield alternate large middle aligned icon"></i>

                    <div class="content">

                        <div class="header">
                            활성 권한
                        </div>

                        <div class="description">

                            <?php
                            echo count(
                                $active_privileges
                            );
                            ?>개

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ==================================================
             활성 권한 목록
             ================================================== -->

        <div class="ui segment">

            <h3 class="ui dividing header">

                <i class="key icon"></i>

                <div class="content">
                    현재 활성 권한
                </div>

            </h3>


            <?php if (
                count($active_privileges) > 0
            ) { ?>

                <div
                    style="
                        display:flex;
                        flex-wrap:wrap;
                        gap:8px;
                    ">

                    <?php foreach (
                        $active_privileges
                        as
                        $privilege
                    ) { ?>

                        <div class="ui basic violet label">

                            <?php
                            echo htmlentities(
                                $privilege['rightstr'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>


                            <?php if (
                                $privilege['valuestr'] !== "" &&
                                $privilege['valuestr'] !== "true"
                            ) { ?>

                                <span
                                    style="
                                        margin-left:4px;
                                        color:#666;
                                    ">

                                    : <?php
                                        echo htmlentities(
                                            $privilege['valuestr'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>

                                </span>

                            <?php } ?>

                        </div>

                    <?php } ?>

                </div>

            <?php } else { ?>

                <div class="ui info message">

                    <div class="header">
                        별도로 부여된 권한이 없습니다.
                    </div>

                    <p>
                        일반 사용자 권한으로 로그인되어 있습니다.
                    </p>

                </div>

            <?php } ?>

        </div>


    <?php } else { ?>


        <!-- ==================================================
             실패 메시지
             ================================================== -->

        <div class="ui negative icon message">

            <i class="exclamation triangle icon"></i>

            <div class="content">

                <div class="header">
                    권한 정보를 새로고침하지 못했습니다.
                </div>

                <p>
                    잠시 후 다시 시도해 주세요.
                </p>

            </div>

        </div>


    <?php } ?>


    <!-- ======================================================
         이동 버튼
         ====================================================== -->

    <div
        style="
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:20px;
        ">

        <a
            href="javascript:history.back();"
            class="ui button">

            <i class="arrow left icon"></i>
            이전 화면

        </a>


        <a
            href="index.php"
            class="ui primary button">

            <i class="home icon"></i>
            홈으로

        </a>


        <?php if (
            isset(
                $_SESSION[
                    $OJ_NAME.'_administrator'
                ]
            )
        ) { ?>

            <a
                href="admin/privilege_list.php"
                class="ui violet button">

                <i class="users cog icon"></i>
                권한 관리

            </a>

        <?php } ?>

    </div>


</div>


<?php

require(
    "template/".
    $OJ_TEMPLATE.
    "/footer.php"
);

?>