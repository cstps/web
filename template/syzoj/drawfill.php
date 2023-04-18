<?php include("template/$OJ_TEMPLATE/header.php");?>

    <style>
      .code-editor {
        position: relative;
      }
      .line-number {
        position: absolute;
        left: 0;
        top: 0;
        width: 30px;
        height: 100%;
        text-align: right;
        font-family: monospace;
        color: gray;
        pointer-events: none;
        line-height: 1.5em;
      }
      .CodeMirror {
        height: 200px;
      }
    </style>

<div id="codeEditor" class="code-editor">
    </div>
    <br>
    <button onclick="runCode()">Run</button>
    <hr>
    <canvas id="processingCanvas"></canvas>
    <br>
    
    <script type="text/javascript">
      function createCodeEditor(parent, rows, cols) {
        var container = document.createElement("div");
        container.className = "code-editor";

        var lineNumber = document.createElement("div");
        lineNumber.className = "line-number";

        var codeArea = document.createElement("textarea");
        codeArea.className = "code-area";
        codeArea.rows = rows;
        codeArea.cols = cols;

        codeArea.value = `
void setup(){
    size(500,500);
}
void draw(){
    stroke(#F5F6CE);
    fill(#75ccd1); // 색상코드 사용 fill(255,0,0);
    ellipse(mouseX,mouseY,20,20);
}
`;
        container.appendChild(lineNumber);
        container.appendChild(codeArea);
        parent.appendChild(container);

        var editor = CodeMirror.fromTextArea(codeArea, {
          mode: "javascript",
          lineNumbers: true
        });

        return editor;
      }

      var editor = createCodeEditor(document.getElementById("codeEditor"), 10, 80);

      function runCode() {
        var code = editor.getValue();
        var canvas = document.getElementById("processingCanvas");

        var p = new Processing(canvas, code);
      }
      
    </script>

<?php include("template/$OJ_TEMPLATE/footer.php");?>
