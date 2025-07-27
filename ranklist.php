<!DOCTYPE html>
<?php

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $OJ_CACHE_SHARE=false;
        $cache_time=30;
        require_once('./include/cache_start.php');
    	require_once('./include/db_info.inc.php');
	require_once("./include/my_func.inc.php");
        require_once('./include/setlang.php');
        require_once('./include/memcache.php');

                // 학교 목록 가져오기
                $sql = "SELECT DISTINCT school FROM users WHERE school != '' AND defunct='N' ORDER BY school ASC";
                $schools = pdo_query($sql);


        if(isset($OJ_NOIP_KEYWORD)&&$OJ_NOIP_KEYWORD){
		$now = strftime("%Y-%m-%d %H:%M",time());
        	$sql="select count(contest_id) from contest where start_time<'$now' and end_time>'$now' and title like '%$OJ_NOIP_KEYWORD%'";
		$row=pdo_query($sql);
		$cols=$row[0];
		//echo $sql;
		//echo $cols[0];
		if($cols[0]>0) {
			
		      $view_errors =  "<h2> $MSG_NOIP_WARNING </h2>";
		      require("template/".$OJ_TEMPLATE."/error.php");
		      exit(0);

		}
 	}

        // ===== 특정 학교 랭킹 보기 기능 시작 =====
        if (isset($_GET['school']) && $_GET['school'] != '') {
        
                $scope = "";  // 템플릿 오류 방지를 위한 기본값

                $school = trim(urldecode($_GET['school']));


                // 존재하는 학교인지 검사
                $chk_sql = "SELECT COUNT(*) as cnt FROM users WHERE school = ? AND defunct='N'";
                $chk_res = pdo_query($chk_sql, $school);
                if (!$chk_res || $chk_res[0]['cnt'] == 0) {
                        $view_errors = "<h2>'$school' 에 해당하는 사용자 정보가 없습니다.</h2>";
                        require("template/$OJ_TEMPLATE/error.php");
                        exit;
                }

                // 페이지네이션 처리
                $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
                $page_size = 300;

                // 해당 학교 사용자 랭킹 쿼리
                $sql = "SELECT user_id, nick, solved, submit FROM users WHERE defunct='N' AND school = ? ORDER BY solved DESC, submit, reg_time LIMIT $start, $page_size";
                $result = pdo_query($sql, $school);

                $rows_cnt = count($result);
                $view_rank = [];
                $rank = $start;

                for ($i = 0; $i < $rows_cnt; $i++) {
                        $row = $result[$i];
                        $rank++;

                        $solved = $row['solved'];
                        $submit = $row['submit'];
                        $rate = $submit == 0 ? "0.00%" : sprintf("%.2lf%%", 100 * $solved / $submit);

                        $view_rank[$i][0] = str_pad($rank, 3, "0", STR_PAD_LEFT);
                        $view_rank[$i][1] = "<a href='userinfo.php?user=" . htmlentities($row['user_id']) . "'>" . $row['user_id'] . "</a>";
                        //$view_rank[$i][2] = htmlentities($row['nick'], ENT_QUOTES, "UTF-8");
                        $view_rank[$i][3] = "<a href='status.php?user_id=" . htmlentities($row['user_id']) . "&jresult=4'>" . $solved . "</a>";
                        $view_rank[$i][4] = "<a href='status.php?user_id=" . htmlentities($row['user_id']) . "'>" . $submit . "</a>";
                        $view_rank[$i][5] = $rate;
                }

                $view_title = "'$school' 랭킹";
                $sql_count = "SELECT COUNT(1) AS cnt FROM users WHERE defunct='N' AND school=?";
                $res_count = pdo_query($sql_count, $school);
                $view_total = $res_count[0]['cnt'];
                

                require("template/$OJ_TEMPLATE/ranklist.php");
                if (file_exists('./include/cache_end.php')) require_once('./include/cache_end.php');
                exit;
        }

        // ===== 학교 내 랭킹 보기 기능 끝 =====

        $view_title= $MSG_RANKLIST;
	// if(!isset($OJ_RANK_HIDDEN)) $OJ_RANK_HIDDEN="'admin','seotos','root'";

        $scope="";
        if(isset($_GET['scope']))
                $scope=$_GET['scope'];
        if($scope!=""&&$scope!='d'&&$scope!='w'&&$scope!='m')
                $scope='y';
	$where="";

	if(isset($_GET['prefix'])){
		$prefix=$_GET['prefix'];
		$where="where user_id like ?";
	}else{	
		$where="where  defunct='N' ";
	}
        $rank = 0;
        if(isset( $_GET ['start'] ))
                $rank = intval ( $_GET ['start'] );

                if(isset($OJ_LANG)){
                        require_once("./lang/$OJ_LANG.php");
                }

                $page_size=300;
                if(isset($_GET['prefix'])) $page_size=300;
                //$rank = intval ( $_GET ['start'] );
                if ($rank < 0)
                        $rank = 0;

                $sql = "SELECT `user_id`,`nick`,`solved`,`submit` FROM `users` $where ORDER BY `solved` DESC,submit,reg_time  LIMIT  " . strval ( $rank ) . ",$page_size";

                if($scope){
                        $s="";
                        switch ($scope){
                                case 'd':
                                        $s=date('Y').'-'.date('m').'-'.date('d');
                                        break;
                                case 'w':
                                        $monday=mktime(0, 0, 0, date("m"),date("d")-(date("w")+7)%8+1, date("Y"))                                                            ;
                                        //$monday->subDays(date('w'));
                                        $s=strftime("%Y-%m-%d",$monday);
                                        break;
                                case 'm':
                                        $s=date('Y').'-'.date('m').'-01';
                                        break;
                                default :
                                        $s=date('Y').'-01-01';
                        }
                        //echo $s."<-------------------------";
                        $sql="SELECT users.`user_id`,`nick`,s.`solved`,t.`submit` FROM `users`
                                INNER JOIN (
                                SELECT COUNT(DISTINCT problem_id) solved, user_id
                                FROM solution
                                WHERE in_date > STR_TO_DATE('$s','%Y-%m-%d') AND result = 4
                                GROUP BY user_id
                                ORDER BY solved DESC
                                LIMIT " . strval($rank) . ",$page_size
                                ) s ON users.user_id = s.user_id
                                INNER JOIN (
                                SELECT COUNT(problem_id) submit, user_id
                                FROM solution
                                WHERE in_date > STR_TO_DATE('$s','%Y-%m-%d')
                                GROUP BY user_id
                                ) t ON users.user_id = t.user_id
                                ORDER BY s.solved DESC, t.submit, reg_time
                                LIMIT " . strval($rank) . ",$page_size";  // ✅ 수정

//                      echo $sql;
                }


      
		
		if(isset($_GET['prefix'])){
			if(is_valid_user_name($_GET['prefix'])){
				$result = pdo_query($sql,$_GET['prefix']."%");
                                
			}else{
				 $view_errors =  "<h2>invalid user name prefix</h2>";
			         require("template/".$OJ_TEMPLATE."/error.php");
      				 exit(0);
			}
		}else{
                	$result = mysql_query_cache($sql) ;
                        
		}
                if($result) $rows_cnt=count($result);
                else $rows_cnt=0;
                $view_rank=Array();
                $i=0;
                for ( $i=0;$i<$rows_cnt;$i++ ) {
					
                        $row=$result[$i];
                        
                        $rank ++;
                        
                        $solCnt = $row['solved']; // 통과 개수

                        $rankX = 0; // 이미지 표시 위치
                        $rankY = 0; // 

                        $level_up_cnt = 0; // 레벨업하기 위한 문제 개수
                        $hobong = 0;        // 레벨의 호봉 최대치
                        if($solCnt<50){
                                // 10문제가 레벨업 기준값
                                $level_up_cnt = 10;
                                $rankX = ($solCnt - ($solCnt % $level_up_cnt))/$level_up_cnt;                             
                        }
                        else if($solCnt<98){
                                // 8문제가 레벨업 기준값
                                $level_up_cnt = 8;
                                $rankX = (($solCnt-50) - (($solCnt-50)% $level_up_cnt))/$level_up_cnt;
                                $rankY = 1;
                        }
                        else{
                                // 6문제가 레벨업 기준값
                                $level_up_cnt = 6;
                                $rankX = (($solCnt-98) - (($solCnt-98)% $level_up_cnt))/$level_up_cnt;
                                for($rankY = 2, $hobong = 6;$rankX>$level_up_cnt;){
                                        $rankX -=$level_up_cnt;
                                        $rankY++;
                                        $hobong++;
                                }        
                        }

                        $rankX *=(-25);
                        $rankX .="px";

                        $rankY *=(-25);

                        $rankY .="px";
                        $view_rank[$i][0]= str_pad($rank,3,"0",STR_PAD_LEFT);

                        $view_rank[$i][1]=  "<div><div style='
                        display:inline-block;
                        width:25px;
                        height:25px;
                        background:url(../../../image/rank25.jpg);
                        background-position: $rankX $rankY;
                        '></div><a href='userinfo.php?user=" .htmlentities ( $row['user_id'],ENT_QUOTES,"UTF-8") . "'>" . $row['user_id'] . "</a>"."</div>";
                        // 별명 숨김 $view_rank[$i][2]=  "<div class=center>" . htmlentities ( $row['nick'] ,ENT_QUOTES,"UTF-8") ."</div>";
                        $view_rank[$i][3]=  "<div class=center><a href='status.php?user_id=" .htmlentities ( $row['user_id'],ENT_QUOTES,"UTF-8") ."&jresult=4'>" . $row['solved']."</a>"."</div>";
                        $view_rank[$i][4]=  "<div class=center><a href='status.php?user_id=" . htmlentities ($row['user_id'],ENT_QUOTES,"UTF-8") ."'>" . $row['submit'] . "</a>"."</div>";

                        if ($row['submit'] == 0)
                                $view_rank[$i][5]= "0.00%";
                        else
                                $view_rank[$i][5]= sprintf ( "%.02lf%%", 100 * $row['solved'] / $row['submit'] );

//                      $i++;
                }

                $sql = "SELECT count(1) as `mycount` FROM `users`";
        //        $result = mysql_query ( $sql );
          // require("./include/memcache.php");
                $result = mysql_query_cache($sql);
                 $row=$result[0];
                $view_total=$row['mycount'];




/////////////////////////Template
require("template/".$OJ_TEMPLATE."/ranklist.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
        require_once('./include/cache_end.php');
?>
