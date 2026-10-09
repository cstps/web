<?php
require_once("../include/db_info.inc.php");
require_once("../include/const.inc.php");
require_once("../include/my_func.inc.php");
require_once("../include/permission_functions.inc.php");
require_once("../include/code_template_functions.inc.php");


$request_problem_id = 0;

if (isset($_POST['problem_id'])) {
  $request_problem_id =
    intval($_POST['problem_id']);
} elseif (isset($_GET['id'])) {
  $request_problem_id =
    intval($_GET['id']);
}

if ($request_problem_id <= 0) {
  http_response_code(400);
  exit("올바른 문제 번호가 필요합니다.");
}

if (!oj_is_logged_in()) {
  http_response_code(401);
  exit("로그인이 필요합니다.");
}

if (!oj_can_manage_problem($request_problem_id)) {
  http_response_code(403);
  exit("본인이 생성한 문제만 수정할 수 있습니다.");
}

// p{pid} 권한 사용자가 관리자 공통 화면을 사용할 수 있도록 허용
$admin_page_access_allowed = true;

?>

<!DOCTYPE html>
<html lang="ko">

<head>
  <?php require_once("admin-header.php"); ?>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>문제 수정</title>
  <?php include_once("tinymce.php"); ?>

  <style>
    .admin-template-language-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
      margin-bottom: 16px;
    }

    .admin-template-language-tab {
      border: 1px solid #d0d7de;
      background: #ffffff;
      border-radius: 6px;
      padding: 8px 14px;
      cursor: pointer;
      font-size: 14px;
    }

    .admin-template-language-tab.active {
      font-weight: 600;
      border-color: #0969da;
      background: #f0f6ff;
    }

    .admin-template-language-panel {
      display: none;
    }

    .admin-template-language-panel.active {
      display: block;
    }
  </style>

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


      // ----------------------------------------------------------
      // 코드 템플릿 편집용 활성 언어 목록
      // ----------------------------------------------------------

      $enabled_template_languages =
        oj_get_enabled_template_languages(
          $language_name,
          $language_ext,
          $OJ_LANGMASK
        );


      // ----------------------------------------------------------
      // problem_template에서 현재 문제의 언어별 Front/Rear 조회
      // ----------------------------------------------------------

      $problem_templates =
        oj_get_problem_templates(
          $id
        );

      if ($problem_templates === false) {
        http_response_code(500);

        exit(
          '언어별 코드 템플릿 정보를 불러오지 못했습니다.'
        );
      }


      // ----------------------------------------------------------
      // 편집 화면에서 사용할 언어별 기본값 구성
      // ----------------------------------------------------------

      $template_editor_values =
        array();

      foreach (
        $enabled_template_languages
        as $language_id => $language_label
      ) {
        $language_id =
          intval($language_id);

        $template_editor_values[$language_id] =
          array(
            'lang' =>
              (string)$language_label,

            'front' =>
              isset(
                $problem_templates[$language_id]['front']
              )
                ? (string)$problem_templates[$language_id]['front']
                : '',

            'rear' =>
              isset(
                $problem_templates[$language_id]['rear']
              )
                ? (string)$problem_templates[$language_id]['rear']
                : ''
          );
      }


      $creator_value =
        isset($row['creator'])
        ? (string)$row['creator']
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
              class="tinymce-editor"
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
              class="tinymce-editor"
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
              class="tinymce-editor"
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
              class="tinymce-editor"
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
                언어별 코드 템플릿
              </label>

              <div class="admin-form-help">
                현재 사이트에서 활성화된 언어별로
                Front Code와 Rear Code를 관리합니다.
              </div>

              <div class="admin-template-language-tabs">

                <?php
                $template_language_index = 0;

                foreach (
                  $template_editor_values
                  as $language_id => $template_value
                ) {
                ?>

                  <button
                    type="button"
                    class="admin-template-language-tab <?php
                      echo $template_language_index === 0
                        ? 'active'
                        : '';
                    ?>"
                    data-template-language="<?php
                      echo intval($language_id);
                    ?>">
                    <?php
                    echo htmlspecialchars(
                      $template_value['lang'],
                      ENT_QUOTES,
                      'UTF-8'
                    );
                    ?>
                  </button>

                <?php
                  $template_language_index++;
                }
                ?>

              </div>


              <?php
              $template_language_index = 0;

              foreach (
                $template_editor_values
                as $language_id => $template_value
              ) {
                $language_id =
                  intval($language_id);
              ?>

                <div
                  class="admin-template-language-panel <?php
                    echo $template_language_index === 0
                      ? 'active'
                      : '';
                  ?>"
                  data-template-panel="<?php echo $language_id; ?>">

                  <div class="admin-form-field">

                    <label class="admin-form-label">
                      <?php
                      echo htmlspecialchars(
                        $template_value['lang'],
                        ENT_QUOTES,
                        'UTF-8'
                      );
                      ?>
                      Front Code
                    </label>

                    <textarea
                      class="admin-form-textarea admin-code-textarea"
                      rows="8"
                      name="template_front[<?php echo $language_id; ?>]"><?php
                        echo htmlspecialchars(
                          $template_value['front'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                      ?></textarea>

                  </div>


                  <div class="admin-form-field">

                    <label class="admin-form-label">
                      <?php
                      echo htmlspecialchars(
                        $template_value['lang'],
                        ENT_QUOTES,
                        'UTF-8'
                      );
                      ?>
                      Rear Code
                    </label>

                    <textarea
                      class="admin-form-textarea admin-code-textarea"
                      rows="8"
                      name="template_rear[<?php echo $language_id; ?>]"><?php
                        echo htmlspecialchars(
                          $template_value['rear'],
                          ENT_QUOTES,
                          'UTF-8'
                        );
                      ?></textarea>

                  </div>

                </div>

              <?php
                $template_language_index++;
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
        ? trim((string)$_POST['creator'])
        : '';

      $creator_length = function_exists('mb_strlen')
        ? mb_strlen($creator, 'UTF-8')
        : strlen($creator);

      if ($creator_length > 200) {
        http_response_code(400);
        exit('표시용 출제자 이름은 200자 이하여야 합니다.');
      }

      $spj =
        isset($_POST['spj'])
        ? intval($_POST['spj'])
        : 0;


      // ----------------------------------------------------------
      // 언어별 코드 템플릿
      //
      // problem_template을 원본으로 사용한다.
      // 비활성 언어의 기존 템플릿은 수정 시 삭제하지 않고 보존한다.
      // ----------------------------------------------------------

      $enabled_template_languages =
        oj_get_enabled_template_languages(
          $language_name,
          $language_ext,
          $OJ_LANGMASK
        );

      $existing_templates =
        oj_get_problem_templates(
          $id
        );

      if ($existing_templates === false) {
        http_response_code(500);
        exit(
          '기존 언어별 코드 템플릿 정보를 불러오지 못했습니다.'
        );
      }

      $templates =
        array(
          'front' => array(),
          'rear' => array()
        );


      // 비활성 언어를 포함한 기존 템플릿을 우선 보존한다.
      foreach (
        $existing_templates
        as $language_id => $template
      ) {
        $language_id =
          intval($language_id);

        $lang =
          isset($template['lang'])
          ? (string)$template['lang']
          : (
              isset($language_name[$language_id])
              ? (string)$language_name[$language_id]
              : ''
            );

        foreach (
          array('front', 'rear')
          as $kind
        ) {
          $content =
            isset($template[$kind])
            ? (string)$template[$kind]
            : '';

          if ($content === '') {
            continue;
          }

          $templates[$kind][$language_id] =
            array(
              'language_id' => $language_id,
              'lang' => $lang,
              'kind' => $kind,
              'content' =>
                oj_normalize_source_newlines(
                  $content
                )
            );
        }
      }


      // 활성 언어는 현재 편집 화면에서 넘어온 값으로 교체한다.
      foreach (
        $enabled_template_languages
        as $language_id => $language_label
      ) {
        $language_id =
          intval($language_id);

        foreach (
          array('front', 'rear')
          as $kind
        ) {
          $post_name =
            'template_' . $kind;

          $content =
            isset($_POST[$post_name]) &&
            is_array($_POST[$post_name]) &&
            isset($_POST[$post_name][$language_id])
              ? (string)$_POST[$post_name][$language_id]
              : '';

          $content =
            oj_normalize_source_newlines(
              $content
            );

          // 빈 값이면 해당 활성 언어 템플릿을 제거한다.
          unset(
            $templates[$kind][$language_id]
          );

          if ($content === '') {
            continue;
          }

          $templates[$kind][$language_id] =
            array(
              'language_id' => $language_id,
              'lang' => (string)$language_label,
              'kind' => $kind,
              'content' => $content
            );
        }
      }


      // 기존 HUSTOJ 코드와 FPS 호환을 위한 legacy 복제본 생성
      $legacy_templates =
        oj_build_legacy_problem_templates(
          $templates,
          $language_name
        );

      $front_code =
        $legacy_templates['front'];

      $rear_code =
        $legacy_templates['rear'];


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

      $problem_update_result =
        pdo_query(
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

      if ($problem_update_result === false) {
        http_response_code(500);
        exit('문제 정보를 수정하지 못했습니다.');
      }

      $template_sync_result =
        oj_save_problem_templates(
          $id,
          $templates
        );

      if ($template_sync_result === false) {
        http_response_code(500);

        exit('문제 기본 정보는 수정되었지만 ' .
          '언어별 코드 템플릿 동기화에 실패했습니다.');
      }

      // 표시용 출제자 정보만 수정한다. p{pid} 권한은 변경하지 않는다.
      $creator_update_result =
        pdo_query(
          "UPDATE problem
           SET creator = ?
           WHERE problem_id = ?",
          $creator,
          $id
        );

      if ($creator_update_result === false) {
        http_response_code(500);
        exit('문제 출제자 정보를 수정하지 못했습니다.');
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

      }
    }


    function do_submit() {

      if (
        typeof(window.tinymce) !== "undefined"
      ) {
        window.tinymce.triggerSave();
      }

      document.getElementById("problemEdit").target = "_self";
    }
  </script>





  <script>
    document.addEventListener(
      "DOMContentLoaded",
      function () {

        var tabs =
          document.querySelectorAll(
            ".admin-template-language-tab"
          );

        var panels =
          document.querySelectorAll(
            ".admin-template-language-panel"
          );

        tabs.forEach(function (tab) {

          tab.addEventListener(
            "click",
            function () {

              var languageId =
                this.getAttribute(
                  "data-template-language"
                );

              tabs.forEach(function (item) {
                item.classList.remove("active");
              });

              panels.forEach(function (panel) {
                panel.classList.remove("active");
              });

              this.classList.add("active");

              var target =
                document.querySelector(
                  '.admin-template-language-panel' +
                  '[data-template-panel="' +
                  languageId +
                  '"]'
                );

              if (target) {
                target.classList.add("active");
              }
            }
          );
        });
      }
    );
  </script>

</body>

</html>