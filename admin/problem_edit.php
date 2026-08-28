<?php
require_once("../include/db_info.inc.php");
require_once("../include/const.inc.php");
require_once("admin-header.php");
require_once("../include/my_func.inc.php");

if (
  !(
    isset($_SESSION[$OJ_NAME . '_administrator']) ||
    isset($_SESSION[$OJ_NAME . '_problem_editor'])
  )
) {
  echo "<a href='../loginpage.php'>Please Login First!</a>";
  exit(1);
}

include_once("kindeditor.php");
?>

<html>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>문제 수정</title>
</head>

<body>

  <div class="admin-page">

    <?php
    if (isset($_GET['id'])) {

      $id = intval($_GET['id']);

      $sql = "
        SELECT *
        FROM `problem`
        WHERE `problem_id` = ?
    ";

      $result = pdo_query(
        $sql,
        $id
      );

      if (
        !is_array($result) ||
        count($result) === 0
      ) {
        echo "
            <div class='admin-card'>
                존재하지 않는 문제입니다.
            </div>
        ";
        exit;
      }

      $row = $result[0];

      $sqltmp = "
        SELECT `user_id`
        FROM `privilege`
        WHERE `rightstr` = ?
          AND `defunct` = 'N'
        LIMIT 1
    ";

      $resulttmp = pdo_query(
        $sqltmp,
        "p" . $id
      );

      $creator_value =
        (
          is_array($resulttmp) &&
          count($resulttmp) > 0
        )
        ? $resulttmp[0]['user_id']
        : '';

      $allow_reuse =
        isset($row['allow_reuse'])
        ? intval($row['allow_reuse'])
        : 1;

      // front / rear / ban 코드가 하나라도 있으면 수정 화면에서 자동 펼침
      $has_code_template =
        trim(
          isset($row['front_code'])
            ? (string)$row['front_code']
            : ''
        ) !== ''
        ||
        trim(
          isset($row['rear_code'])
            ? (string)$row['rear_code']
            : ''
        ) !== ''
        ||
        trim(
          isset($row['ban_code'])
            ? (string)$row['ban_code']
            : ''
        ) !== '';
    ?>

      <div class="admin-page-header">

        <div>
          <h1 class="admin-page-title">
            문제 수정
          </h1>

          <div class="admin-page-description">
            문제 #<?php echo $id; ?>의 내용과 채점 조건, 문제 재사용 정책을 수정합니다.
          </div>
        </div>

        <div class="admin-page-header-actions">

          <a
            href="../problem.php?id=<?php echo $id; ?>"
            class="admin-btn admin-btn-secondary"
            target="_blank">
            문제 보기
          </a>

          <a
            href="problem_list.php"
            class="admin-btn admin-btn-secondary">
            문제 목록
          </a>

        </div>

      </div>


      <form
        id="problemEdit"
        action="problem_edit.php"
        method="post"
        onsubmit="do_submit()">

        <input
          type="hidden"
          name="problem_id"
          value="<?php echo $id; ?>">


        <!-- =====================================================
             1. 기본 정보
             ===================================================== -->

        <div class="admin-form-card">

          <div class="admin-form-card-header">

            <span class="admin-form-step">
              1
            </span>

            <div>
              <div class="admin-form-card-title">
                기본 정보
              </div>

              <div class="admin-form-card-desc">
                문제 제목과 실행 제한을 수정합니다.
              </div>
            </div>

          </div>


          <div class="admin-form-field">

            <label class="admin-form-label">
              <?php echo $MSG_TITLE; ?>
            </label>

            <div class="admin-form-id-title">

              <span class="admin-problem-id-label">
                #<?php echo $id; ?>
              </span>

              <input
                class="admin-form-input"
                type="text"
                name="title"
                value="<?php
                        echo htmlspecialchars(
                          $row['title'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?>"
                required>

            </div>

          </div>


          <div class="admin-form-grid-2">

            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_Time_Limit; ?>
              </label>

              <div class="admin-form-unit">

                <input
                  class="admin-form-input"
                  type="number"
                  min="0.001"
                  max="300"
                  step="0.001"
                  name="time_limit"
                  value="<?php
                          echo htmlspecialchars(
                            $row['time_limit'],
                            ENT_QUOTES,
                            'UTF-8'
                          );
                          ?>"
                  required>

                <span class="admin-form-unit-label">
                  sec
                </span>

              </div>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_Memory_Limit; ?>
              </label>

              <div class="admin-form-unit">

                <input
                  class="admin-form-input"
                  type="number"
                  min="1"
                  max="1024"
                  step="1"
                  name="memory_limit"
                  value="<?php
                          echo htmlspecialchars(
                            $row['memory_limit'],
                            ENT_QUOTES,
                            'UTF-8'
                          );
                          ?>"
                  required>

                <span class="admin-form-unit-label">
                  MB
                </span>

              </div>

            </div>

          </div>

        </div>


        <!-- =====================================================
             2. 문제 내용
             ===================================================== -->

        <div class="admin-form-card">

          <div class="admin-form-card-header">

            <span class="admin-form-step">
              2
            </span>

            <div>
              <div class="admin-form-card-title">
                문제 내용
              </div>

              <div class="admin-form-card-desc">
                학생에게 표시되는 문제 설명과 입출력 예제를 수정합니다.
              </div>
            </div>

          </div>


          <div class="admin-form-field">

            <label class="admin-form-label">
              <?php echo $MSG_Description; ?>
            </label>

            <textarea
              class="kindeditor"
              rows="13"
              name="description"
              cols="80"><?php
                        echo htmlspecialchars(
                          $row['description'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?></textarea>

          </div>


          <div class="admin-form-field">

            <label class="admin-form-label">
              <?php echo $MSG_Input; ?>
            </label>

            <textarea
              class="kindeditor"
              rows="13"
              name="input"
              cols="80"><?php
                        echo htmlspecialchars(
                          $row['input'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?></textarea>

          </div>


          <div class="admin-form-field">

            <label class="admin-form-label">
              <?php echo $MSG_Output; ?>
            </label>

            <textarea
              class="kindeditor"
              rows="13"
              name="output"
              cols="80"><?php
                        echo htmlspecialchars(
                          $row['output'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?></textarea>

          </div>


          <div class="admin-form-grid-2">

            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_Sample_Input; ?>
              </label>

              <textarea
                class="admin-form-textarea admin-code-textarea"
                rows="9"
                name="sample_input"><?php
                                    echo htmlspecialchars(
                                      $row['sample_input'],
                                      ENT_QUOTES,
                                      'UTF-8'
                                    );
                                    ?></textarea>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_Sample_Output; ?>
              </label>

              <textarea
                class="admin-form-textarea admin-code-textarea"
                rows="9"
                name="sample_output"><?php
                                      echo htmlspecialchars(
                                        $row['sample_output'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                      );
                                      ?></textarea>

            </div>

          </div>


          <div class="admin-form-field">

            <label class="admin-form-label">
              <?php echo $MSG_HINT; ?>
            </label>

            <textarea
              class="kindeditor"
              rows="13"
              name="hint"
              cols="80"><?php
                        echo htmlspecialchars(
                          $row['hint'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?></textarea>

          </div>

        </div>


        <!-- =====================================================
             3. 채점 및 문제 관리
             ===================================================== -->

        <div class="admin-form-card">

          <div class="admin-form-card-header">

            <span class="admin-form-step">
              3
            </span>

            <div>
              <div class="admin-form-card-title">
                채점 및 문제 관리
              </div>

              <div class="admin-form-card-desc">
                채점 방식과 문제의 사용 정책을 수정합니다.
              </div>
            </div>

          </div>


          <div class="admin-form-grid-2">

            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_SPJ; ?>
              </label>

              <div class="admin-choice-group">

                <label class="admin-choice">

                  <input
                    type="radio"
                    name="spj"
                    value="0"
                    <?php
                    echo intval($row['spj']) === 0
                      ? 'checked'
                      : '';
                    ?>>

                  <span>
                    사용 안 함
                  </span>

                </label>


                <label class="admin-choice">

                  <input
                    type="radio"
                    name="spj"
                    value="1"
                    <?php
                    echo intval($row['spj']) === 1
                      ? 'checked'
                      : '';
                    ?>>

                  <span>
                    사용
                  </span>

                </label>

              </div>

              <div class="admin-form-help">
                <?php echo $MSG_HELP_SPJ; ?>
              </div>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                다른 대회에서 문제 사용
              </label>

              <div class="admin-choice-group">

                <label class="admin-choice">

                  <input
                    type="radio"
                    name="allow_reuse"
                    value="1"
                    <?php
                    echo $allow_reuse === 1
                      ? 'checked'
                      : '';
                    ?>>

                  <span>
                    재사용 허용
                  </span>

                </label>


                <label class="admin-choice">

                  <input
                    type="radio"
                    name="allow_reuse"
                    value="0"
                    <?php
                    echo $allow_reuse === 0
                      ? 'checked'
                      : '';
                    ?>>

                  <span>
                    재사용 제한
                  </span>

                </label>

              </div>

              <div class="admin-form-help">
                재사용 제한 시 다른 사용자가 이 문제를 새로운 대회나
                수업 차시에 추가할 수 없습니다.
              </div>

            </div>

          </div>


          <div class="admin-form-grid-2">

            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_SOURCE; ?>
              </label>

              <input
                class="admin-form-input"
                type="text"
                name="source"
                value="<?php
                        echo htmlspecialchars(
                          $row['source'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?>"
                placeholder="예: 정보올림피아드//수행평가">

              <div class="admin-form-help">
                여러 출처는 // 로 구분합니다.
              </div>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_Creator; ?>
              </label>

              <textarea
                class="admin-form-textarea"
                name="creator"
                rows="2"><?php
                          echo htmlspecialchars(
                            $creator_value,
                            ENT_QUOTES,
                            'UTF-8'
                          );
                          ?></textarea>

            </div>

          </div>


          <div class="admin-form-field admin-form-field-small">

            <label class="admin-form-label">
              <?php echo $MSG_PRO_POINT; ?>
            </label>

            <div class="admin-form-unit admin-form-unit-small">

              <input
                class="admin-form-input"
                type="number"
                min="1"
                max="300"
                step="1"
                name="pro_point"
                value="<?php
                        echo intval(
                          $row['pro_point']
                        );
                        ?>">

              <span class="admin-form-unit-label">
                점
              </span>

            </div>

          </div>

        </div>


        <!-- =====================================================
             4. 코드 템플릿 및 제한
             ===================================================== -->

        <div class="admin-form-card">

          <div class="admin-form-card-header">

            <span class="admin-form-step">
              4
            </span>

            <div>
              <div class="admin-form-card-title">
                코드 템플릿 및 제한
              </div>

              <div class="admin-form-card-desc">
                함수 작성형 문제 또는 특정 코드 사용 제한이 필요한 경우 설정합니다.
              </div>
            </div>

          </div>


          <button
            type="button"
            class="admin-code-toggle"
            id="codeTemplateToggle"
            onclick="toggleCodeTemplate()"
            aria-expanded="<?php
                            echo $has_code_template
                              ? 'true'
                              : 'false';
                            ?>"
            aria-controls="codeTemplateContent">
            <span id="codeTemplateToggleText">
              <?php
              echo $has_code_template
                ? '코드 템플릿 설정 접기'
                : '코드 템플릿 설정 펼치기';
              ?>
            </span>

            <span id="codeTemplateArrow">
              <?php
              echo $has_code_template
                ? '▲'
                : '▼';
              ?>
            </span>
          </button>


          <div
            id="codeTemplateContent"
            class="admin-code-template-content <?php
                                                echo $has_code_template
                                                  ? 'open'
                                                  : '';
                                                ?>">

            <div class="admin-form-field">

              <label class="admin-form-label">
                앞 코드 (Front Code)
              </label>

              <div class="admin-form-help">
                학생이 작성한 코드 앞에 자동으로 추가되는 코드입니다.
                언어별 구분은 기존 HUSTOJ 형식인
                <code>//언어명//</code>을 사용합니다.
              </div>

              <?php
              if ($OJ_ACE_EDITOR) {
              ?>

                <pre
                  class="admin-code-editor"
                  id="front_code"><?php
                                  echo htmlspecialchars(
                                    isset($row['front_code'])
                                      ? $row['front_code']
                                      : '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                  );
                                  ?></pre>

                <input
                  type="hidden"
                  id="front_code_source"
                  name="front_code"
                  value="">

              <?php
              } else {
              ?>

                <textarea
                  class="admin-form-textarea admin-code-textarea"
                  rows="10"
                  id="front_code"
                  name="front_code"><?php
                                    echo htmlspecialchars(
                                      isset($row['front_code'])
                                        ? $row['front_code']
                                        : '',
                                      ENT_QUOTES,
                                      'UTF-8'
                                    );
                                    ?></textarea>

              <?php
              }
              ?>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                뒤 코드 (Rear Code)
              </label>

              <div class="admin-form-help">
                학생이 작성한 코드 뒤에 자동으로 추가되는 코드입니다.
              </div>

              <?php
              if ($OJ_ACE_EDITOR) {
              ?>

                <pre
                  class="admin-code-editor"
                  id="rear_code"><?php
                                  echo htmlspecialchars(
                                    isset($row['rear_code'])
                                      ? $row['rear_code']
                                      : '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                  );
                                  ?></pre>

                <input
                  type="hidden"
                  id="rear_code_source"
                  name="rear_code"
                  value="">

              <?php
              } else {
              ?>

                <textarea
                  class="admin-form-textarea admin-code-textarea"
                  rows="10"
                  id="rear_code"
                  name="rear_code"><?php
                                    echo htmlspecialchars(
                                      isset($row['rear_code'])
                                        ? $row['rear_code']
                                        : '',
                                      ENT_QUOTES,
                                      'UTF-8'
                                    );
                                    ?></textarea>

              <?php
              }
              ?>

            </div>


            <div class="admin-form-field">

              <label class="admin-form-label">
                <?php echo $MSG_BAN_CODE; ?>
              </label>

              <input
                class="admin-form-input"
                type="text"
                name="ban_code"
                value="<?php
                        echo htmlspecialchars(
                          isset($row['ban_code'])
                            ? $row['ban_code']
                            : '',
                          ENT_QUOTES,
                          'UTF-8'
                        );
                        ?>"
                placeholder="예: for/if">

              <div class="admin-form-help">
                여러 금지 코드는 / 로 구분해서 입력합니다.
              </div>

            </div>

          </div>

        </div>


        <div class="admin-form-actions">

          <a
            href="problem_list.php"
            class="admin-btn admin-btn-secondary">
            취소
          </a>

          <?php
          require_once("../include/set_post_key.php");
          ?>

          <button
            type="submit"
            name="submit"
            class="admin-btn admin-btn-primary">
            <?php echo $MSG_SAVE; ?>
          </button>

        </div>

      </form>


    <?php
    } else {

      require_once("../include/check_post_key.php");

      $id = intval(
        $_POST['problem_id']
      );

      if (
        !(
          isset(
            $_SESSION[$OJ_NAME . "_p$id"]
          ) ||
          isset(
            $_SESSION[$OJ_NAME . '_administrator']
          ) ||
          isset(
            $_SESSION[$OJ_NAME . '_problem_editor']
          )
        )
      ) {
        exit();
      }


      $title = isset($_POST['title'])
        ? $_POST['title']
        : '';

      $title = str_replace(
        ",",
        "&#44;",
        $title
      );


      $time_limit =
        isset($_POST['time_limit'])
        ? $_POST['time_limit']
        : 1;

      $memory_limit =
        isset($_POST['memory_limit'])
        ? $_POST['memory_limit']
        : 128;


      $description =
        isset($_POST['description'])
        ? $_POST['description']
        : '';

      $description =
        str_replace(
          "<p>",
          "",
          $description
        );

      $description =
        str_replace(
          "</p>",
          "<br />",
          $description
        );

      $description =
        str_replace(
          ",",
          "&#44;",
          $description
        );


      $input =
        isset($_POST['input'])
        ? $_POST['input']
        : '';

      $input =
        str_replace(
          "<p>",
          "",
          $input
        );

      $input =
        str_replace(
          "</p>",
          "<br />",
          $input
        );

      $input =
        str_replace(
          ",",
          "&#44;",
          $input
        );


      $output =
        isset($_POST['output'])
        ? $_POST['output']
        : '';

      $output =
        str_replace(
          "<p>",
          "",
          $output
        );

      $output =
        str_replace(
          "</p>",
          "<br />",
          $output
        );

      $output =
        str_replace(
          ",",
          "&#44;",
          $output
        );


      $sample_input =
        isset($_POST['sample_input'])
        ? $_POST['sample_input']
        : '';

      $sample_output =
        isset($_POST['sample_output'])
        ? $_POST['sample_output']
        : '';

      if ($sample_input === '') {
        $sample_input = "\n";
      }

      if ($sample_output === '') {
        $sample_output = "\n";
      }


      $hint =
        isset($_POST['hint'])
        ? $_POST['hint']
        : '';

      $hint =
        str_replace(
          "<p>",
          "",
          $hint
        );

      $hint =
        str_replace(
          "</p>",
          "<br />",
          $hint
        );

      $hint =
        str_replace(
          ",",
          "&#44;",
          $hint
        );


      $source =
        isset($_POST['source'])
        ? $_POST['source']
        : '';

      $creator =
        isset($_POST['creator'])
        ? trim($_POST['creator'])
        : '';

      $spj =
        isset($_POST['spj'])
        ? intval($_POST['spj'])
        : 0;


      // 앞뒤 코드는 원문을 보존하고 줄바꿈 방식만 LF로 통일한다.
      $front_code =
        isset($_POST['front_code'])
        ? $_POST['front_code']
        : '';

      $rear_code =
        isset($_POST['rear_code'])
        ? $_POST['rear_code']
        : '';

      $front_code =
        str_replace(
          array("\r\n", "\r"),
          "\n",
          $front_code
        );

      $rear_code =
        str_replace(
          array("\r\n", "\r"),
          "\n",
          $rear_code
        );


      $ban_code =
        isset($_POST['ban_code'])
        ? $_POST['ban_code']
        : '';

      $pro_point =
        isset($_POST['pro_point'])
        ? intval($_POST['pro_point'])
        : 1;


      // ----------------------------------------------------------
      // 문제 재사용 정책
      // ----------------------------------------------------------

      $allow_reuse_raw =
        isset($_POST['allow_reuse'])
        ? (string)$_POST['allow_reuse']
        : '';

      if (
        !in_array(
          $allow_reuse_raw,
          array('0', '1'),
          true
        )
      ) {
        echo "Invalid allow_reuse value.";
        exit(1);
      }

      $allow_reuse =
        intval($allow_reuse_raw);


      $description =
        RemoveXSS(
          $description
        );

      $input =
        RemoveXSS(
          $input
        );

      $output =
        RemoveXSS(
          $output
        );

      $hint =
        RemoveXSS(
          $hint
        );

      $ban_code =
        RemoveXSS(
          $ban_code
        );


      $basedir =
        $OJ_DATA . "/" . $id;


      if (
        $sample_input &&
        file_exists(
          $basedir . "/sample.in"
        )
      ) {

        $fp =
          fopen(
            $basedir . "/sample.in",
            "w"
          );

        fputs(
          $fp,
          preg_replace(
            "(\r\n)",
            "\n",
            $sample_input
          )
        );

        fclose(
          $fp
        );


        $fp =
          fopen(
            $basedir . "/sample.out",
            "w"
          );

        fputs(
          $fp,
          preg_replace(
            "(\r\n)",
            "\n",
            $sample_output
          )
        );

        fclose(
          $fp
        );
      }


      $sql = "
        UPDATE `problem`
        SET
            `title` = ?,
            `time_limit` = ?,
            `memory_limit` = ?,
            `description` = ?,
            `input` = ?,
            `output` = ?,
            `sample_input` = ?,
            `sample_output` = ?,
            `hint` = ?,
            `source` = ?,
            `spj` = ?,
            `in_date` = NOW(),
            `front_code` = ?,
            `rear_code` = ?,
            `ban_code` = ?,
            `pro_point` = ?,
            `allow_reuse` = ?
        WHERE `problem_id` = ?
    ";

      @pdo_query(
        $sql,
        $title,
        $time_limit,
        $memory_limit,
        $description,
        $input,
        $output,
        $sample_input,
        $sample_output,
        $hint,
        $source,
        $spj,
        $front_code,
        $rear_code,
        $ban_code,
        $pro_point,
        $allow_reuse,
        $id
      );


      // 출제자 정보 수정
      if ($creator !== '') {

        $sql_creator = "
            UPDATE `privilege`
            SET `user_id` = ?
            WHERE `rightstr` = ?
        ";

        @pdo_query(
          $sql_creator,
          $creator,
          "p" . $id
        );
      }


      echo "
        <div class='admin-card'>

            <h3 style='margin-top:0;'>
                문제 수정 완료
            </h3>

            <p>
                문제 #"
        . intval($id) .
        " 수정이 완료되었습니다.
            </p>

            <div class='admin-form-actions'>

                <a
                    class='admin-btn admin-btn-primary'
                    href='../problem.php?id="
        . intval($id) .
        "'
                    target='_blank'
                >
                    문제 보기
                </a>

                <a
                    class='admin-btn admin-btn-secondary'
                    href='problem_list.php'
                >
                    문제 목록
                </a>

            </div>

        </div>
    ";
    }
    ?>

  </div>


  <script>
    function toggleCodeTemplate() {

      var content =
        document.getElementById(
          "codeTemplateContent"
        );

      var button =
        document.getElementById(
          "codeTemplateToggle"
        );

      var text =
        document.getElementById(
          "codeTemplateToggleText"
        );

      var arrow =
        document.getElementById(
          "codeTemplateArrow"
        );

      if (!content) {
        return;
      }

      var isOpen =
        content.classList.contains(
          "open"
        );

      if (isOpen) {

        content.classList.remove(
          "open"
        );

        if (text) {
          text.textContent =
            "코드 템플릿 설정 펼치기";
        }

        if (arrow) {
          arrow.textContent = "▼";
        }

        if (button) {
          button.setAttribute(
            "aria-expanded",
            "false"
          );
        }

      } else {

        content.classList.add(
          "open"
        );

        if (text) {
          text.textContent =
            "코드 템플릿 설정 접기";
        }

        if (arrow) {
          arrow.textContent = "▲";
        }

        if (button) {
          button.setAttribute(
            "aria-expanded",
            "true"
          );
        }

        // display:none 상태에서 초기화된 ACE 편집기의 폭 재계산
        window.setTimeout(
          function() {

            if (
              typeof(editorFrontCode) !==
              "undefined"
            ) {
              editorFrontCode.resize();
            }

            if (
              typeof(editorRearCode) !==
              "undefined"
            ) {
              editorRearCode.resize();
            }
          },
          0
        );
      }
    }


    function do_submit() {

      if (
        typeof(editorFrontCode) !== "undefined"
      ) {
        $("#front_code_source").val(
          editorFrontCode.getValue()
        );
      }

      if (
        typeof(editorRearCode) !== "undefined"
      ) {
        $("#rear_code_source").val(
          editorRearCode.getValue()
        );
      }

      document.getElementById("problemEdit").target = "_self";
    }
  </script>


  <?php
  if (
    isset($OJ_ACE_EDITOR) &&
    $OJ_ACE_EDITOR &&
    isset($_GET['id'])
  ) {
  ?>
    <script src="../ace/ace.js"></script>
    <script src="../ace/ext-language_tools.js"></script>

    <script>
      ace.require("../ace/ext/language_tools");

      var editorFrontCode =
        ace.edit("front_code");

      editorFrontCode.setTheme(
        "ace/theme/chrome"
      );

      editorFrontCode.session.setMode(
        "ace/mode/c_cpp"
      );

      editorFrontCode.setOptions({
        enableSnippets: true,
        enableLiveAutocompletion: false
      });


      var editorRearCode =
        ace.edit("rear_code");

      editorRearCode.setTheme(
        "ace/theme/chrome"
      );

      editorRearCode.session.setMode(
        "ace/mode/c_cpp"
      );

      editorRearCode.setOptions({
        enableSnippets: true,
        enableLiveAutocompletion: false
      });

      if (
        document
        .getElementById(
          "codeTemplateContent"
        )
        .classList.contains(
          "open"
        )
      ) {

        window.setTimeout(
          function() {
            editorFrontCode.resize();
            editorRearCode.resize();
          },
          0
        );
      }
    </script>
  <?php
  }
  ?>

</body>

</html>