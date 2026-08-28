<div class="admin-topbar">

    <div class="admin-topbar-inner">

        <div class="admin-topbar-left">

            <a
                class="admin-brand"
                href="<?php echo $OJ_HOME; ?>"
            >
                <span class="admin-brand-main">
                    1024.kr
                </span>

                <span class="admin-brand-sub">
                    관리자
                </span>
            </a>

        </div>


        <div class="admin-topbar-right">

            <?php
            if (
                isset(
                    $_SESSION[
                        $OJ_NAME.'_user_id'
                    ]
                )
            ) {
            ?>

                <span class="admin-user">

                    <?php
                    echo htmlspecialchars(
                        $_SESSION[
                            $OJ_NAME.'_user_id'
                        ],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </span>

            <?php
            }
            ?>


            <a
                class="admin-site-link"
                href="../"
            >
                사이트로 돌아가기
            </a>

        </div>

    </div>

</div>