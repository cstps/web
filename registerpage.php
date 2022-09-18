<?php
////////////////////////////Common head
	$cache_time=10;
	$OJ_CACHE_SHARE=false;
	require_once('./include/cache_start.php');
    require_once('./include/db_info.inc.php');
	// 비공개 회원가입을 경우 회원가입 안된다는 페이지 표시 22.4.4

	// DB에서 확인하도록 수정
	$sql="SELECT `register` FROM `setting` ";
	$result = pdo_query($sql);
	$row =  $result[0];

	if( $row['register']==0) { // 회원가입 off인 경우

		$view_errors = "<center>";
		$view_errors .= "<h3>비공개 회원가입</h3>";
		$view_errors .= "<p>비공개 회원가입으로 운영되고 있습니다.</p>";
		$view_errors .= "<br>";
		$view_errors .= "<span class=text-success>선생님이 안내한 아이디를 이용해서 로그인 하세요</span>";
		$view_errors .= "</center>";
		$view_errors .= "<br><br>";
		require("template/".$OJ_TEMPLATE."/error.php");
		exit(0);
	}
	require_once('./include/setlang.php');
	$view_title= "Register a new account";
	
	///////////////////////////MAIN	
		
	/////////////////////////Template
	require("template/".$OJ_TEMPLATE."/registerpage.php");
	/////////////////////////Common foot
	if(file_exists('./include/cache_end.php'))
		require_once('./include/cache_end.php');
?>
