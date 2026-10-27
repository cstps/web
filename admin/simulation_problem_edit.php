<?php
require_once(__DIR__ . '/simulation-problem-init.php');
require_once(__DIR__ . '/../scripts/simulation_problem_store.php');
require_once(__DIR__ . '/../scripts/simulation_problem_csrf.php');
require_once(__DIR__ . '/../scripts/simulation_problem_basic_store.php');

function simulation_problem_form_escape($value)
{
    return htmlspecialchars(
        is_scalar($value) ? (string)$value : '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$values = array(
    'title' => '',
    'description' => '',
    'starter_code' => "function setup() {\n    createCanvas(400, 400);\n    background(255);\n}\n",
    'simulation_mode' => 'drawing',
    'canvas_width' => '400',
    'canvas_height' => '400',
    'allow_edit' => '1',
    'allow_submit' => '1',
    'sharing' => 'private',
    'published' => '0',
    'execution_profile' => 'p5js-2.3.4',
    'reference_source' => '',
    'scoring_mode' => 'whole_image',
    'position_tolerance' => '1',
    'max_score' => '100.00',
    'regions_json' => '[]',
);

$problem_id = null;
$base_version = '0';
$simulation_problem_form_error = '';
$fatal = false;
$simulation_problem_form_csrf_token = '';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$is_post = $method === 'POST';

try {
    if (!in_array($method, array('GET', 'POST'), true)) {
        header('Allow: GET, POST');
        throw new InvalidArgumentException('지원하지 않는 요청입니다.');
    }

    $raw_id = $is_post
        ? ($_POST['problem_id'] ?? null)
        : ($_GET['problem_id'] ?? '');

    if (
        !is_string($raw_id) ||
        (
            $raw_id !== '' &&
            (
                preg_match('/^[1-9][0-9]{0,18}$/D', $raw_id) !== 1 ||
                (
                    strlen($raw_id) === 19 &&
                    strcmp($raw_id, '9223372036854775807') > 0
                )
            )
        )
    ) {
        throw new InvalidArgumentException('문제 번호가 올바르지 않습니다.');
    }

    $problem_id = $raw_id === '' ? null : $raw_id;

    if ($is_post && $problem_id !== null) {
        if (
            !in_array(
                $_POST['expected_published'] ?? null,
                array('0', '1'),
                true
            ) ||
            !in_array(
                $_POST['expected_sharing'] ?? null,
                array('private', 'shared'),
                true
            )
        ) {
            throw new InvalidArgumentException(
                '기준 공개 설정이 올바르지 않습니다. 편집 화면을 다시 열어 주세요.'
            );
        }
    }

    if ($problem_id !== null) {
        $statement = simulation_problem_store_statement(
            $dbh,
            'SELECT problem_id, owner_user_id, sharing, published, status
             FROM simulation_problem WHERE problem_id = ?',
            array($problem_id)
        );
        $problem = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (
            !$problem ||
            simulation_problem_permissions(
                $simulation_admin_actor,
                $problem
            )['edit'] !== true
        ) {
            throw new UnexpectedValueException(
                '문제를 찾을 수 없거나 수정 권한이 없습니다.'
            );
        }
    }

    if ($is_post) {
        $base_version = $_POST['base_version'] ?? null;

        if (
            !is_string($base_version) ||
            preg_match('/^(0|[1-9][0-9]{0,9})$/D', $base_version) !== 1 ||
            (float)$base_version > 2147483647
        ) {
            throw new InvalidArgumentException('기준 버전이 올바르지 않습니다.');
        }

        foreach ($values as $key => $default) {
            $values[$key] = $_POST[$key] ?? '';
        }
    } elseif ($problem_id !== null) {
        $statement = simulation_problem_store_statement(
            $dbh,
            'SELECT v.*,
                    r.source_code AS reference_source,
                    r.grading_config AS reference_grading_config,
                    r.max_score AS reference_max_score
             FROM simulation_problem_version v
             LEFT JOIN simulation_problem_reference r
               ON r.version_id = v.version_id
             WHERE v.problem_id = ?
             ORDER BY v.version_no DESC
             LIMIT 1',
            array($problem_id)
        );
        $version = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (!$version) {
            throw new RuntimeException('저장된 문제 버전을 찾지 못했습니다.');
        }

        $execution = simulation_validate_execution_metadata($version, true);
        $base_version = (string)$version['version_no'];

        foreach (array(
            'title', 'description', 'starter_code', 'simulation_mode',
            'canvas_width', 'canvas_height', 'allow_edit', 'allow_submit'
        ) as $key) {
            $values[$key] = (string)$version[$key];
        }

        $values['sharing'] = $problem['sharing'];
        $values['published'] = (string)$problem['published'];
        $values['execution_profile'] =
            $execution['source_language'] === 'python'
                ? 'python-draw-poc-v1'
                : 'p5js-2.3.4';

        if ($version['reference_source'] !== null) {
            $grading = json_decode(
                $version['reference_grading_config'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($grading)) {
                throw new RuntimeException('채점 설정이 올바르지 않습니다.');
            }

            $values['reference_source'] = $version['reference_source'];
            $values['max_score'] = (string)$version['reference_max_score'];
            $values['scoring_mode'] = $grading['scoring_mode'] ?? 'metrics_only';
            $values['position_tolerance'] = (string)(
                $grading['position_tolerance_px'] ?? 0
            );
            $values['regions_json'] = json_encode(
                $grading['regions'] ?? array(),
                JSON_UNESCAPED_UNICODE |
                JSON_PRETTY_PRINT |
                JSON_THROW_ON_ERROR
            );
        }
    }
} catch (UnexpectedValueException $exception) {
    http_response_code(403);
    $simulation_problem_form_error = $exception->getMessage();
    $fatal = true;
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    $simulation_problem_form_error = $exception->getMessage();
    $fatal = true;
} catch (Throwable $exception) {
    error_log('[simulation_problem_form] ' . $exception->getMessage());
    http_response_code(500);
    $simulation_problem_form_error = '문제 편집 화면을 불러오지 못했습니다.';
    $fatal = true;
}

if (!$fatal) {
    $context = ($problem_id === null ? 'new' : 'edit:' . $problem_id)
        . ':' . $base_version
        . ($problem_id === null ? '' : ':' .
            ($is_post
                ? $_POST['expected_published']
                : (string)$problem['published']) . ':' .
            ($is_post
                ? $_POST['expected_sharing']
                : $problem['sharing']));

    if ($is_post) {
        try {
            if (!simulation_problem_csrf_consume(
                $_POST['simulation_problem_csrf'] ?? null,
                $context
            )) {
                throw new UnexpectedValueException(
                    '보안 토큰이 만료됐거나 유효하지 않습니다. 입력 내용을 확인한 뒤 다시 저장해 주세요.'
                );
            }

            $save_action = $_POST['save_action'] ?? 'version';

            if (
                !is_string($save_action) ||
                !in_array($save_action, array('version', 'basic'), true)
            ) {
                throw new InvalidArgumentException('저장 작업이 올바르지 않습니다.');
            }

            if ($save_action === 'basic') {
                $saved = simulation_store_problem_basic(
                    $dbh,
                    $values + array(
                        'expected_published' => $_POST['expected_published'] ?? null,
                        'expected_sharing' => $_POST['expected_sharing'] ?? null,
                    ),
                    $problem_id,
                    $base_version
                );
            } else {
                $saved = simulation_store_problem_version(
                    $dbh, $values, $problem_id, $base_version, true
                );
            }

            // 저장 함수가 커밋을 완료한 뒤 처리기에 알린다.
            // 알림 실패는 이미 저장된 문제 버전의 성공 여부에 영향을 주지 않는다.
            try {
                $bank_notify_errno = 0;
                $bank_notify_error = '';
                $bank_notify_socket = @stream_socket_client(
                    'unix:///run/1024kr-simulation-grade/notify.sock',
                    $bank_notify_errno,
                    $bank_notify_error,
                    0.05,
                    STREAM_CLIENT_CONNECT
                );

                if ($bank_notify_socket !== false) {
                    fclose($bank_notify_socket);
                } else {
                    error_log(
                        '[simulation_problem_save] 처리기 알림 실패. ' .
                        '문제 버전과 이미지 요청은 보존됨. 오류 번호: ' .
                        $bank_notify_errno
                    );
                }
            } catch (Throwable $bank_notify_exception) {
                error_log(
                    '[simulation_problem_save] 처리기 알림 처리 실패. ' .
                    '문제 버전과 이미지 요청은 보존됨.'
                );
            }

            header(
                'Location: simulation_problem_edit.php?problem_id=' .
                rawurlencode($saved['problem_id']) .
                ($save_action === 'basic'
                    ? '&basic_saved=' . rawurlencode($saved['change'])
                    : '&saved=1'),
                true,
                303
            );
            exit;
        } catch (UnexpectedValueException $exception) {
            http_response_code(403);
            $simulation_problem_form_error = $exception->getMessage();
        } catch (SimulationProblemVersionConflict $exception) {
            http_response_code(409);
            $simulation_problem_form_error = $exception->getMessage();
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $simulation_problem_form_error = $exception->getMessage();
        } catch (JsonException $exception) {
            http_response_code(422);
            $simulation_problem_form_error = '영역 설정의 JSON 형식을 확인해 주세요.';
        } catch (Throwable $exception) {
            error_log('[simulation_problem_save] ' . $exception->getMessage());
            http_response_code(500);
            $simulation_problem_form_error = '저장하지 못했습니다. 입력 내용을 보관한 뒤 권한과 문제 상태를 확인해 주세요.';
        }
    }

    $simulation_problem_form_csrf_token = simulation_problem_csrf_issue($context);
}


$simulation_problem_saved_image = null;

if (!$fatal && $problem_id !== null && $base_version !== '0') {
    try {
        require_once(__DIR__ . '/../scripts/simulation_problem_image_view.php');

        $statement = simulation_problem_store_statement(
            $dbh,
            'SELECT CAST(version_id AS CHAR)
             FROM simulation_problem_version
             WHERE problem_id = ? AND version_no = ?',
            array($problem_id, $base_version)
        );
        $simulation_saved_version_id = $statement->fetchColumn();
        $statement->closeCursor();

        if ($simulation_saved_version_id !== false) {
            $simulation_problem_saved_image = simulation_problem_image_view(
                $dbh,
                $simulation_admin_actor,
                $simulation_saved_version_id
            );
        }
    } catch (Throwable $exception) {
        error_log('[simulation_problem_saved_image] ' . $exception->getMessage());
        $simulation_problem_saved_image = array(
            'state' => 'unavailable',
            'label' => '저장된 목표 이미지 상태를 확인하지 못했습니다.',
        );
    }
}

$admin_page_title = $problem_id === null ? '그림 문제 추가' : '그림 문제 수정';
$admin_active_menu = $problem_id === null
    ? 'simulation_problem_add'
    : 'simulation_problem_list';

require(__DIR__ . '/admin-layout-start.php');
$simulation_problem_form_ready = true;
require(__DIR__ . '/simulation_problem_form.php');
require(__DIR__ . '/admin-layout-end.php');
