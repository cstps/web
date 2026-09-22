<?php

require_once(__DIR__ . "/admin-init.php");

http_response_code(410);
header("Content-Type: text/plain; charset=UTF-8");

exit("문제 번호 변경 기능은 데이터 정합성 보호를 위해 비활성화되었습니다.\n");
