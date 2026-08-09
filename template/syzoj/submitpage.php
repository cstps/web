<?php $show_title="제출 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php");?>
<center>
 <script src="<?php echo $OJ_CDN_URL?>include/checksource.js"></script>
<form id="frmSolution" action="submit.php" method="post">
<?php if (isset($id)){?>
Problem <span class=blue><b><?php echo $id?></b></span>
<input id=problem_id type='hidden' value='<?php echo $id?>' name="id" ><br>
<?php }else{
//$PID="ABCDEFGHIJKLMNOPQRSTUVWXYZ";
//if ($pid>25) $pid=25;
?>
Problem <span class=blue><b><?php echo chr($pid+ord('A'))?></b></span> of Contest <span class=blue><b><?php echo $cid?></b></span><br>
<input id="cid" type='hidden' value='<?php echo $cid?>' name="cid">
<input id="pid" type='hidden' value='<?php echo $pid?>' name="pid">
<?php }?>
<span id="language_span">Language:
<select id="language" name="language" onChange="reloadtemplate($(this).val());" >
<?php
$lang_count=count($language_ext);
if(isset($_GET['langmask']))
$langmask=$_GET['langmask'];
else
$langmask=$OJ_LANGMASK;
$lang=(~((int)$langmask))&((1<<($lang_count))-1);
if(isset($_COOKIE['lastlang'])) $lastlang=$_COOKIE['lastlang'];
else $lastlang=0;
for($i=0;$i<$lang_count;$i++){
if($lang&(1<<$i))
echo"<option value=$i ".( $lastlang==$i?"selected":"").">
".$language_name[$i]."
</option>";
}
?>
</select>
<?php if($OJ_VCODE){?>
<?php echo $MSG_VCODE?>:
<input name="vcode" size=4 type=text><img id="vcode" alt="click to change" src="vcode.php" onclick="this.src='vcode.php?'+Math.random()">
<?php }?>

<br>
</span>
<?php if(isset($view_process_mode) && $view_process_mode){ ?>

<div style="
	width:80%;
	max-width:1000px;
	margin:20px auto;
	text-align:left;
	border:1px solid #ddd;
	border-radius:6px;
	padding:20px;
	background:#fafafa;
">

	<h3 style="margin-top:0;">
		문제 해결 과정
	</h3>


	<?php if(!isset($view_is_resubmit) || !$view_is_resubmit){ ?>

		<!-- =================================================
		     첫 제출
		     ================================================= -->

		<div style="margin-bottom:20px;">

			<label>
				<strong>1. 풀이 계획</strong>
			</label>

			<p style="color:#666; margin-top:5px;">
				코드를 작성하기 전에 문제를 어떻게 해결할지 작성하세요.
			</p>

			<textarea
				name="plan_text"
				id="plan_text"
				rows="4"
				style="width:100%;"
				placeholder="예: 반복문을 이용하여 입력된 값을 하나씩 확인한다."
				required
			></textarea>

			<input
				type="hidden"
				name="reflection"
				value=""
			>

		</div>


	<?php }else{ ?>

		<!-- =================================================
		     재제출
		     ================================================= -->

		<div style="
			padding:15px;
			margin-bottom:15px;
			background:#fff;
			border:1px solid #ddd;
			border-radius:5px;
		">

			<strong>직전 제출 결과</strong>

			<br><br>

			<?php
			echo htmlentities(
				$view_previous_result_text,
				ENT_QUOTES,
				"UTF-8"
			);
			?>

		</div>


		<div style="
			padding:15px;
			margin-bottom:20px;
			background:#fff;
			border:1px solid #ddd;
			border-radius:5px;
		">

			<strong>처음 세운 풀이 계획</strong>

			<br><br>

			<?php

			if(
				isset($view_previous_plan_text) &&
				$view_previous_plan_text != ""
			){

				echo nl2br(
					htmlentities(
						$view_previous_plan_text,
						ENT_QUOTES,
						"UTF-8"
					)
				);

			}
			else{

				echo "<span style='color:#999;'>기록된 풀이 계획이 없습니다.</span>";

			}

			?>

		</div>


		<div style="margin-bottom:20px;">

			<label>
				<strong>1. 이번에 수정한 부분</strong>
			</label>

			<p style="color:#666; margin-top:5px;">
				수정한 부분을 선택하세요. 여러 개 선택할 수 있습니다.
			</p>


			<div style="
				display:flex;
				flex-wrap:wrap;
				gap:10px 20px;
				margin-top:10px;
				margin-bottom:15px;
			">

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="input">
					입력
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="output">
					출력
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="condition">
					조건문
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="loop">
					반복문
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="variable">
					변수
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="function">
					함수
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="data">
					배열 / 자료구조
				</label>

				<label>
					<input type="checkbox"
						name="change_type[]"
						value="other">
					기타
				</label>

			</div>


			<label>
				<strong>간단한 수정 메모</strong>
				<span style="color:#888;">
					(선택)
				</span>
			</label>

			<input
				type="text"
				name="reflection"
				id="reflection"
				style="
					width:100%;
					margin-top:8px;
					padding:8px;
				"
				maxlength="100"
				placeholder="예: 출력 문자열 오타 수정"
			>


			<!-- 재제출에서는 최초 풀이계획을 새로 저장하지 않음 -->
			<input
				type="hidden"
				name="plan_text"
				value=""
			>

		</div>


	<?php } ?>




		<!-- =====================================================
		생성형 AI 활용
		첫 제출 / 재제출 공통
		===================================================== -->

		<div style="margin-bottom:20px;">

			<label>
				<strong>
					<?php
					echo (isset($view_is_resubmit) && $view_is_resubmit)
						? "2. 이번 수정에서 생성형 AI를 활용했나요?"
						: "2. 생성형 AI를 활용했나요?";
					?>
				</strong>
			</label>

			<p style="color:#666; margin-top:5px;">
				가장 가까운 활용 방법 하나를 선택하세요.
			</p>

			<div style="
				display:flex;
				flex-wrap:wrap;
				gap:10px 22px;
				margin-top:10px;
			">

				<label>
					<input
						type="radio"
						name="ai_usage_choice"
						value="none"
						checked
					>
					사용하지 않음
				</label>

				<label>
					<input
						type="radio"
						name="ai_usage_choice"
						value="idea"
					>
					힌트·아이디어
				</label>

				<label>
					<input
						type="radio"
						name="ai_usage_choice"
						value="syntax"
					>
					문법 도움
				</label>

				<label>
					<input
						type="radio"
						name="ai_usage_choice"
						value="debug"
					>
					오류 수정
				</label>

				<label>
					<input
						type="radio"
						name="ai_usage_choice"
						value="generate"
					>
					코드 생성
				</label>

			</div>


			<!-- submit.php의 기존 구조와 호환하기 위한 hidden 값 -->

			<input
				type="hidden"
				name="ai_used"
				id="ai_used"
				value="0"
			>

			<input
				type="hidden"
				name="ai_usage_type"
				id="ai_usage_type"
				value="none"
			>

		</div>


		<!-- =====================================================
			AI 질문 - 선택사항
			===================================================== -->

		<div
			id="ai_prompt_area"
			style="
				display:none;
				margin-bottom:20px;
			"
		>

			<label>
				<strong>AI에게 질문한 내용</strong>

				<span style="color:#888;">
					(선택)
				</span>
			</label>

			<input
				type="text"
				name="ai_prompt"
				id="ai_prompt"
				maxlength="200"
				style="
					width:100%;
					margin-top:8px;
					padding:8px;
				"
				placeholder="예: 반복문 범위를 어떻게 고쳐야 하는지 질문함"
			>

		</div>

</div>

<?php } ?>


<?php if($OJ_ACE_EDITOR){ ?>
	<pre style="width:80%;height:300px" id="source"><?php echo htmlentities($view_src,ENT_QUOTES,"UTF-8")?></pre><br>

	<input type=hidden id="hide_source" name="source" value=""/>
<?php }else{ ?>
	<textarea style="width:80%;height:300px" id="source" name="source"><?php echo htmlentities($view_src,ENT_QUOTES,"UTF-8")?></textarea><br>
<?php }?>

<?php if (isset($OJ_TEST_RUN)&&$OJ_TEST_RUN){?>
<?php echo $MSG_Input?>:<textarea style="width:30%" cols=40 rows=5 id="input_text" name="input_text" ><?php echo $view_sample_input?></textarea>
<?php echo $MSG_Output?>:
<textarea style="width:30%" cols=10 rows=5 id="out" name="out" disabled="true" >SHOULD BE:
<?php echo $view_sample_output?>
</textarea>
<br>
<?php } ?>
<!-- <input id="Submit" class="btn btn-info" type=button value="<?php echo $MSG_SUBMIT?>" onclick="do_submit();" > -->
<div class="ui center aligned vertical segment" style="padding-bottom: 0; ">
<button
	type="submit"
	class="ui labeled icon button"
>
	<i class="ui edit icon"></i>
	제출
</button>
<!--div onclick="show_custom_test()" class="ui positive button">自定义测试</div-->
</div>
<?php if (isset($OJ_ENCODE_SUBMIT)&&$OJ_ENCODE_SUBMIT){?>
<input class="btn btn-success" title="WAF gives you reset ? try this." type=button value="Encoded <?php echo $MSG_SUBMIT?>"  onclick="encoded_submit();">
<input type=hidden id="encoded_submit_mark" name="reverse2" value="reverse"/>
<?php }?>

<!-- <?php if (isset($OJ_TEST_RUN)&&$OJ_TEST_RUN){?>
<input id="TestRun" class="btn btn-info" type=button value="<?php echo $MSG_TR?>" onclick=do_test_run();>
<span class="btn" id=result>状态</span>
<?php }?>
<?php if (isset($OJ_BLOCKLY)&&$OJ_BLOCKLY){?>
	<input id="blockly_loader" type=button class="btn" onclick="openBlockly()" value="<?php echo $MSG_BLOCKLY_OPEN?>" style="color:white;background-color:rgb(169,91,128)">
	<input id="transrun" type=button  class="btn" onclick="loadFromBlockly() " value="<?php echo $MSG_BLOCKLY_TEST?>" style="display:none;color:white;background-color:rgb(90,164,139)">
<div id="blockly" class="center">Blockly</div>
<?php }?> -->
</form>
</center>

<script>
var sid=0;
var i=0;
var using_blockly=false;
var judge_result=[<?php
foreach($judge_result as $result){
echo "'$result',";
}
?>''];
function print_result(solution_id)
{
sid=solution_id;
$("#out").load("status-ajax.php?tr=1&solution_id="+solution_id);
}
function fresh_result(solution_id)
{
	var tb=window.document.getElementById('result');
	if(solution_id==undefined){
		tb.innerHTML="Vcode Error!";		
		if($("#vcode")!=null) $("#vcode").click();
		return ;
	}
	sid=solution_id;
	var xmlhttp;
	if (window.XMLHttpRequest)
	{// code for IE7+, Firefox, Chrome, Opera, Safari
	xmlhttp=new XMLHttpRequest();
	}
	else
	{// code for IE6, IE5
	xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function()
	{
	if (xmlhttp.readyState==4 && xmlhttp.status==200)
	{
	var r=xmlhttp.responseText;
	var ra=r.split(",");
	// alert(r);
	// alert(judge_result[r]);
	var loader="<img width=18 src=image/loader.gif>";
	var tag="span";
	if(ra[0]<4) tag="span disabled=true";
	else tag="a";
	{
		if(ra[0]==11)
		
		tb.innerHTML="<"+tag+" href='ceinfo.php?sid="+solution_id+"' class='badge badge-info' target=_blank>"+judge_result[ra[0]]+"</"+tag+">";
		else
		tb.innerHTML="<"+tag+" href='reinfo.php?sid="+solution_id+"' class='badge badge-info' target=_blank>"+judge_result[ra[0]]+"</"+tag+">";
	}
	if(ra[0]<4)tb.innerHTML+=loader;
	tb.innerHTML+="Memory:"+ra[1]+"kb&nbsp;&nbsp;";
	tb.innerHTML+="Time:"+ra[2]+"ms";
	if(ra[0]<4)
	window.setTimeout("fresh_result("+solution_id+")",2000);
	else{
		window.setTimeout("print_result("+solution_id+")",2000);
		count=1;
	}
	}
	}
	xmlhttp.open("GET","status-ajax.php?solution_id="+solution_id,true);
	xmlhttp.send();
}
function getSID(){
var ofrm1 = document.getElementById("testRun").document;
var ret="0";
if (ofrm1==undefined)
{
ofrm1 = document.getElementById("testRun").contentWindow.document;
var ff = ofrm1;
ret=ff.innerHTML;
}
else
{
var ie = document.frames["frame1"].document;
ret=ie.innerText;
}
return ret+"";
}
var count=0;
	 
function encoded_submit(){

      var mark="<?php echo isset($id)?'problem_id':'cid';?>";
        var problem_id=document.getElementById(mark);

	if(typeof(editor) != "undefined")
		$("#hide_source").val(editor.getValue());
        if(mark=='problem_id')
                problem_id.value='<?php if(isset($id)) echo $id?>';
        else
                problem_id.value='<?php if(isset($cid))echo $cid?>';

        document.getElementById("frmSolution").target="_self";
        document.getElementById("encoded_submit_mark").name="encoded_submit";
        var source=$("#source").val();
	if(typeof(editor) != "undefined") {
		source=editor.getValue();
        	$("#hide_source").val(encode64(utf16to8(source)));
	}else{
        	$("#source").val(encode64(utf16to8(source)));
	}
//      source.value=source.value.split("").reverse().join("");
//      alert(source.value);
        document.getElementById("frmSolution").submit();
}

// ============================================================
// 일반 제출 처리
// - ACE Editor 내용을 hidden source에 복사
// - form.submit()을 다시 호출하지 않음
// ============================================================

document.getElementById("frmSolution").addEventListener(
	"submit",
	function(event){

		// Blockly를 사용하는 경우 먼저 코드로 변환
		if(using_blockly){
			translate();
		}

		// ACE Editor 사용 시 실제 코드를 hidden input에 저장
		if(typeof(editor) != "undefined"){

			var hideSource =
				document.getElementById("hide_source");

			if(hideSource){
				hideSource.value = editor.getValue();
			}
		}

		// 문제 ID / 대회 ID 확인
		var mark =
			"<?php echo isset($id)?'problem_id':'cid';?>";

		var problem_id =
			document.getElementById(mark);

		if(problem_id){

			if(mark == "problem_id"){

				problem_id.value =
					"<?php if(isset($id)) echo $id?>";

			}
			else{

				problem_id.value =
					"<?php if(isset($cid)) echo $cid?>";

			}
		}

		// 일반 제출
		document.getElementById("frmSolution").target = "_self";

		// 여기서 submit()을 다시 호출하지 않는다.
		// 브라우저가 원래 submit 동작을 계속 진행함
	}
);

var handler_interval;
function do_test_run(){
	if( handler_interval) window.clearInterval( handler_interval);
	var loader="<img width=18 src=image/loader.gif>";
	var tb=window.document.getElementById('result');
        var source=$("#source").val();
	if(typeof(editor) != "undefined") {
		source=editor.getValue();
        	$("#hide_source").val(source);
	}
	if(source.length<10) return alert("too short!");
	if(tb!=null)tb.innerHTML=loader;

	var mark="<?php echo isset($id)?'problem_id':'cid';?>";
	var problem_id=document.getElementById(mark);
	problem_id.value=-problem_id.value;
	document.getElementById("frmSolution").target="testRun";
	//$("#hide_source").val(editor.getValue());
	//document.getElementById("frmSolution").submit();
	$.post("submit.php?ajax",$("#frmSolution").serialize(),function(data){fresh_result(data);});
  	$("#Submit").prop('disabled', true);
  	$("#TestRub").prop('disabled', true);
	problem_id.value=-problem_id.value;
	count=20;
	handler_interval= window.setTimeout("resume();",1000);
}
function resume(){
	count--;
	var s=$("#Submit")[0];
	var t=$("#TestRub")[0];
	if(count<0){
		s.disabled=false;
		if(t!=null)t.disabled=false;
		s.value="<?php echo $MSG_SUBMIT?>";
		if(t!=null)t.value="<?php echo $MSG_TR?>";
		if( handler_interval) window.clearInterval( handler_interval);
		if($("#vcode")!=null) $("#vcode").click();
	}else{
		s.value="<?php echo $MSG_SUBMIT?>("+count+")";
		if(t!=null)t.value="<?php echo $MSG_TR?>("+count+")";
		window.setTimeout("resume();",1000);
	}
}
function switchLang(lang){
   var langnames=new Array("c_cpp","c_cpp","pascal","java","ruby","sh","python","php","perl","csharp","objectivec","vbscript","scheme","c_cpp","c_cpp","lua","javascript","golang");
   editor.getSession().setMode("ace/mode/"+langnames[lang]);

}
function reloadtemplate(lang){
   //console.log("lang="+lang);
   document.cookie="lastlang="+lang.value;
   //alert(document.cookie);
   var url=window.location.href;
   var i=url.indexOf("sid=");
   if(i!=-1) url=url.substring(0,i-1);
 //  if(confirm("<?php echo  $MSG_LOAD_TEMPLATE_CONFIRM?>"))
 //       document.location.href=url;
   switchLang(lang);
}
function openBlockly(){
   $("#frame_source").hide();
   $("#TestRun").hide();
   $("#language")[0].scrollIntoView();
   $("#language").val(6).hide();
   $("#language_span").hide();
   $("#EditAreaArroundInfos_source").hide();
   $('#blockly').html('<iframe name=\'frmBlockly\' width=90% height=580 src=\'blockly/demos/code/index.html\'></iframe>'); 
  $("#blockly_loader").hide();
  $("#transrun").show();
  $("#Submit").prop('disabled', true);
  using_blockly=true;
  
}
function translate(){
  var blockly=$(window.frames['frmBlockly'].document);
  var tb=blockly.find('td[id=tab_python]');
  var python=blockly.find('pre[id=content_python]');
  tb.click();
  blockly.find('td[id=tab_blocks]').click();
  if(typeof(editor) != "undefined") editor.setValue(python.text());
  else $("#source").val(python.text());
  $("#language").val(6);
 
}
function loadFromBlockly(){
 translate();
 do_test_run();
  $("#frame_source").hide();
//  $("#Submit").prop('disabled', false);
}
// ============================================================
// 생성형 AI 활용 선택
// ============================================================

function updateAIUsage(){

	var selected =
		document.querySelector(
			'input[name="ai_usage_choice"]:checked'
		);

	var aiUsed =
		document.getElementById("ai_used");

	var aiUsageType =
		document.getElementById("ai_usage_type");

	var promptArea =
		document.getElementById("ai_prompt_area");

	var prompt =
		document.getElementById("ai_prompt");


	if(!selected)
		return;


	if(selected.value === "none"){

		aiUsed.value = "0";
		aiUsageType.value = "none";

		if(promptArea)
			promptArea.style.display = "none";

		if(prompt)
			prompt.value = "";

	}
	else{

		aiUsed.value = "1";
		aiUsageType.value = selected.value;

		if(promptArea)
			promptArea.style.display = "block";

	}

}


document.addEventListener(
	"DOMContentLoaded",
	function(){

		var choices =
			document.querySelectorAll(
				'input[name="ai_usage_choice"]'
			);

		for(var i=0; i<choices.length; i++){

			choices[i].addEventListener(
				"change",
				updateAIUsage
			);

		}

		updateAIUsage();

	}
);

</script>
<script language="Javascript" type="text/javascript" src="<?php echo $OJ_CDN_URL?>include/base64.js"></script>
<?php if($OJ_ACE_EDITOR){ ?>
<script src="<?php echo $OJ_CDN_URL?>ace/ace.js"></script>
<script src="<?php echo $OJ_CDN_URL?>ace/ext-language_tools.js"></script>
<script>
    ace.require("ace/ext/language_tools");
    var editor = ace.edit("source");
    editor.setTheme("ace/theme/chrome");
    switchLang(<?php echo $lastlang ?>);
    editor.setOptions({
	    enableBasicAutocompletion: true,
	    enableSnippets: true,
	    enableLiveAutocompletion: true,
		fontSize: "13pt", // font size 키우기

    });
   reloadtemplate($("#language").val()); 
     
</script>
<?php
  $pid_for_key = isset($id) ? $id : (isset($pid) ? $pid : 'unknown');
  $cid_prefix = isset($cid) ? "contest_" . $cid . "_" : "";
?>
<script>
	// 자동 저장 기능 (localStorage 사용)
	const localKey = "autosave_code_<?php echo $cid_prefix . $pid_for_key ?>";

	// 복원 확인
	const savedCode = localStorage.getItem(localKey);
	if (savedCode && typeof editor !== "undefined") {
	const shouldRestore = confirm("💾 저장된 코드가 있습니다. 복원하시겠습니까?");
	if (shouldRestore) {
		editor.setValue(savedCode, -1);
		
		// 저장 시간 표시
		const lastSaved = localStorage.getItem(localKey + "_time");
		if (lastSaved) {
		const savedDate = new Date(parseInt(lastSaved));
		const now = new Date();
		const diffSec = Math.floor((now - savedDate) / 1000);
		let timeStr = "";
		if (diffSec < 60) timeStr = `${diffSec}초 전`;
		else if (diffSec < 3600) timeStr = `${Math.floor(diffSec / 60)}분 전`;
		else timeStr = savedDate.toLocaleString();

		const notice = document.createElement("div");
		notice.innerText = `💾 저장된 코드가 ${timeStr}에 저장되었습니다.`;
		notice.style.color = "#666";
		notice.style.marginBottom = "10px";
		document.getElementById("editor").before(notice);
		}
	} else {
		localStorage.removeItem(localKey);
		localStorage.removeItem(localKey + "_time");
	}
	}

	// 자동 저장: 5초마다
	setInterval(() => {
	if (typeof editor !== "undefined") {
		const code = editor.getValue();
		localStorage.setItem(localKey, code);
		localStorage.setItem(localKey + "_time", Date.now());
	}
	}, 5000);

	// 제출 시 삭제
	document.getElementById("frmSolution").addEventListener("submit", () => {
	localStorage.removeItem(localKey);
	localStorage.removeItem(localKey + "_time");
	});

</script>

<?php }?>

  </body>
</html>
<?php include("template/$OJ_TEMPLATE/footer.php");?>
