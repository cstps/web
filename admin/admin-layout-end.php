</main>

</div>

<?php
$admin_layout_js_path =
    __DIR__ . "/admin-layout.js";

$admin_layout_js_version =
    file_exists($admin_layout_js_path)
    ? filemtime($admin_layout_js_path)
    : "1";
?>

<script
    src="admin-layout.js?v=<?php echo rawurlencode(
        (string)$admin_layout_js_version
    ); ?>"></script>

</body>

</html>