<?php

require_once(
    __DIR__ .
    '/include/admin_init.php'
);

class_share_admin_require_login();

class_share_admin_require_post_csrf();

class_share_admin_logout();

header(
    'Location: /class-share/admin/login.php',
    true,
    303
);

exit;
