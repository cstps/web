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
                <?php
                require(__DIR__ . "/../../include/set_post_key.php");
                ?>
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

                                        <?php if (!$is_course_performance_student) { ?>
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
                                        <?php } ?>


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
						? '3. 코드 수정 및 실행'
						: '3. 코드 작성 및 실행';
				}
				?>
			</strong>
		</div>
                <div class="submit-code-workspace">

                    <div class="submit-code-pane">



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

                    <div
                            id="submit-editor-resize"
                            class="submit-editor-resize"
                            title="드래그하여 코드 편집기 높이 조절"
                            aria-label="코드 편집기 높이 조절">
                            <span></span>
                    </div>

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

		                    </div>

                    <?php if (isset($OJ_TEST_RUN) && $OJ_TEST_RUN) { ?>

                        <div class="submit-run-pane">

                            <?php require(__DIR__ . "/submit-test-run.php"); ?>

                        </div>

                    <?php } ?>

                </div>
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
			enableBasicAutocompletion: false,
			enableSnippets: true,
			enableLiveAutocompletion: false,
			fontSize: "13pt", // font size 키우기

		});

            // ============================================================
            // ACE Editor 높이 수동 조절
            // - 기본 높이는 CSS에서 지정
            // - 아래 손잡이를 위/아래로 드래그하여 조절
            // - 새로고침하면 CSS 기본 높이로 복원
            // ============================================================
            (function () {

                    var resizeHandle =
                            document.getElementById(
                                    "submit-editor-resize"
                            );

                    var editorElement =
                            document.getElementById(
                                    "source"
                            );

                    if (
                            !resizeHandle ||
                            !editorElement
                    ) {
                            return;
                    }


                    var submitEditorResizeStartY = 0;
                    var submitEditorResizeStartHeight = 0;

                    var minHeight = 160;
                    var maxHeight = 900;



                    resizeHandle.addEventListener(
                            "pointerdown",
                            function (event) {

                                    submitEditorResizeStartY =
                                            event.clientY;

                                    submitEditorResizeStartHeight =
                                            editorElement.offsetHeight;

                                    resizeHandle.setPointerCapture(
                                            event.pointerId
                                    );

                                    event.preventDefault();
                            }
                    );


                    resizeHandle.addEventListener(
                            "pointermove",
                            function (event) {

                                    if (
                                            !resizeHandle.hasPointerCapture(
                                                    event.pointerId
                                            )
                                    ) {
                                            return;
                                    }

                                    var newHeight =
                                            submitEditorResizeStartHeight +
                                            (
                                                    event.clientY -
                                                    submitEditorResizeStartY
                                            );

                                    newHeight =
                                            Math.max(
                                                    minHeight,
                                                    Math.min(
                                                            maxHeight,
                                                            newHeight
                                                    )
                                            );

                                    editorElement.style.height =
                                            newHeight + "px";

                                    editor.resize();
                            }
                    );


                    function finishEditorResize(event) {

                            if (
                                    resizeHandle.hasPointerCapture(
                                            event.pointerId
                                    )
                            ) {
                                    resizeHandle.releasePointerCapture(
                                            event.pointerId
                                    );
                            }
                    }


                    resizeHandle.addEventListener(
                            "pointerup",
                            finishEditorResize
                    );

                    resizeHandle.addEventListener(
                            "pointercancel",
                            finishEditorResize
                    );

            })();

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
                <?php
                if (
                        isset($view_block_code_clipboard) &&
                        $view_block_code_clipboard
                ) {
                ?>
                        // ====================================================
                        // 수행모드 / Course 복붙금지모드 코드 복사·붙여넣기 차단
                        // ====================================================

                        (function() {
                                var editorElement =
                                        editor.container;

                                function blockClipboardEvent(event) {
                                        event.preventDefault();
                                        event.stopPropagation();

                                        if (
                                                typeof event.stopImmediatePropagation ===
                                                "function"
                                        ) {
                                                event.stopImmediatePropagation();
                                        }

                                        return false;
                                }

                                editorElement.addEventListener(
                                        "copy",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "cut",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "paste",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "contextmenu",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "dragstart",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "drop",
                                        blockClipboardEvent,
                                        true
                                );

                                editorElement.addEventListener(
                                        "beforeinput",
                                        function(event) {
                                                if (
                                                        event.inputType ===
                                                                "insertFromPaste" ||
                                                        event.inputType ===
                                                                "insertFromDrop"
                                                ) {
                                                        blockClipboardEvent(
                                                                event
                                                        );
                                                }
                                        },
                                        true
                                );

                                editorElement.addEventListener(
                                        "keydown",
                                        function(event) {
                                                var key =
                                                        (
                                                                event.key ||
                                                                ""
                                                        ).toLowerCase();

                                                var ctrlOrMeta =
                                                        event.ctrlKey ||
                                                        event.metaKey;

                                                // Ctrl/Cmd + C/X/V
                                                if (
                                                        ctrlOrMeta &&
                                                        (
                                                                key === "c" ||
                                                                key === "x" ||
                                                                key === "v"
                                                        )
                                                ) {
                                                        blockClipboardEvent(
                                                                event
                                                        );

                                                        return;
                                                }

                                                // Ctrl + Insert
                                                if (
                                                        event.ctrlKey &&
                                                        key === "insert"
                                                ) {
                                                        blockClipboardEvent(
                                                                event
                                                        );

                                                        return;
                                                }

                                                // Shift + Insert
                                                if (
                                                        event.shiftKey &&
                                                        key === "insert"
                                                ) {
                                                        blockClipboardEvent(
                                                                event
                                                        );
                                                }
                                        },
                                        true
                                );
                        })();

                <?php
                }
                ?>

		reloadtemplate($("#language").val());
	</script>
	<?php
	$pid_for_key = isset($id) ? $id : (isset($pid) ? $pid : 'unknown');
	$cid_prefix = isset($cid) ? "contest_" . $cid . "_" : "";

        // 서버 코드 자동저장 전용 CSRF 토큰
        // 일반 제출의 1회용 csrf와 분리하여 사용한다.
        if (
                !isset($_SESSION[$OJ_NAME.'_draft_csrf']) ||
                !is_string($_SESSION[$OJ_NAME.'_draft_csrf']) ||
                $_SESSION[$OJ_NAME.'_draft_csrf'] === ''
        ) {
                $_SESSION[$OJ_NAME.'_draft_csrf'] =
                        bin2hex(random_bytes(32));
        }

        $draft_csrf_token =
                $_SESSION[$OJ_NAME.'_draft_csrf'];

	?>
	<script>
		// ============================================================
		// 자동 저장 기능
		//
		// 브라우저 localStorage는 사용하지 않고 서버 draft만 사용한다.
		// ============================================================

		const isReadOnly =
			<?php
			echo (
				isset($view_source_readonly) &&
				$view_source_readonly
			)
				? 'true'
				: 'false';
			?>;


		const draftProblemId =
		        <?php echo intval($problem_id); ?>;

		const draftContestId =
		        <?php
		        echo isset($cid)
		                ? intval($cid)
		                : 0;
		        ?>;


		const draftCsrfToken =
		        <?php
		        echo json_encode(
		                $draft_csrf_token,
		                JSON_UNESCAPED_UNICODE |
		                JSON_UNESCAPED_SLASHES
		        );
		        ?>;


                // ============================================================
                // 서버 자동저장
                //
                // - 코드 입력이 2초 동안 멈추면 저장
                // - 계속 입력하는 경우에도 5초마다 최신 상태 확인
                // - 직전 저장 코드와 다를 때만 전송
                // - 읽기 전용 화면에서는 저장하지 않음
                // ============================================================

                let lastServerSavedCode = null;
                let serverDraftSaving = false;

                async function saveServerDraft() {

                        if (
                                isReadOnly ||
                                serverDraftSaving ||
                                typeof editor === "undefined"
                        ) {
                                return;
                        }

                        const code =
                                editor.getValue();

                        if (code === lastServerSavedCode) {
                                return;
                        }

                        const languageElement =
                                document.getElementById("language");

                        if (!languageElement) {
                                return;
                        }

                        const formData =
                                new FormData();

                        formData.set(
                                "problem_id",
                                String(draftProblemId)
                        );

                        formData.set(
                                "contest_id",
                                String(draftContestId)
                        );

                        formData.set(
                                "language",
                                String(languageElement.value)
                        );

                        formData.set(
                                "source",
                                code
                        );


                        formData.set(
                                "draft_csrf",
                                draftCsrfToken
                        );

                        const solutionForm =
                                document.getElementById("frmSolution");

                        if (solutionForm) {

                                const hiddenFields =
                                        solutionForm.querySelectorAll(
                                                "input[type='hidden']"
                                        );

                                hiddenFields.forEach((field) => {

                                        if (
                                                field.name &&
                                                !formData.has(field.name)
                                        ) {
                                                formData.set(
                                                        field.name,
                                                        field.value
                                                );
                                        }
                                });
                        }

                        serverDraftSaving = true;

                        try {

                                const response =
                                        await fetch(
                                                "student_code_draft_save.php",
                                                {
                                                        method: "POST",
                                                        body: formData,
                                                        credentials: "same-origin",
                                                        cache: "no-store"
                                                }
                                        );

                                const data =
                                        await response.json();

                                if (
                                        response.ok &&
                                        data.ok === true
                                ) {
                                        lastServerSavedCode =
                                                code;
                                }

                        }
                        catch (error) {

                                console.warn(
                                        "서버 코드 자동저장 실패",
                                        error
                                );

                        }
                        finally {

                                serverDraftSaving = false;
                        }
                }

                // ------------------------------------------------------------
                // 코드 변경 후 2초 동안 추가 입력이 없으면 저장
                // ------------------------------------------------------------
                let serverDraftDebounceTimer = null;

                if (
                        !isReadOnly &&
                        typeof editor !== "undefined"
                ) {

                        editor.getSession().on(
                                "change",
                                function() {

                                        if (serverDraftDebounceTimer !== null) {
                                                clearTimeout(
                                                        serverDraftDebounceTimer
                                                );
                                        }

                                        serverDraftDebounceTimer =
                                                setTimeout(
                                                        function() {
                                                                serverDraftDebounceTimer = null;
                                                                saveServerDraft();
                                                        },
                                                        2000
                                                );
                                }
                        );
                }

                // ------------------------------------------------------------
                // 계속 타이핑하여 debounce가 미뤄지는 경우를 위한 5초 보정
                // saveServerDraft() 내부에서 코드 변경 여부를 다시 검사한다.
                // ------------------------------------------------------------
                setInterval(
                        saveServerDraft,
                        5000
                );

	</script>

<?php } ?>

</body>

</html>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
