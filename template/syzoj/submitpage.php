<?php $show_title = "제출 - $OJ_NAME"; ?>
<?php include("template/$OJ_TEMPLATE/header.php"); ?>

<link
	rel="stylesheet"
	href="template/<?php echo $OJ_TEMPLATE; ?>/css/submitpage.css">

<script src="<?php echo $OJ_CDN_URL ?>include/checksource.js"></script>


<div class="submit-page">

	<form
		id="frmSolution"
		action="submit.php"
		method="post">
		<div class="submit-header">

			<div class="submit-problem-info">

				<?php if (isset($id)) { ?>

					<div class="submit-problem-title">
						문제 <?php echo intval($id); ?>
					</div>

					<input
						id="problem_id"
						type="hidden"
						value="<?php echo intval($id); ?>"
						name="id">

				<?php } else { ?>

					<div class="submit-problem-title">
						문제 <?php echo chr($pid + ord('A')); ?>
					</div>

					<div class="submit-contest-info">
						대회 #<?php echo intval($cid); ?>
					</div>

					<input
						id="cid"
						type="hidden"
						value="<?php echo intval($cid); ?>"
						name="cid">

					<input
						id="pid"
						type="hidden"
						value="<?php echo intval($pid); ?>"
						name="pid">

				<?php } ?>

			</div>


			<div
				id="language_span"
				class="submit-language">

				<label for="language">
					제출 언어
				</label>

				<select
					id="language"
					name="language"
					onchange="reloadtemplate($(this).val());">
					<?php
					$lang_count = count($language_ext);
					if (isset($_GET['langmask']))
						$langmask = $_GET['langmask'];
					else
						$langmask = $OJ_LANGMASK;
					$lang = (~((int)$langmask)) & ((1 << ($lang_count)) - 1);

					// $lastlang은 submitpage.php 본체에서 결정된 값을 우선 사용
					// 값이 없는 경우에만 cookie 또는 기본값 사용
					if (!isset($lastlang)) {

						if (isset($_COOKIE['lastlang'])) {
							$lastlang = intval($_COOKIE['lastlang']);
						} else {
							$lastlang = 0;
						}
					} else {
						$lastlang = intval($lastlang);
					}

					for ($i = 0; $i < $lang_count; $i++) {
						if ($lang & (1 << $i))
							echo "<option value=$i " . ($lastlang == $i ? "selected" : "") . ">
" . $language_name[$i] . "
</option>";
					}
					?>
				</select>


				<?php if ($OJ_VCODE) { ?>

					<div class="submit-vcode">

						<label>
							<?php echo $MSG_VCODE; ?>
						</label>

						<input
							name="vcode"
							size="4"
							type="text">

						<img
							id="vcode"
							alt="인증코드 변경"
							src="vcode.php"
							onclick="this.src='vcode.php?'+Math.random()">

					</div>

				<?php } ?>

			</div>

		</div>
		<?php if (isset($view_process_mode) && $view_process_mode) { ?>

			<div class="submit-process-card">

				<h3 class="submit-section-title">
					문제 해결 과정
				</h3>


				<?php if (!isset($view_is_resubmit) || !$view_is_resubmit) { ?>

					<!-- =================================================
					첫 제출
					================================================= -->

					<div class="submit-process-section">

						<label class="submit-process-label">
							<strong>1. 풀이 계획</strong>
						</label>

						<p class="submit-help-text">
							코드를 작성하기 전에 문제를 어떻게 해결할지 작성하세요.
						</p>

						<textarea
							name="plan_text"
							id="plan_text"
							rows="4"
							class="submit-process-textarea"
							placeholder="예: 반복문을 이용하여 입력된 값을 하나씩 확인한다."
							required></textarea>

						<input
							type="hidden"
							name="reflection"
							value="">

					</div>


				<?php } else { ?>

					<!-- =================================================
					재제출
					================================================= -->

					<div class="submit-history-card">

						<strong class="submit-history-title">
							직전 제출 결과
						</strong>

						<br><br>

						<?php
						echo htmlentities(
							$view_previous_result_text,
							ENT_QUOTES,
							"UTF-8"
						);
						?>

						<?php
						if (
							isset($view_previous_reflection) &&
							trim($view_previous_reflection) !== ''
						) {
						?>

							<div class="submit-history-detail">

								<strong>
									직전 수정 메모
								</strong>

								<div class="submit-history-text">

									<?php
									echo nl2br(
										htmlentities(
											$view_previous_reflection,
											ENT_QUOTES,
											"UTF-8"
										)
									);
									?>

								</div>

							</div>

						<?php
						}
						?>

					</div>


					<div class="submit-history-card">

						<strong class="submit-history-title">
							처음 세운 풀이 계획
						</strong>

						<br><br>

						<?php

						if (
							isset($view_previous_plan_text) &&
							$view_previous_plan_text != ""
						) {

							echo nl2br(
								htmlentities(
									$view_previous_plan_text,
									ENT_QUOTES,
									"UTF-8"
								)
							);
						} else {

							echo "<span style='color:#999;'>기록된 풀이 계획이 없습니다.</span>";
						}

						?>

					</div>


					<div class="submit-process-section">

						<label class="submit-process-label">
							<strong>1. 이번에 수정한 부분</strong>
						</label>

						<p class="submit-help-text">
							수정한 부분을 선택하세요. 여러 개 선택할 수 있습니다.
						</p>

						<div class="submit-choice-group">

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
							class="submit-process-input"
							maxlength="100"
							placeholder="예: 출력 문자열 오타 수정">


						<!-- 재제출에서는 최초 풀이계획을 새로 저장하지 않음 -->
						<input
							type="hidden"
							name="plan_text"
							value="">

					</div>


				<?php } ?>




				<!-- =====================================================
		생성형 AI 활용
		첫 제출 / 재제출 공통
		===================================================== -->

				<div class="submit-process-section">

					<label class="submit-process-label">
						<strong>
							<?php
							echo (isset($view_is_resubmit) && $view_is_resubmit)
								? "2. 이번 수정에서 생성형 AI를 활용했나요?"
								: "2. 생성형 AI를 활용했나요?";
							?>
						</strong>
					</label>

					<p class="submit-help-text">
						가장 가까운 활용 방법 하나를 선택하세요.
					</p>

					<div class="submit-choice-group">

						<label>
							<input
								type="radio"
								name="ai_usage_choice"
								value="none"
								checked>
							사용하지 않음
						</label>

						<label>
							<input
								type="radio"
								name="ai_usage_choice"
								value="idea">
							힌트·아이디어
						</label>

						<label>
							<input
								type="radio"
								name="ai_usage_choice"
								value="syntax">
							문법 도움
						</label>

						<label>
							<input
								type="radio"
								name="ai_usage_choice"
								value="debug">
							오류 수정
						</label>

						<label>
							<input
								type="radio"
								name="ai_usage_choice"
								value="generate">
							코드 생성
						</label>

					</div>


					<!-- submit.php의 기존 구조와 호환하기 위한 hidden 값 -->

					<input
						type="hidden"
						name="ai_used"
						id="ai_used"
						value="0">

					<input
						type="hidden"
						name="ai_usage_type"
						id="ai_usage_type"
						value="none">

				</div>


				<!-- =====================================================
			AI 질문 - 선택사항
			===================================================== -->

				<div
					id="ai_prompt_area"
					class="submit-ai-prompt-area"
					style="display:none;">

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
						class="submit-process-input"
						placeholder="예: 반복문 범위를 어떻게 고쳐야 하는지 질문함">

				</div>

			</div>

		<?php } ?>


		<div class="submit-code-header">

			<strong>
				<?php
				if (
					isset($view_source_readonly) &&
					$view_source_readonly
				) {

					echo '코드 보기';
				} else {

					echo (
						isset($view_is_resubmit) &&
						$view_is_resubmit
					)
						? '3. 코드 수정'
						: '3. 코드 작성';
				}
				?>
			</strong>
		</div>


		<?php if ($OJ_ACE_EDITOR) { ?>

			<pre
				id="source"
				class="submit-code-editor"><?php
											echo htmlentities(
												$view_src,
												ENT_QUOTES,
												"UTF-8"
											);
											?></pre>

			<br>

			<input
				type="hidden"
				id="hide_source"
				name="source"
				value="">

		<?php } else { ?>

			<textarea
				id="source"
				name="source"
				class="submit-source-textarea"><?php
												echo htmlentities(
													$view_src,
													ENT_QUOTES,
													"UTF-8"
												);
												?></textarea>

		<?php } ?>

		<?php if (isset($OJ_TEST_RUN) && $OJ_TEST_RUN) { ?>
			<?php echo $MSG_Input ?>:<textarea style="width:30%" cols=40 rows=5 id="input_text" name="input_text"><?php echo $view_sample_input ?></textarea>
			<?php echo $MSG_Output ?>:
			<textarea style="width:30%" cols=10 rows=5 id="out" name="out" disabled="true">SHOULD BE:
<?php echo $view_sample_output ?>
</textarea>
			<br>
		<?php } ?>
		<?php
		if (
			!isset($view_source_readonly) ||
			!$view_source_readonly
		) {
		?>

			<div class="submit-actions">

				<button
					type="submit"
					class="ui primary labeled icon button">

					<i class="ui edit icon"></i>
					제출

				</button>

			</div>

		<?php
		}
		?>
		<?php
		if (
			(
				!isset($view_source_readonly) ||
				!$view_source_readonly
			) &&
			isset($OJ_ENCODE_SUBMIT) &&
			$OJ_ENCODE_SUBMIT
		) {
		?>
			<input class="btn btn-success" title="WAF gives you reset ? try this." type=button value="Encoded <?php echo $MSG_SUBMIT ?>" onclick="encoded_submit();">
			<input type=hidden id="encoded_submit_mark" name="reverse2" value="reverse" />
		<?php
		}
		?>
	</form>

</div>


<script>

	function encoded_submit() {

		var mark = "<?php echo isset($id) ? 'problem_id' : 'cid'; ?>";
		var problem_id = document.getElementById(mark);

		if (typeof(editor) != "undefined")
			$("#hide_source").val(editor.getValue());
		if (mark == 'problem_id')
			problem_id.value = '<?php if (isset($id)) echo $id ?>';
		else
			problem_id.value = '<?php if (isset($cid)) echo $cid ?>';

		document.getElementById("frmSolution").target = "_self";
		document.getElementById("encoded_submit_mark").name = "encoded_submit";
		var source = $("#source").val();
		if (typeof(editor) != "undefined") {
			source = editor.getValue();
			$("#hide_source").val(encode64(utf16to8(source)));
		} else {
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
		function(event) {


			// ACE Editor 사용 시 실제 코드를 hidden input에 저장
			if (typeof(editor) != "undefined") {

				var hideSource =
					document.getElementById("hide_source");

				if (hideSource) {
					hideSource.value = editor.getValue();
				}
			}

			// 문제 ID / 대회 ID 확인
			var mark =
				"<?php echo isset($id) ? 'problem_id' : 'cid'; ?>";

			var problem_id =
				document.getElementById(mark);

			if (problem_id) {

				if (mark == "problem_id") {

					problem_id.value =
						"<?php if (isset($id)) echo $id ?>";

				} else {

					problem_id.value =
						"<?php if (isset($cid)) echo $cid ?>";

				}
			}

			// 일반 제출
			document.getElementById("frmSolution").target = "_self";

			// 여기서 submit()을 다시 호출하지 않는다.
			// 브라우저가 원래 submit 동작을 계속 진행함
		}
	);



	function switchLang(lang) {
		var langnames = new Array("c_cpp", "c_cpp", "pascal", "java", "ruby", "sh", "python", "php", "perl", "csharp", "objectivec", "vbscript", "scheme", "c_cpp", "c_cpp", "lua", "javascript", "golang");
		editor.getSession().setMode("ace/mode/" + langnames[lang]);

	}

	function reloadtemplate(lang) {
		//console.log("lang="+lang);
		document.cookie = "lastlang=" + lang + "; path=/";
		var url = window.location.href;
		var i = url.indexOf("sid=");
		if (i != -1) url = url.substring(0, i - 1);
		//  if(confirm("<?php echo  $MSG_LOAD_TEMPLATE_CONFIRM ?>"))
		//       document.location.href=url;
		if (
			typeof editor !== "undefined"
		) {
			switchLang(lang);
		}
	}

	// ============================================================
	// 생성형 AI 활용 선택
	// ============================================================

	function updateAIUsage() {

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


		if (!selected)
			return;


		if (selected.value === "none") {

			aiUsed.value = "0";
			aiUsageType.value = "none";

			if (promptArea)
				promptArea.style.display = "none";

			if (prompt)
				prompt.value = "";

		} else {

			aiUsed.value = "1";
			aiUsageType.value = selected.value;

			if (promptArea)
				promptArea.style.display = "block";

		}

	}


	document.addEventListener(
		"DOMContentLoaded",
		function() {

			var choices =
				document.querySelectorAll(
					'input[name="ai_usage_choice"]'
				);

			for (var i = 0; i < choices.length; i++) {

				choices[i].addEventListener(
					"change",
					updateAIUsage
				);

			}

			updateAIUsage();

		}
	);
</script>
<script language="Javascript" type="text/javascript" src="<?php echo $OJ_CDN_URL ?>include/base64.js"></script>
<?php if ($OJ_ACE_EDITOR) { ?>
	<script src="<?php echo $OJ_CDN_URL ?>ace/ace.js"></script>
	<script src="<?php echo $OJ_CDN_URL ?>ace/ext-language_tools.js"></script>
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
		<?php
		if (
			isset($view_source_readonly) &&
			$view_source_readonly
		) {
		?>

			editor.setReadOnly(true);

		<?php
		}
		?>
		reloadtemplate($("#language").val());
	</script>
	<?php
	$pid_for_key = isset($id) ? $id : (isset($pid) ? $pid : 'unknown');
	$cid_prefix = isset($cid) ? "contest_" . $cid . "_" : "";
	?>
	<script>
		// ============================================================
		// 자동 저장 기능
		//
		// 일반 작성과 과거 제출 Edit의 자동저장을 분리한다.
		// ============================================================

		const isEditMode =
			<?php
			echo isset($_GET['sid'])
				? 'true'
				: 'false';
			?>;


		const isReadOnly =
			<?php
			echo (
				isset($view_source_readonly) &&
				$view_source_readonly
			)
				? 'true'
				: 'false';
			?>;


		const baseLocalKey =
			"autosave_code_<?php
							echo $cid_prefix . $pid_for_key;
							?>";


		const editSolutionId =
			<?php
			echo isset($_GET['sid'])
				? intval($_GET['sid'])
				: 0;
			?>;


		const localKey =
			isEditMode ?
			baseLocalKey +
			"_edit_" +
			editSolutionId :
			baseLocalKey;


		const savedCode =
			localStorage.getItem(localKey);


		if (
			!isReadOnly &&
			savedCode &&
			typeof editor !== "undefined"
		) {
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
					const editorElement =
						document.getElementById("source");

					if (editorElement) {
						editorElement.before(notice);
					}
				}
			} else {
				localStorage.removeItem(localKey);
				localStorage.removeItem(localKey + "_time");
			}
		}

		// 자동 저장: 5초마다
		setInterval(() => {

			if (
				!isReadOnly &&
				typeof editor !== "undefined"
			) {

				const code =
					editor.getValue();

				localStorage.setItem(
					localKey,
					code
				);

				localStorage.setItem(
					localKey + "_time",
					Date.now()
				);
			}

		}, 5000);

		// 제출 시 삭제
		const solutionForm = document.getElementById("frmSolution");

		if (solutionForm) {
			solutionForm.addEventListener("submit", () => {
				localStorage.removeItem(localKey);
				localStorage.removeItem(localKey + "_time");
			});
		}
	</script>

<?php } ?>

</body>

</html>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>