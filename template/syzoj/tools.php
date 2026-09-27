<?php
$show_title = $view_title;
include("header.php");
?>

<div class="ui container" style="margin-top: 2em; margin-bottom: 3em;">
    <h2 class="ui dividing header">
        <i class="wrench teal icon"></i>
        <div class="content">
            <?php echo isset($MSG_ULTILIST) ? $MSG_ULTILIST : "유틸리티 도구 모음"; ?>
            <div class="sub header">수업 및 프로그래밍 학습 지원을 위한 편의 도구 모음입니다.</div>
        </div>
    </h2>

    <!-- Semantic UI Cards 타일형 레이아웃 -->
    <div class="ui four stackable cards" style="margin-top: 1.5em;">

        <!-- 1. 한자리 수 합 계산기 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>pc.php';">
            <div class="content">
                <div class="header">
                    <i class="calculator teal icon"></i>
                    <?php echo isset($MSG_POINTCHECK) ? $MSG_POINTCHECK : "한자리 합 계산기"; ?>
                </div>
                <div class="meta">점수 및 숫자 계산</div>
                <div class="description">
                    입력된 숫자들의 합을 실시간으로 산출하거나 특수 연산을 수행합니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui teal label">도구</span>
            </div>
        </div>

        <!-- 2. 글자 수 세기 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>charcount.php';">
            <div class="content">
                <div class="header">
                    <i class="font blue icon"></i>
                    <?php echo isset($MSG_CHARCOUNT) ? $MSG_CHARCOUNT : "글자 수 세기"; ?>
                </div>
                <div class="meta">텍스트 분석</div>
                <div class="description">
                    공백 포함/제외 글자 수, 바이트(Byte) 수, 단어 수를 실시간으로 측정합니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui blue label">도구</span>
            </div>
        </div>

        <!-- 3. 자리 배치 도구 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>seat_assign.php';">
            <div class="content">
                <div class="header">
                    <i class="users orange icon"></i>
                    <?php echo isset($MSG_SEATASSIGN) ? $MSG_SEATASSIGN : "자리 배치"; ?>
                </div>
                <div class="meta">수업/시험 도구</div>
                <div class="description">
                    학생들의 실습실 또는 시험 좌석을 공정하게 무작위 배치합니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui orange label">추천</span>
            </div>
        </div>

        <!-- 4. 사다리 타기 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>sadari.php';">
            <div class="content">
                <div class="header">
                    <i class="random green icon"></i>
                    <?php echo isset($MSG_SADARI) ? $MSG_SADARI : "사다리 타기"; ?>
                </div>
                <div class="meta">랜덤 추첨</div>
                <div class="description">
                    발표자 지정, 순번 정하기, 팀 나누기 등에 활용하는 사다리 게임입니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui green label">게임</span>
            </div>
        </div>

        <!-- 5. 단축 URL 생성기 카드 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>shorturl.php';">
            <div class="content">
                <div class="header">
                    <i class="linkify teal icon"></i>
                    단축 URL 생성기
                </div>
                <div class="meta">주소 줄이기</div>
                <div class="description">
                    긴 과제/설문지 주소나 한글 웹 주소를 보기 쉽고 짧은 주소로 줄여줍니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui teal label">신규</span>
            </div>
        </div>

        <!-- 6. 수업용 대형 타이머 카드 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>timer.php';">
            <div class="content">
                <div class="header">
                    <i class="stopwatch teal icon"></i>
                    수업용 타이머
                </div>
                <div class="meta">시간 관리</div>
                <div class="description">
                    실습 과제 및 발표, 시험 시간을 화면에 크게 띄워 몰입감을 높여줍니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui teal label">신규</span>
            </div>
        </div>

        <!-- 7. 랜덤 학생 추첨기 카드 -->
        <div class="ui card link" onclick="location.href='<?php echo $path_fix ?>/picker.php';">
            <div class="content">
                <div class="header">
                    <i class="user random orange icon"></i>
                    랜덤 학생 추첨기
                </div>
                <div class="meta">발표 및 질문</div>
                <div class="description">
                    학생 번호나 이름을 무작위로 애니메이션 효과와 함께 추첨합니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui orange label">신규</span>
            </div>
        </div>

        <!-- 휴먼벤치마크 카드 -->
        <div class="ui card link" onclick="location.href='<?php echo isset($path_fix) ? $path_fix : ''; ?>humanbenchmark.php';">
            <div class="content">
                <div class="header">
                    <i class="gamepad teal icon"></i>
                    휴먼벤치마크
                </div>
                <div class="meta">인지능력 측정</div>
                <div class="description">
                    반응속도(ms), 순서 기억하기, 숫자 암기 테스트로 뇌의 인지 성능을 측정합니다.
                </div>
            </div>
            <div class="extra content">
                <span class="right floated">바로가기 <i class="right chevron icon"></i></span>
                <span class="ui teal label">신규</span>
            </div>
        </div>

        <!-- 6. 준비 중 카드 -->
        <div class="ui card disabled" style="opacity: 0.65; background-color: #f9fafb;">
            <div class="content">
                <div class="header">
                    <i class="edit grey icon"></i>
                    신규 도구 준비 중
                </div>
                <div class="meta">개발 진행 중</div>
                <div class="description">
                    수업에 도움이 되는 신규 기능이 지속적으로 추가될 예정입니다.
                </div>
            </div>
            <div class="extra content">
                <span class="ui grey label">Coming Soon</span>
            </div>
        </div>

    </div>
</div>

<style>
    .ui.cards>.card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .ui.cards>.card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.12) !important;
    }
</style>

<?php include("footer.php"); ?>