<?php
$show_title = $view_title;

$tools_registry = require(
    __DIR__ . "/../../../include/tools_registry.inc.php"
);

include(__DIR__ . "/../header.php");
?>

<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools.css?v=20261003-2">

<div class="ui container tools-page">
    <section class="tools-hero">
        <h1 class="tools-hero-title">
            <i class="wrench icon"></i>
            <?php
            echo isset($MSG_ULTILIST)
                ? $MSG_ULTILIST
                : "유틸리티 도구 모음";
            ?>
        </h1>

        <p class="tools-hero-description">
            수업 운영과 프로그래밍 학습에 필요한
            편의 도구를 한곳에서 사용할 수 있습니다.
        </p>
    </section>

    <div class="tools-section-title">
        사용할 도구를 선택하세요
    </div>

    <div class="tools-grid">
        <?php foreach ($tools_registry as $tool): ?>
            <?php
            $tool_title = $tool['title'];

            if (
                isset($tool['message_var']) &&
                isset(${$tool['message_var']})
            ) {
                $tool_title = ${$tool['message_var']};
            }
            ?>

            <a
                class="tools-card"
                href="<?php
                echo htmlspecialchars(
                    $tool['url'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>">

                <div class="tools-card-icon">
                    <i class="<?php
                    echo htmlspecialchars(
                        $tool['icon'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?> icon"></i>
                </div>

                <div class="tools-card-title">
                    <?php
                    echo htmlspecialchars(
                        $tool_title,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

                <div class="tools-card-meta">
                    <?php
                    echo htmlspecialchars(
                        $tool['meta'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

                <div class="tools-card-description">
                    <?php
                    echo htmlspecialchars(
                        $tool['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </div>

                <div class="tools-card-footer">
                    <span>
                        <?php
                        echo htmlspecialchars(
                            $tool['category'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </span>

                    <span>
                        열기
                        <i class="right chevron icon"></i>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include(__DIR__ . "/../footer.php"); ?>