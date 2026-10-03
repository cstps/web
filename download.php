<?php
////////////////////////////Common head
require_once('./include/db_info.inc.php');
require_once('./include/course_functions.inc.php');
if((!isset($OJ_DOWNLOAD))||!$OJ_DOWNLOAD){
    $view_errors="Download Disabled!";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);

}
$sid=intval($_GET['sid']);
$name=basename($_GET['name'],".out");
$sql="select problem_id,contest_id,user_id from solution where solution_id=?";
$data=pdo_query($sql,$sid);
//var_dump($sql);
if(count($data)>0){
   $row=$data[0];
   $pid=$row[0];
   $cid=$row[1];
   $uid=$row[2];
   if(!(isset($_SESSION[$OJ_NAME.'_'.'user_id']) && $uid == $_SESSION[$OJ_NAME.'_'.'user_id']
         || isset($_SESSION[$OJ_NAME.'_'.'administrator'])
       )){
       	    $view_errors="not your submission"; 
	    require("template/".$OJ_TEMPLATE."/error.php");
	    exit(0);
   }
   // 수행모드 학생에게는 문제의 입력/출력 테스트 데이터를 제공하지 않는다.
   // 관리자와 source_browser는 기존 관리 권한을 유지한다.
   $current_user =
       isset($_SESSION[$OJ_NAME.'_user_id'])
           ? trim((string)$_SESSION[$OJ_NAME.'_user_id'])
           : '';

    $is_download_privileged = (
        isset($_SESSION[$OJ_NAME . '_administrator']) ||
        isset($_SESSION[$OJ_NAME . '_source_browser']) ||
        (
            $cid > 0 &&
            isset($_SESSION[$OJ_NAME . '_m' . $cid])
        ) ||
        (
            $cid > 0 &&
            course_can_view_contest_process($cid)
        )
    );

   if (
       $current_user !== '' &&
       !$is_download_privileged &&
       course_should_restrict_student_history($current_user)
   ) {
       $view_errors =
           "<h2>수행모드에서는 테스트 데이터를 다운로드할 수 없습니다.</h2>";

       require(
           "template/".
           $OJ_TEMPLATE.
           "/error.php"
       );

       exit(0);
   }

   if(isset($OJ_NOIP_KEYWORD)&&$OJ_NOIP_KEYWORD){
	$now = strftime("%Y-%m-%d %H:%M",time());
	$sql = "select 1 from `contest` where contest_id=? and `start_time` < ? and `end_time` > ? and `title` like ?";
        $rrs = pdo_query($sql, $cid ,$now , $now , "%$OJ_NOIP_KEYWORD%");
        $flag = count($rrs) > 0 ;
	if($flag){
	    $view_errors = "<h2> $MSG_NOIP_WARNING </h2>";
	    require("template/".$OJ_TEMPLATE."/error.php");
	    exit(0);
	}

   }
   $infile="$OJ_DATA/$pid/$name.in"; 
   $outfile="$OJ_DATA/$pid/$name.out"; 
   $zipname = tempnam(__DIR__.'/upload', '');
   $zip = new ZipArchive();

        if ($zip->open($zipname, ZIPARCHIVE::CREATE) !== TRUE) {
            exit('파일을 열거나 생성할 수 없습니다.');
        }
        $files = [ $infile,$outfile ];

        $zip->open($zipname, ZipArchive::CREATE);
        foreach ($files as $file) {
              
            $fileContent = file_get_contents($file);
            $file = iconv('utf-8', 'GBK', basename($file));
            $zip->addFromString($file, $fileContent);
        }
        $zip->close();

        header('Content-Type: application/zip;charset=utf8');
        header('Content-disposition: attachment; filename='.$name. date('Y-m-d') . '.zip');
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);
        unlink($zipname);
        die();
}
?>
