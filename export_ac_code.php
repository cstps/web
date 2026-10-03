<?php 
require_once("./include/db_info.inc.php");
require_once("./include/course_functions.inc.php");
if (!isset($_SESSION[$OJ_NAME.'_'.'user_id'])){
	$view_errors= "<a href=./loginpage.php>$MSG_Login</a>";
	require("template/".$OJ_TEMPLATE."/error.php");
	exit(0);
}
// ============================================================
// Course 수행모드 학생의 기존 정답 코드 내보내기 차단
// ============================================================

$current_user =
    isset($_SESSION[$OJ_NAME.'_'.'user_id'])
        ? trim(
            (string)$_SESSION[
                $OJ_NAME.'_'.'user_id'
            ]
        )
        : '';

if (
    $current_user !== '' &&
    course_should_restrict_student_history(
        $current_user
    ) &&
    !isset($_SESSION[$OJ_NAME.'_'.'source_browser'])
) {

    $view_errors =
        "<h2>수행모드에서는 기존 제출 코드를 내보낼 수 없습니다.</h2>";

    require(
        "template/".
        $OJ_TEMPLATE.
        "/error.php"
    );

    exit(0);
}


// ============================================================
// 모든 권한 검사를 통과한 경우에만 다운로드 시작
// ============================================================

header(
    "Content-Type: text/plain; charset=UTF-8"
);

header(
    "Content-Disposition: attachment; filename=\"ac-".
    $current_user.
    ".txt\""
);


$sql="select distinct source,problem_id from source_code inner join \n 
		(select solution_id,problem_id from solution where user_id=? and result=4) S \n
		on source_code.solution_id=S.solution_id  \n
			where S.problem_id not in (select problem_id from contest_problem where contest_id \n
							in (select contest_id from contest where start_time < now() and end_time >now()) \n
						) order by problem_id";
//echo "$sql";
echo $_SESSION[$OJ_NAME.'_'.'user_id']."\r\n";

$result=pdo_query($sql,$_SESSION[$OJ_NAME.'_'.'user_id']);
 foreach($result as $row){
	echo "Problem".$row['problem_id'].":\r\n";
	echo preg_replace("(\n)","\r\n",$row['source']);
	echo "\r\n------------------------------------------------------\r\n";
}

?>
