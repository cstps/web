<?php
require_once("admin-header.php");
?>

<!DOCTYPE html>
<html>

<head>

    <title>1024.kr 관리자</title>

</head>

<body class="admin-layout-page">


<?php
require_once("admin-bar.php");
?>


<div class="admin-layout">

    <aside class="admin-sidebar">

        <?php
        require("menu2.php");
        ?>

    </aside>


    <main class="admin-main">

        <iframe
            name="main"
            src="help.php"
            class="admin-main-frame"
            title="관리자 작업 영역"
        ></iframe>

    </main>

</div>


</body>
</html>