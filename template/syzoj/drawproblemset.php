<?php $show_title="$MSG_SOURCE - $OJ_NAME"; ?>

<?php include("template/$OJ_TEMPLATE/header.php");?>
    <link rel="stylesheet" href="<?php echo $OJ_CDN_URL?>/include/codemirror.css" />
    <script src="<?php echo $OJ_CDN_URL?>/include/codemirror.js"></script>
    <script src="<?php echo $OJ_CDN_URL?>/include/javascript.js"></script>
    <script src="<?php echo $OJ_CDN_URL?>/include/processing.min.js"></script>
        


    <div id="codeEditor-wrapper">
        <div id="codeEditor"></div>
        <button onclick="runCode()">Run</button>
        <hr>
    </div>
    <div id="processingCanvas-wrapper">
        <canvas id="processingCanvas" class="processingCanvas" width="500" height="500"></canvas>
    </div>

    <script type="text/javascript">
      var p = null; // Processing 인스턴스

      var editor = CodeMirror(document.getElementById("codeEditor"), {
        value: 
        `
void setup(){
    size(500,500);
}
void draw(){
    stroke(#F5F6CE);
    fill(#75ccd1); // 색상코드 사용 fill(255,0,0);
    ellipse(mouseX,mouseY,20,20);
}
`,
        mode:  "javascript",
        lineNumbers: true
      });

      function runCode() {
        if (p) {
          p.exit(); // 기존 Processing 인스턴스 종료
        }

        var code = editor.getValue();
        var canvas = document.getElementById("processingCanvas");
        var rect = canvas.getBoundingClientRect();

        p = new Processing(canvas, code);

        canvas.addEventListener("mousemove", function(event) {
          var x = event.clientX - rect.left;
          var y = event.clientY - rect.top;

          // 보정된 마우스 좌표 사용
          p.mouseX = x;
          p.mouseY = y;
        });
      }
    </script>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
