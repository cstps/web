<?php
require_once __DIR__ . '/admin-init.php';
require_once __DIR__ . '/../include/problem.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('잘못된 요청 방식입니다.');
}

if (!oj_can_create_admin_problems()) {
    http_response_code(403);
    exit('문제를 생성할 권한이 없습니다.');
}

require_once __DIR__ . '/../include/check_post_key.php';

// contest_id
$title = $_POST['title'];
$title = str_replace(",", "&#44;", $title);
$time_limit = $_POST['time_limit'];
$memory_limit = $_POST['memory_limit'];

$description = $_POST['description'];
$description = str_replace("<p>", "", $description);
$description = str_replace("</p>", "<br />", $description);
$description = str_replace(",", "&#44;", $description);

$input = $_POST['input'];
$input = str_replace("<p>", "", $input);
$input = str_replace("</p>", "<br />", $input);
$input = str_replace(",", "&#44;", $input);

$output = $_POST['output'];
$output = str_replace("<p>", "", $output);
$output = str_replace("</p>", "<br />", $output);
$output = str_replace(",", "&#44;", $output);

$sample_input = $_POST['sample_input'];
$sample_output = $_POST['sample_output'];
$test_input = $_POST['test_input'];
$test_output = $_POST['test_output'];
/* don't do this , we will left them empty for not generating invalid test data files
if ($sample_input=="") $sample_input="\n";
if ($sample_output=="") $sample_output="\n";
if ($test_input=="") $test_input="\n";
if ($test_output=="") $test_output="\n";
*/
$hint = $_POST['hint'];
$hint = str_replace("<p>", "", $hint);
$hint = str_replace("</p>", "<br />", $hint);
$hint = str_replace(",", "&#44;", $hint);

$source = $_POST['source'];
$current_user_id =
    (string)$_SESSION[$OJ_NAME . '_user_id'];

$creator_label =
    isset($_POST['creator'])
    ? trim((string)$_POST['creator'])
    : '';

if ($creator_label === '') {
    $creator_label = $current_user_id;
}

$creator_length = function_exists('mb_strlen')
    ? mb_strlen($creator_label, 'UTF-8')
    : strlen($creator_label);

if ($creator_length > 200) {
    http_response_code(400);
    exit('표시용 출제자 이름은 200자 이하여야 합니다.');
}

$spj = $_POST['spj'];


// 앞뒤 코드, 금지어, 포인트
// 코드는 내용과 빈 줄을 보존하고 줄바꿈 방식만 LF로 통일한다.
$front_code =
    isset($_POST['front_code'])
    ? $_POST['front_code']
    : '';

$rear_code =
    isset($_POST['rear_code'])
    ? $_POST['rear_code']
    : '';

$front_code =
    str_replace(
        array("\r\n", "\r"),
        "\n",
        $front_code
    );

$rear_code =
    str_replace(
        array("\r\n", "\r"),
        "\n",
        $rear_code
    );

$ban_code =
    isset($_POST['ban_code'])
    ? $_POST['ban_code']
    : '';

$pro_point =
    isset($_POST['pro_point'])
    ? intval($_POST['pro_point'])
    : 1;


// ------------------------------------------------------------
// 문제의 다른 대회 재사용 허용 여부
//
// 1 = 허용
// 0 = 금지
//
// 기존 폼이나 오래된 요청과의 호환을 위해
// 값이 없으면 기본적으로 허용한다.
// ------------------------------------------------------------

$allow_reuse = 1;

if (isset($_POST['allow_reuse'])) {

    $allow_reuse_raw =
        (string)$_POST['allow_reuse'];

    if (
        !in_array(
            $allow_reuse_raw,
            array('0', '1'),
            true
        )
    ) {

        echo "Invalid allow_reuse value.";
        exit(1);
    }

    $allow_reuse =
        intval($allow_reuse_raw);
}



$title = RemoveXSS($title);
$description = RemoveXSS($description);
$input = RemoveXSS($input);
$output = RemoveXSS($output);
$hint = RemoveXSS($hint);

//$front_code = RemoveXSS($front_code);
//$rear_code = RemoveXSS($rear_code);
$ban_code = RemoveXSS($ban_code);

//echo "->".$OJ_DATA."<-";
$pid = addproblem(
    $title,
    $time_limit,
    $memory_limit,
    $description,
    $input,
    $output,
    $sample_input,
    $sample_output,
    $hint,
    $source,
    $spj,
    $OJ_DATA,
    $front_code,
    $rear_code,
    $ban_code,
    $pro_point
);

if (
    $pid === false ||
    intval($pid) <= 0
) {
    http_response_code(500);
    exit('문제를 저장하지 못했습니다.');
}

$pid =
    intval($pid);

// 표시용 출제자는 계정이나 문제 관리 권한과 별도로 저장한다.
$creator_result = pdo_query(
    "UPDATE problem
     SET creator = ?
     WHERE problem_id = ?",
    $creator_label,
    $pid
);

if ($creator_result === false) {
    http_response_code(500);
    exit('문제 출제자 표시 정보를 저장하지 못했습니다.');
}

// ------------------------------------------------------------
// 문제 재사용 정책 저장
// ------------------------------------------------------------

pdo_query(
    "UPDATE problem
     SET allow_reuse = ?
     WHERE problem_id = ?",
    $allow_reuse,
    $pid
);


$basedir = "$OJ_DATA/$pid";
mkdir($basedir);

if (strlen($sample_output) && !strlen($sample_input)) $sample_input = "0";
if (strlen($sample_input)) mkdata($pid, "sample.in", $sample_input, $OJ_DATA);
if (strlen($sample_output)) mkdata($pid, "sample.out", $sample_output, $OJ_DATA);
if (strlen($test_output) && !strlen($test_input)) $test_input = "0";
if (strlen($test_input)) mkdata($pid, "test.in", $test_input, $OJ_DATA);
if (strlen($test_output)) mkdata($pid, "test.out", $test_output, $OJ_DATA);

// 관리 권한은 표시용 출제자 이름과 무관하게 실제 등록자에게 부여한다.
$grant_result = pdo_query(
    "INSERT INTO privilege
        (user_id, rightstr, defunct)
     VALUES
        (?, ?, 'N')",
    $current_user_id,
    'p' . $pid
);

if ($grant_result === false) {
    http_response_code(500);
    exit('문제는 저장됐지만 관리 권한을 부여하지 못했습니다.');
}

$_SESSION[$OJ_NAME . '_p' . $pid] = true;

header(
    'Location: problem_testdata.php?id=' .
        rawurlencode((string)$pid)
);
exit;
/*  */
?>
