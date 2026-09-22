<?php

require_once(__DIR__ . "/admin-init.php");

http_response_code(410);
header("Content-Type: text/plain; charset=UTF-8");

exit(
    "문제 영구 삭제 기능은 안전한 보관·삭제 기능으로 교체하기 위해 비활성화되었습니다.\n"
);