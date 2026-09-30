<?php
if (
    isset($_SERVER["REQUEST_METHOD"]) &&
    $_SERVER["REQUEST_METHOD"] === "POST"
) {
    require(__DIR__ . "/rejudge_update.php");
    exit;
}
?>
<?php
require_once(__DIR__ . "/admin-init.php");
require_once(__DIR__ . "/../include/const.inc.php");

if (!isset($_SESSION[$OJ_NAME . "_administrator"])) {
    http_response_code(403);
    exit("관리자만 재채점을 요청할 수 있습니다.");
}

$admin_page_title = "재채점";
$admin_active_menu = "rejudge";
$admin_page_head_file = __DIR__ . "/rejudge-head.php";

$rejudge_forms = array(
    array(
        "title" => "문제별 재채점",
        "description" => "해당 문제에 제출된 코드를 재채점합니다.",
        "field" => "rjpid",
        "label" => "문제 번호",
        "placeholder" => "예: 1001",
        "contest_problem" => false
    ),
    array(
        "title" => "제출별 재채점",
        "description" => "제출 번호 하나를 지정하여 재채점합니다.",
        "field" => "rjsid",
        "label" => "제출 번호",
        "placeholder" => "예: 153888",
        "contest_problem" => false
    ),
    array(
        "title" => "채점 상태별 재채점",
        "description" => "지정한 채점 상태에 해당하는 제출을 조회합니다. 실행 전 대상 건수를 확인해 주세요.",
        "field" => "result",
        "label" => "채점 상태값",
        "placeholder" => "0~14",
        "contest_problem" => false
    ),
    array(
        "title" => "대회 전체 재채점",
        "description" => "해당 대회의 전체 제출을 재채점합니다.",
        "field" => "rjcid",
        "label" => "대회 번호",
        "placeholder" => "예: 1003",
        "contest_problem" => false
    ),
    array(
        "title" => "대회 내 문제별 재채점",
        "description" => "대회 번호와 대회 내 문제 위치를 지정합니다.",
        "field" => "rjcid",
        "label" => "대회 번호",
        "placeholder" => "예: 1003",
        "contest_problem" => true
    )
);

require(__DIR__ . "/admin-layout-start.php");
?>

<div class="admin-page rejudge-page">
    <div class="admin-page-header">
        <h1 class="admin-page-title">재채점</h1>
        <div class="admin-page-description">
            재채점 범위를 선택하고 번호를 입력해 주세요.
            대상 확인 화면에서 내용을 확인한 뒤 실행합니다.
        </div>
    </div>

    <div class="rejudge-input-grid">
        <?php foreach ($rejudge_forms as $index => $form): ?>
            <?php
            $input_id = "rejudge-input-" . $index;
            $heading_id = "rejudge-heading-" . $index;
            $description_id = "rejudge-description-" . $index;
            ?>
            <section
                class="rejudge-input-card"
                aria-labelledby="<?php echo $heading_id; ?>">

                <h2 id="<?php echo $heading_id; ?>">
                    <?php echo htmlspecialchars(
                        $form["title"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </h2>

                <p id="<?php echo $description_id; ?>">
                    <?php echo htmlspecialchars(
                        $form["description"],
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </p>

                <form action="rejudge.php" method="post">
                    <input type="hidden" name="do" value="do">

                    <?php if ($index === 0): ?>
                        <?php
                        require_once(
                            __DIR__ . "/../include/set_post_key.php"
                        );
                        ?>
                    <?php else: ?>
                        <input
                            type="hidden"
                            name="postkey"
                            value="<?php echo htmlspecialchars(
                                (string)$_SESSION[$OJ_NAME . "_postkey"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>">
                    <?php endif; ?>

                    <div class="rejudge-field">
                        <label for="<?php echo $input_id; ?>">
                            <?php echo htmlspecialchars(
                                $form["label"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>
                        </label>

                        <input
                            type="number"
                            id="<?php echo $input_id; ?>"
                            name="<?php echo $form["field"]; ?>"
                            min="<?php echo $form["field"] === "result" ? "0" : "1"; ?>"
                            <?php if ($form["field"] === "result"): ?>
                                max="14"
                                value="3"
                            <?php endif; ?>
                            step="1"
                            inputmode="numeric"
                            placeholder="<?php echo htmlspecialchars(
                                $form["placeholder"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                            aria-describedby="<?php echo $description_id; ?>"
                            required>
                    </div>

                    <?php if ($form["field"] === "result"): ?>
                        <div class="rejudge-field-help">
                            3은 실행 중 상태입니다. 상태값은 0~14까지 입력할 수 있습니다.
                        </div>
                    <?php endif; ?>

                    <?php if ($form["contest_problem"]): ?>
                        <div class="rejudge-field">
                            <label for="rejudge-contest-position">
                                대회 내 문제 위치
                            </label>
                            <select
                                id="rejudge-contest-position"
                                name="pid"
                                required>
                                <?php foreach ($PID as $position => $label): ?>
                                    <option value="<?php echo htmlspecialchars(
                                        (string)$position,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>">
                                        <?php echo htmlspecialchars(
                                            (string)$label,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="rejudge-field-help">
                            문제 번호가 아닌 대회 내 위치를 선택합니다.
                        </div>
                    <?php endif; ?>

                    <button type="submit">재채점 대상 확인</button>
                </form>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?php require(__DIR__ . "/admin-layout-end.php"); ?>
