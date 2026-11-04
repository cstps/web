<?php
function pdo_query($sql)
{
    $mysql_init_command = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
        ? constant('Pdo\\Mysql::ATTR_INIT_COMMAND')
        : constant('PDO::MYSQL_ATTR_INIT_COMMAND');

	$num_args = func_num_args();
	$args = func_get_args();
	$args = array_slice($args, 1, --$num_args);
	// SQL 앞쪽의 공백, 줄바꿈, 탭 제거
	// SELECT/INSERT/CREATE 등의 쿼리 종류 판별 오류 방지
	$sql = ltrim($sql);

	global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $dbh, $OJ_SAE, $OJ_TEMPLATE;
	try {
		if (!$dbh || stripos($sql, "create") === 0 || stripos($sql, "drop") === 0 || stripos($sql, "grant") === 0) {

			if (isset($OJ_SAE) && $OJ_SAE) {
				$OJ_DATA = "saestor://data/";
				//  for sae.sina.com.cn
				$DB_NAME = SAE_MYSQL_DB;
				$dbh = new PDO("mysql:host=" . SAE_MYSQL_HOST_M . ';dbname=' . SAE_MYSQL_DB, SAE_MYSQL_USER, SAE_MYSQL_PASS, array($mysql_init_command => "set names utf8"));
			} else {

				if (stripos($sql, "create") === 0 || stripos($sql, "drop") === 0 || stripos($sql, "grant") === 0) {
					$dbh = new PDO("mysql:host=" . $DB_HOST, $DB_USER, $DB_PASS, array(PDO::ATTR_PERSISTENT => true, $mysql_init_command => "set names utf8"));
					//					echo "General SQL";
				} else {
					$dbh = new PDO("mysql:host=" . $DB_HOST . ';dbname=' . $DB_NAME, $DB_USER, $DB_PASS, array(PDO::ATTR_PERSISTENT => true, $mysql_init_command => "set names utf8"));
					//					echo "$DB_NAME SQL";
				}
			}
		}
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sth = $dbh->prepare($sql);
		$sth->execute($args);
		$result = array();
		if (stripos($sql, "select") === 0) {
			$result = $sth->fetchAll();
		} else if (stripos($sql, "insert") === 0) {
			$result = $dbh->lastInsertId();
		} else {
			$result = $sth->rowCount();
		}
		//print($sql);
		$sth->closeCursor();
		return $result;
	} catch (PDOException $e) {

		error_log(
			'[pdo_query] ' .
				$e->getMessage()
		);

		if (
			stripos($sql, "create") === 0 ||
			stripos($sql, "drop") === 0
		) {
			echo "continue\n";
		}

		return false;
	}
}
