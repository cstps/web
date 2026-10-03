<?php
$show_title = $view_title;
include(__DIR__ . "/../header.php");
?>
<link
    rel="stylesheet"
    href="/template/syzoj/tools/tools-common.css?v=20261003-2">


<div class="ui container tools-detail-page" style="margin-top: 2em; margin-bottom: 3em;">
    <h2 class="ui dividing header">
        <i class="linkify teal icon"></i>
        <div class="content">
            단축 URL 생성기
            <div class="sub header">긴 과제/설문지 주소를 입력하기 쉽고 짧은 주소로 줄여줍니다.</div>
        </div>
    </h2>

    <!-- URL 생성 입력 폼 카드 -->
    <div class="ui segment" style="padding: 2em;">
        <?php if (isset($error_msg)) { ?>
            <div class="ui negative message">
                <i class="warning circle icon"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php } ?>

        <?php if (isset($success_msg)) { ?>
            <div class="ui positive message">
                <i class="check circle icon"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php } ?>

        <form class="ui form" method="post" action="shorturl.php">
            <input type="hidden" name="action" value="create">

            <div class="field">
                <label>원본 URL 주소 입력</label>
                <input type="text" name="url" placeholder="한글.한글 또는 https://docs.google.com/..." required>
            </div>

            <!-- 비로그인 사용자인 경우 일일 보안키 입력란 노출 -->
            <?php if (!isset($_SESSION[$OJ_NAME . '_user_id'])) { ?>
                <div class="field" style="margin-top: 1em;">
                    <label>
                        <i class="key orange icon"></i> 일일 보안키 입력
                        <span style="font-weight: normal; color: #666; font-size: 0.9em;">(비회원 이용 시 교사가 안내한 오늘의 암호를 입력하세요)</span>
                    </label>
                    <input type="text" name="passkey" placeholder="오늘의 보안키 입력" style="max-width: 250px;" required>
                </div>
            <?php } ?>

            <button class="ui teal button" type="submit" style="margin-top: 1em;">
                <i class="magic icon"></i> 단축 주소 생성
            </button>
        </form>

        <!-- [1회성 결과 표시] 생성 성공 시 이 영역만 노출 -->
        <?php if (!empty($generated_url)) { ?>
            <div class="ui positive message" style="margin-top: 2em;">
                <div class="header">
                    <i class="check circle icon"></i> 단축 URL이 성공적으로 생성되었습니다!
                </div>
                <p style="margin-top: 0.5em; color: #555;">
                    <?php if ($is_guest_generated) { ?>
                        * 비회원 생성 결과입니다. 생성된 주소를 지금 복사하여 저장해 두세요.
                    <?php } ?>
                </p>
                <div class="ui action input fluid" style="margin-top: 1em;">
                    <input type="text" id="shortUrlInput" value="<?php echo htmlspecialchars($generated_url); ?>" readonly>
                    <button class="ui blue button" onclick="copyToClipboard('<?php echo htmlspecialchars($generated_url); ?>')">
                        <i class="copy icon"></i> 복사
                    </button>
                    <a href="<?php echo htmlspecialchars($generated_url); ?>" target="_blank" class="ui green button">
                        <i class="external alternate icon"></i> 이동
                    </a>
                </div>
            </div>
        <?php } ?>
    </div>

    <!-- [목록 및 삭제 영역]: 오직 로그인된 회원에게만 출력됨 (비로그인은 완전히 숨김) -->
    <?php if (isset($_SESSION[$OJ_NAME . '_user_id'])) { ?>
        <div class="ui segment" style="margin-top: 2em;">
            <h3 class="ui header">
                <i class="list alternate outline icon"></i>
                <div class="content">
                    <?php echo isset($_SESSION[$OJ_NAME . '_administrator']) ? "전체 단축 URL 목록 (최근 50개)" : "내가 생성한 단축 URL 목록"; ?>
                </div>
            </h3>

            <?php if (empty($my_short_urls)) { ?>
                <div class="ui placeholder segment text center" style="padding: 2em;">
                    <p style="color: #777;">아직 생성된 단축 URL이 없습니다.</p>
                </div>
            <?php } else {
                $http_host = $_SERVER['HTTP_HOST'];
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            ?>
                <table class="ui celled table">
                    <thead>
                        <tr>
                            <th style="width: 110px;">단축 코드</th>
                            <th>원본 URL</th>
                            <th style="width: 90px; text-align: center;">클릭 수</th>
                            <th style="width: 150px;">생성 일시</th>
                            <th style="width: 160px; text-align: center;">동작</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_short_urls as $row) {
                            $s_url = "{$scheme}://{$http_host}/shorturl.php?code=" . $row['short_code'];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['short_code']); ?></strong>
                                </td>
                                <td style="max-width: 280px; word-break: break-all;">
                                    <a href="<?php echo htmlspecialchars($row['original_url']); ?>" target="_blank" style="color: #555;">
                                        <?php echo htmlspecialchars($row['original_url']); ?>
                                    </a>
                                </td>
                                <td style="text-align: center;">
                                    <span class="ui label mini teal"><?php echo number_format($row['click_count']); ?>회</span>
                                </td>
                                <td>
                                    <small><?php echo $row['created_at']; ?></small>
                                </td>
                                <td style="text-align: center;">
                                    <button class="ui compact mini blue button" onclick="copyToClipboard('<?php echo htmlspecialchars($s_url); ?>')">
                                        <i class="copy icon"></i> 복사
                                    </button>

                                    <form method="post" action="shorturl.php" onsubmit="return confirm('이 단축 URL을 삭제하시겠습니까?');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="ui compact mini red button">
                                            <i class="trash icon"></i> 삭제
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php } ?>

    <div style="margin-top: 1.5em;">
        <a href="/tools/" class="ui button tools-detail-back"><i class="arrow left icon"></i> 유틸리티 목록으로 돌아가기</a>
    </div>
</div>

<script>
    function copyToClipboard(text) {
        var dummy = document.createElement("textarea");
        document.body.appendChild(dummy);
        dummy.value = text;
        dummy.select();
        document.execCommand("copy");
        document.body.removeChild(dummy);
        alert("단축 URL이 복사되었습니다:\n" + text);
    }
</script>

<?php include(__DIR__ . "/../footer.php"); ?>