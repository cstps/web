<?php
$show_title = $view_title;
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

        <a
            class="tools-card"
            href="/tools/pc.php">

            <div class="tools-card-icon">
                <i class="calculator icon"></i>
            </div>

            <div class="tools-card-title">
                <?php
                echo isset($MSG_POINTCHECK)
                    ? $MSG_POINTCHECK
                    : "한자리 합 계산기";
                ?>
            </div>

            <div class="tools-card-meta">
                점수 및 숫자 계산
            </div>

            <div class="tools-card-description">
                입력한 숫자의 합을 실시간으로 계산하고
                필요한 숫자 연산을 간편하게 처리합니다.
            </div>

            <div class="tools-card-footer">
                <span>계산 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/charcount.php">

            <div class="tools-card-icon">
                <i class="font icon"></i>
            </div>

            <div class="tools-card-title">
                <?php
                echo isset($MSG_CHARCOUNT)
                    ? $MSG_CHARCOUNT
                    : "글자 수 세기";
                ?>
            </div>

            <div class="tools-card-meta">
                텍스트 분석
            </div>

            <div class="tools-card-description">
                공백 포함·제외 글자 수와 바이트 수,
                단어 수를 실시간으로 확인합니다.
            </div>

            <div class="tools-card-footer">
                <span>텍스트 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/seat_assign.php">

            <div class="tools-card-icon">
                <i class="users icon"></i>
            </div>

            <div class="tools-card-title">
                <?php
                echo isset($MSG_SEATASSIGN)
                    ? $MSG_SEATASSIGN
                    : "자리 배치";
                ?>
            </div>

            <div class="tools-card-meta">
                수업 및 시험 운영
            </div>

            <div class="tools-card-description">
                학생들의 실습실 또는 시험 좌석을
                무작위로 배치합니다.
            </div>

            <div class="tools-card-footer">
                <span>수업 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/sadari.php">

            <div class="tools-card-icon">
                <i class="random icon"></i>
            </div>

            <div class="tools-card-title">
                <?php
                echo isset($MSG_SADARI)
                    ? $MSG_SADARI
                    : "사다리 타기";
                ?>
            </div>

            <div class="tools-card-meta">
                무작위 선택
            </div>

            <div class="tools-card-description">
                발표 순서, 역할 배정, 팀 선택 등에
                활용할 수 있는 사다리 도구입니다.
            </div>

            <div class="tools-card-footer">
                <span>추첨 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/shorturl.php">

            <div class="tools-card-icon">
                <i class="linkify icon"></i>
            </div>

            <div class="tools-card-title">
                단축 URL 생성기
            </div>

            <div class="tools-card-meta">
                주소 정리
            </div>

            <div class="tools-card-description">
                긴 웹 주소를 짧고 공유하기 쉬운
                주소로 변환합니다.
            </div>

            <div class="tools-card-footer">
                <span>웹 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/timer.php">

            <div class="tools-card-icon">
                <i class="stopwatch icon"></i>
            </div>

            <div class="tools-card-title">
                수업용 타이머
            </div>

            <div class="tools-card-meta">
                시간 관리
            </div>

            <div class="tools-card-description">
                실습, 발표, 활동 시간을 큰 화면으로
                확인하고 관리할 수 있습니다.
            </div>

            <div class="tools-card-footer">
                <span>수업 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/picker.php">

            <div class="tools-card-icon">
                <i class="user icon"></i>
            </div>

            <div class="tools-card-title">
                랜덤 학생 추첨기
            </div>

            <div class="tools-card-meta">
                발표 및 질문
            </div>

            <div class="tools-card-description">
                학생 번호나 이름을 입력해
                무작위로 한 명을 선택합니다.
            </div>

            <div class="tools-card-footer">
                <span>추첨 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>


        <a
            class="tools-card"
            href="/tools/humanbenchmark.php">

            <div class="tools-card-icon">
                <i class="gamepad icon"></i>
            </div>

            <div class="tools-card-title">
                휴먼벤치마크
            </div>

            <div class="tools-card-meta">
                반응 및 기억 테스트
            </div>

            <div class="tools-card-description">
                반응속도와 순서 기억, 숫자 기억 등
                간단한 인지 테스트를 제공합니다.
            </div>

            <div class="tools-card-footer">
                <span>학습 도구</span>
                <span>
                    열기
                    <i class="right chevron icon"></i>
                </span>
            </div>
        </a>

    </div>
</div>

<?php include(__DIR__ . "/../footer.php"); ?>