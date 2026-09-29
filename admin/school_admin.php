<?php
require_once(__DIR__ . "/admin-init.php");

if (!oj_can_manage_admin_users()) {
    http_response_code(403);
    exit("학교 목록을 관리할 권한이 없습니다.");
}

$school_path = __DIR__ . "/../school_list.json";

if (!is_readable($school_path)) {
    http_response_code(500);
    exit("학교 목록 파일을 읽을 수 없습니다.");
}

$school_json = file_get_contents($school_path);

if ($school_json === false) {
    http_response_code(500);
    exit("학교 목록 파일을 읽을 수 없습니다.");
}

$school_list = json_decode($school_json, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($school_list)
) {
    http_response_code(500);
    exit("학교 목록 파일의 형식이 올바르지 않습니다.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        !isset($_POST["do"]) ||
        $_POST["do"] !== "add"
    ) {
        http_response_code(400);
        $message = "<div class='alert alert-danger'>올바르지 않은 요청입니다.</div>";
    } else {
        require_once(__DIR__ . "/../include/check_post_key.php");

        $input = isset($_POST["new_school"])
            ? $_POST["new_school"]
            : null;

        if (!is_string($input)) {
            http_response_code(400);
            $message = "<div class='alert alert-danger'>학교명을 입력해 주세요.</div>";
        } else {
            $new_school = trim($input);

            if (
                $new_school === "" ||
                strlen($new_school) > 100 ||
                preg_match("//u", $new_school) !== 1 ||
                preg_match('/[<>\x00-\x1F\x7F]/u', $new_school) !== 0
            ) {
                http_response_code(400);
                $message = "<div class='alert alert-danger'>학교명을 확인해 주세요. 100바이트 이하로 입력하고 HTML 기호와 제어 문자는 제외해야 합니다.</div>";
            } else {
                $new_key = preg_replace(
                    "/[^a-zA-Z0-9가-힣]/u",
                    "",
                    $new_school
                );
                $new_key = mb_strtolower($new_key, "UTF-8");

                if ($new_key === "") {
                    http_response_code(400);
                    $message = "<div class='alert alert-danger'>학교명에 글자나 숫자를 입력해 주세요.</div>";
                } else {
                    $handle = @fopen($school_path, "r+");

                    if ($handle === false) {
                        http_response_code(500);
                        $message = "<div class='alert alert-danger'>학교 목록 파일을 열 수 없습니다.</div>";
                    } elseif (!flock($handle, LOCK_EX)) {
                        fclose($handle);
                        http_response_code(500);
                        $message = "<div class='alert alert-danger'>학교 목록 파일을 잠글 수 없습니다.</div>";
                    } else {
                        $original = stream_get_contents($handle);
                        $current = $original === false
                            ? null
                            : json_decode($original, true);

                        if (
                            $original === false ||
                            json_last_error() !== JSON_ERROR_NONE ||
                            !is_array($current)
                        ) {
                            http_response_code(500);
                            $message = "<div class='alert alert-danger'>학교 목록 파일이 올바르지 않아 저장하지 않았습니다.</div>";
                        } else {
                            $duplicate = false;
                            $invalid_list = false;

                            foreach ($current as $existing) {
                                if (
                                    !is_string($existing) ||
                                    trim($existing) === ""
                                ) {
                                    $invalid_list = true;
                                    break;
                                }

                                $existing_key = preg_replace(
                                    "/[^a-zA-Z0-9가-힣]/u",
                                    "",
                                    $existing
                                );

                                if (
                                    mb_strtolower($existing_key, "UTF-8")
                                    === $new_key
                                ) {
                                    $duplicate = true;
                                }
                            }

                            if ($invalid_list) {
                                http_response_code(500);
                                $message = "<div class='alert alert-danger'>학교 목록에 잘못된 항목이 있어 저장하지 않았습니다.</div>";
                            } elseif ($duplicate) {
                                $message = "<div class='alert alert-info'>이미 등록된 학교입니다.</div>";
                            } else {
                                $current[] = $new_school;
                                sort($current, SORT_STRING);

                                $encoded = json_encode(
                                    array_values($current),
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_PRETTY_PRINT
                                );

                                $write_all = function ($stream, $data) {
                                    rewind($stream);
                                    $offset = 0;
                                    $length = strlen($data);

                                    while ($offset < $length) {
                                        $count = fwrite(
                                            $stream,
                                            substr($data, $offset)
                                        );

                                        if ($count === false || $count === 0) {
                                            return false;
                                        }

                                        $offset += $count;
                                    }

                                    return true;
                                };

                                $saved = $encoded !== false
                                    && $write_all($handle, $encoded)
                                    && ftruncate($handle, strlen($encoded))
                                    && fflush($handle);

                                if ($saved) {
                                    $school_list = $current;
                                    $message = "<div class='alert alert-success'>학교를 추가했습니다.</div>";
                                } else {
                                    if ($original !== false) {
                                        $write_all($handle, $original);
                                        ftruncate($handle, strlen($original));
                                        fflush($handle);
                                    }

                                    http_response_code(500);
                                    $message = "<div class='alert alert-danger'>저장에 실패했습니다. 학교 목록 파일을 확인해 주세요.</div>";
                                }
                            }
                        }

                        flock($handle, LOCK_UN);
                        fclose($handle);
                    }
                }
            }
        }
    }
}
?>


<?php
$admin_page_title = "학교 관리";
$admin_active_menu = "school_admin";

require_once(__DIR__ . "/admin-layout-start.php");
?>

<div class="admin-page">
  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">학교 관리</h1>
      <div class="admin-page-description">
        등록된 학교 <?php echo number_format(count($school_list)); ?>개를
        검색하거나 새 학교를 추가합니다.
      </div>
    </div>
  </div>

  <?php if (isset($message)) { ?>
    <div class="admin-card" role="status">
      <?php
      echo htmlspecialchars(
          strip_tags($message),
          ENT_QUOTES,
          "UTF-8"
      );
      ?>
    </div>
  <?php } ?>

  <div class="admin-form-card">
    <div class="admin-form-card-header">
      <span class="admin-form-step">1</span>
      <div>
        <div class="admin-form-card-title">학교 검색</div>
        <div class="admin-form-card-desc">
          현재 등록된 학교명을 확인합니다.
        </div>
      </div>
    </div>

    <div class="admin-form-field">
      <label class="admin-form-label" for="school-search">
        학교명
      </label>
      <input
        type="search"
        id="school-search"
        class="admin-form-input"
        placeholder="학교명 일부를 입력하세요"
        autocomplete="off">
      <div id="search-result" aria-live="polite"></div>
    </div>
  </div>

  <form action="school_admin.php" method="post">
    <div class="admin-form-card">
      <div class="admin-form-card-header">
        <span class="admin-form-step">2</span>
        <div>
          <div class="admin-form-card-title">학교 추가</div>
          <div class="admin-form-card-desc">
            회원가입과 사용자 정보 수정에서 사용할 학교명을 추가합니다.
          </div>
        </div>
      </div>

      <div class="admin-form-field">
        <label class="admin-form-label" for="new_school">
          새 학교명
        </label>
        <input
          type="text"
          name="new_school"
          id="new_school"
          class="admin-form-input"
          placeholder="예: 서울고등학교"
          required>
        <div class="admin-form-help">
          100바이트 이하로 입력해 주세요.
        </div>
      </div>
    </div>

    <div class="admin-form-actions">
      <?php
      require_once(__DIR__ . "/../include/set_post_key.php");
      ?>
      <button
        type="submit"
        name="do"
        value="add"
        class="admin-btn admin-btn-primary">
        학교 추가
      </button>
    </div>
  </form>
</div>

<script>
  const schoolList = <?php
    echo json_encode(
        $school_list,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );
  ?>;

  const searchInput = document.getElementById("school-search");
  const resultEl = document.getElementById("search-result");

  searchInput.addEventListener("input", function () {
    const keyword = searchInput.value.trim().toLocaleLowerCase();
    resultEl.textContent = "";

    if (keyword === "") {
      return;
    }

    let matchCount = 0;
    const visibleMatches = [];

    schoolList.forEach(function (school) {
      if (school.toLocaleLowerCase().includes(keyword)) {
        matchCount += 1;

        if (visibleMatches.length < 30) {
          visibleMatches.push(school);
        }
      }
    });

    if (matchCount === 0) {
      resultEl.textContent = "목록에 없습니다.";
      return;
    }

    const summary = document.createElement("div");
    summary.textContent = matchCount + "개 일치";
    resultEl.appendChild(summary);

    const list = document.createElement("ul");

    visibleMatches.forEach(function (school) {
      const item = document.createElement("li");
      item.textContent = school;
      list.appendChild(item);
    });

    resultEl.appendChild(list);

    if (matchCount > visibleMatches.length) {
      const remaining = document.createElement("div");
      remaining.textContent =
        "처음 " + visibleMatches.length + "개만 표시합니다.";
      resultEl.appendChild(remaining);
    }
  });
</script>

<?php
require_once(__DIR__ . "/admin-layout-end.php");
?>
