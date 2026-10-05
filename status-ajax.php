<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

////////////////////////////Common head
$cache_time = 2;
$OJ_CACHE_SHARE = false;

require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
$view_title = "$MSG_STATUS";

require_once("./include/const.inc.php");
require_once("./include/course_functions.inc.php");

$solution_id = 0;
// check the top arg

if (isset($_GET['solution_id'])) {
  $solution_id = intval($_GET['solution_id']);
}

$sql = "select * from solution where solution_id=? LIMIT 1";
$result = pdo_query($sql,$solution_id);

if (count($result)>0) {
	$row = $result[0];

        $current_user =
                isset($_SESSION[$OJ_NAME.'_user_id'])
                        ? trim((string)$_SESSION[$OJ_NAME.'_user_id'])
                        : '';

        $contest_id = intval($row['contest_id']);
        $solution_problem_id = intval($row['problem_id']);

        $is_solution_owner = (
                $current_user !== '' &&
                (string)$row['user_id'] === $current_user
        );

        $is_status_privileged = (
                isset($_SESSION[$OJ_NAME.'_administrator']) ||
                isset($_SESSION[$OJ_NAME.'_source_browser']) ||
                (
                        $contest_id > 0 &&
                        isset($_SESSION[$OJ_NAME.'_m'.$contest_id])
                ) ||
                (
                        $contest_id > 0 &&
                        course_can_view_contest_process(
                                $contest_id
                        )
                )
        );

        $is_course_performance_student = (
                $current_user !== '' &&
                !$is_status_privileged &&
                course_should_restrict_student_history(
                        $current_user
                )
        );

        // 수행모드 학생은 자신의 제출정보만 직접 조회할 수 있다.
        if (
                $is_course_performance_student &&
                !$is_solution_owner
        ) {
                http_response_code(403);
                echo "수행모드에서는 다른 사용자의 제출정보를 볼 수 없습니다.";
                exit(0);
        }

        // 수행모드 학생은 본인의 제출이라도
        // 수행모드 시작 전에 생성된 기존 제출은 직접 조회할 수 없다.
        //
        // custom test(problem_id=0)는 현재 시험 실행 결과 확인에
        // 사용되므로 이 제한 대상에서 제외한다.
        if (
                $is_course_performance_student &&
                $is_solution_owner &&
                $solution_problem_id > 0 &&
                isset($row['in_date']) &&
                course_should_restrict_student_record(
                        $current_user,
                        $row['in_date']
                )
        ) {
                http_response_code(403);
                echo "수행모드에서는 기존 제출정보를 볼 수 없습니다.";
                exit(0);
        }

        if (isset($_GET['tr'])) {

                // 상세정보는 제출자 본인 또는 관리 권한 사용자만 볼 수 있다.
                if (
                        $current_user === '' ||
                        (
                                !$is_solution_owner &&
                                !$is_status_privileged
                        )
                ) {
                        http_response_code(403);
                        echo "상세정보를 볼 권한이 없습니다.";
                        exit(0);
                }

                $res = intval($row['result']);

		if ($res==11) {
			$sql = "SELECT `error` FROM `compileinfo` WHERE `solution_id`=?";
		}
		else {
			$sql = "SELECT `error` FROM `runtimeinfo` WHERE `solution_id`=?";
		}

		$result = pdo_query($sql,$solution_id);
		$row = $result[0];

                if ($row) {
                        echo htmlentities(
                                str_replace(
                                        "\n\r",
                                        "\n",
                                        $row['error']
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                        );

                        // custominput은 본인의 시험 실행에서만 삭제한다.
                        if (
                                $solution_problem_id === 0 &&
                                $is_solution_owner
                        ) {
                                $sql =
                                        "delete from custominput ".
                                        "where solution_id=?";

                                pdo_query(
                                        $sql,
                                        $solution_id
                                );
                        }
                }
		//echo $sql.$res;
	}
	else {
		if (isset($_GET['q']) && "user_id"==$_GET['q']) {
			echo $row['user_id']."[".$row['nick']."]";      // ajax onmouseover show who was copycated or shared the code to him
		}
		else {
			$contest_id = $row['contest_id'];

			if ($contest_id>0) {
				$result = pdo_query("select title from contest where contest_id=?",$contest_id);
				$contest_title = $result[0][0];

				if (stripos($contest_title,$OJ_NOIP_KEYWORD)!==false) {
					echo "$OJ_NOIP_KEYWORD";
					exit(0);
				}
			}

			if (isset($_GET['t']) && "json"==$_GET['t']) {

                                // solution 전체 정보는 제출자 본인 또는
                                // 관리 권한이 있는 사용자만 조회할 수 있다.
                                if (
                                        !$is_solution_owner &&
                                        !$is_status_privileged
                                ) {
                                        http_response_code(403);
                                        echo "제출정보를 볼 권한이 없습니다.";
                                        exit(0);
                                }

				echo json_encode($row);
			}
			else {
				if(isset($_SESSION[$OJ_NAME.'_'.'administrator']))
					echo $row['result'].",".$row['memory']." KB,".$row['time']." ms,".$row['judger'].",".($row['pass_rate']*100);
				else
					echo $row['result'].",".$row['memory']." KB,".$row['time']." ms,"."none,".($row['pass_rate']*100);
			}
		}
	}
}
else {
	echo $solution_id;
	echo "0, 0, 0,unknown,0";
}

?>
