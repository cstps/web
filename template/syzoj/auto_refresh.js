var i = 0;
var interval = 800;

function auto_refresh() {
	interval = 800;
	var tb = window.document.getElementById('result-tab');
	var rows = tb.rows;
	for (var i=rows.length-1; i>0; i--) {
		var result = $(rows[i].cells[4].children[0]).attr("result");
		result=$(rows[i].cells[4]).find("span").attr("result");
		rows[i].cells[4].className = "td_result";
		var sid = parseInt(rows[i].cells[0].textContent.trim(), 10);

		if (result<4) {
			window.setTimeout(function() {
				fresh_result(sid);
			}, interval);

			console.log("auto_refresh "+sid+" actived!");
			break;
		}
	}
}

function findRow(solution_id) {
	var tb = window.document.getElementById('result-tab');
	var rows = tb.rows;

	for (var i=1; i<rows.length; i++) {

		var cell = rows[i].cells[0];

		var sid = parseInt(
			cell.textContent.trim(),
			10
		);

		if (sid === parseInt(solution_id, 10)) {
			return rows[i];
		}
	}

	return null;
}

function fresh_result(solution_id) {
	var xmlhttp;
	if (window.XMLHttpRequest) {// code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	}
	else {// code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}

	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState==4 && xmlhttp.status==200) {
			var tb = window.document.getElementById('result-tab');
			var row = findRow(solution_id);


			// 행을 찾지 못한 경우 JavaScript 오류 방지
			if (!row) {

				console.log(
					"fresh_result: row not found",
					solution_id
				);

				return;
			}
			var r = xmlhttp.responseText;

			var ra = r.split(",");
			ra[0] = parseInt(ra[0]);
			// alert(r);
			// alert(judge_result[r]);
			var loader = "<img width=18 src=image/loader.gif>";
			row.cells[5].innerHTML = ra[1];
			row.cells[6].innerHTML = ra[2];

			if (
				ra[3] != "none" &&
				row.cells.length > 10 &&
				row.cells[10]
			) {
				row.cells[10].innerHTML = ra[3];
			}

			if (ra[0]<4) {
				//console.log(loader);
				if (-1==row.cells[4].innerHTML.indexOf("loader")) {
					//console.log(row.cells[3].innerHTML);
			 		row.cells[4].innerHTML += loader;
				}
				interval *= 1.5;

				window.setTimeout(function() {
					fresh_result(solution_id);
				}, interval);
			}
			else {
				console.log("JUDGE FINISHED - RELOAD", solution_id, ra[0]);

				window.location.reload();
				return;
			}
		}
	}
	xmlhttp.open("GET","status-ajax.php?solution_id="+solution_id,true);
	xmlhttp.send();
}

var hj_ss = "<select class='http_judge form-control' length='2' name='result'>";

for (var i=0; i<10; i++) {
  hj_ss += "	<option value='"+i+"'>"+judge_result[i]+" </option>";
}

hj_ss += "</select>";
hj_ss += "<input name='manual' type='hidden'>";
hj_ss += "<input class='http_judge form-control' size=5 title='输入判定原因与提示' name='explain' type='text'>";
hj_ss += "<input type='button' class='http_judge btn' name='manual' value='确定' onclick='http_judge(this)' >";

$(".http_judge_form").append(hj_ss);

auto_refresh();

$(".td_result").mouseover(function () {
  //$(this).children(".btn").hide(300);
  $(this).find("form").show(600);
  var sid = $(this).find("span[class=original]").attr("sid");
  $(this).find("span[class=original]").load("status-ajax.php?q=user_id&solution_id="+sid);
});

$(".http_judge_form").hide();

function http_judge(btn) {
  var sid = $(btn).parent()[0].children[0].value;
  $.post("admin/problem_judge.php",$(btn).parent().serialize(),function(data,textStatus) {
    if(textStatus=="success")window.setTimeout("fresh_result("+sid+")",1000);
	})
  return false;
}
