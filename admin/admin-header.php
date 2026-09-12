<?php
require_once("../include/db_info.inc.php");
require_once("../include/my_func.inc.php");
require_once("../include/permission_functions.inc.php");
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<meta
  name="viewport"
  content="width=device-width, initial-scale=1">

<link
  rel="stylesheet"
  href="../include/hoj.css"
  type="text/css">

<link
  rel="stylesheet"
  href="admin.css?v=<?php
                    echo filemtime(
                      __DIR__ . '/admin.css'
                    );
                    ?>"
  type="text/css">

<script src="../template/syzoj/jquery.min.js"></script>
<script>
  $("document").ready(function() {
    $("form").append("<div id='csrf' />");
    $("#csrf").load("../csrf.php");
  });
</script>
<?php
$admin_page_access_allowed =
  isset($admin_page_access_allowed) &&
  $admin_page_access_allowed === true;

if (
  !oj_can_access_admin() &&
  !$admin_page_access_allowed
) {
  http_response_code(403);
  exit('관리자 페이지에 접근할 권한이 없습니다.');
}
require_once("../template/$OJ_TEMPLATE/css.php");
if (file_exists("../lang/$OJ_LANG.php")) require_once("../lang/$OJ_LANG.php");
?>