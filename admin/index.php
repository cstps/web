<?php

require_once(__DIR__ . "/admin-init.php");

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

header(
    "Location: help.php",
    true,
    302
);

exit;
