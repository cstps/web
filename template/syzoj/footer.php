</div>
</div>
<script src="<?php echo $OJ_CDN_URL.$path_fix."template/$OJ_TEMPLATE"?>/css/semantic.min.js"></script>
<script src="<?php echo $path_fix."template/$OJ_TEMPLATE"?>/css/Chart.min.js"></script>
<footer>
    <style>
    .footer {
        line-height: 1.4285em;
        font-family: "Lato", "Noto Sans CJK SC", "Source Han Sans SC", "PingFang SC", "Hiragino Sans GB", "Microsoft Yahei", "WenQuanYi Micro Hei", "Droid Sans Fallback", "sans-serif";
        box-sizing: inherit;
        padding: 0 !important;
        border: none !important;
        color: #888;
        font-size: 1rem;
        margin: 35px 0 14px !important;
        position: relative;
        width: 100%;
        bottom: 0;
        background: none transparent;
        border-radius: 0;
        box-shadow: none;
    }
    </style>
    <div class="footer">
        <div class="ui center aligned container">
            <div><?php echo $domain==$DOMAIN?$OJ_NAME:ucwords($OJ_NAME)."'s OJ"?> is powered by <a style="color: inherit !important;" class=" " title="GitHub"
                    target="_blank" rel="noreferrer noopener" href="https://github.com/zhblue/hustoj">HUSTOJ</a>, Theme
                by <a style="color: inherit !important;" href="https://github.com/syzoj">SYZOJ</a></div>
                <div>운영자 : <a style="color: inherit !important;" href="mailto:seotos@gmail.com">GTKBS-GNE</a> computer science teacher(진주제일중)</div>
	 <!--   <div> Running on <a href='https://debian.org' target='_blank'>Debian11</a> / <a href='https://www.loongson.cn' target='_blank'>Loongson 3A3000</a> </div> -->	
            <?php if ($OJ_BEIAN) { ?>
            <div>
                <a href="https://beian.miit.gov.cn/" style="text-decoration: none; color: #444444;"
                    target="_blank"><?php echo $OJ_BEIAN; ?></a>
            </div>
            <?php } ?>
        </div>
    </div>
    </div>

</footer>
    <script type="text/javascript" src="//wcs.naver.net/wcslog.js"></script>
    <script type="text/javascript">
    if(!wcs_add) var wcs_add = {};
    wcs_add["wa"] = "61f357cc3c2d10";
    if(window.wcs) {
        wcs_do();
    }
    </script>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PMGHQPWP"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
</body>


</html>
