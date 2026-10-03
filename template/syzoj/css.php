<?php
// ============================================================
// 공통 CSS / 수식 렌더링 자원
//
// 브라우저에서 사용하는 정적 자원 경로는 현재 URL의 깊이와
// 관계없이 항상 웹 루트를 기준으로 생성한다.
//
// $OJ_CDN_URL이 설정되어 있으면 해당 CDN 주소를 사용하고,
// 비어 있으면 /template/... 절대경로를 사용한다.
// ============================================================

$asset_base =
    rtrim((string)$OJ_CDN_URL, '/') .
    '/template/' .
    $OJ_TEMPLATE;
?>

<link rel="stylesheet" href="<?php echo $asset_base; ?>/css/style.css">
<link rel="stylesheet" href="<?php echo $asset_base; ?>/css/tomorrow.css">

<link rel="stylesheet" href="<?php echo $asset_base; ?>/semantic/semantic.min.css">

<link rel="stylesheet" href="<?php echo $asset_base; ?>/css/katex.min.css">

<script
    defer
    src="<?php echo $asset_base; ?>/css/katex.js">
</script>

<script>
var katex_config = {
    delimiters: [
        {left: "$$", right: "$$", display: true},
        {left: "$", right: "$", display: false}
    ]
};
</script>

<script
    defer
    src="<?php echo $asset_base; ?>/css/auto-render.min.js"
    onload="renderMathInElement(document.body, katex_config)">
</script>

<link
    href="<?php echo $asset_base; ?>/css/morris.min.css"
    rel="stylesheet">

<link
    href="<?php echo $asset_base; ?>/css/FiraMono.css"
    rel="stylesheet">

<link
    href="<?php echo $asset_base; ?>/css/latin.css"
    rel="stylesheet">

<link
    href="<?php echo $asset_base; ?>/css/latin-ext.css"
    rel="stylesheet">

<link
    href="<?php echo $asset_base; ?>/css/Exo.css"
    rel="stylesheet">
