<?php
include("template/$OJ_TEMPLATE/header.php");

$activity_add_escape = static function ($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$activity_add_has_type = array_key_exists('activity_type', $_GET);

$activity_add_cards = array(
    'material' => array(
        '학습 자료',
        '읽을 자료와 학습 내용을 안내합니다.',
        'book',
        'blue'
    ),
    'assignment' => array(
        '과제',
        '학생에게 수행할 과제 내용을 안내합니다.',
        'clipboard',
        'orange'
    ),
    'contest' => array(
        '코딩 실습',
        '대회 문제 가져오기, 문제 직접 선택, 기존 대회 연결 중에서 구성합니다.',
        'code',
        'green'
    ),
    'simulation' => array(
        '시뮬레이션',
        '직접 만들거나 등록된 문제를 가져와 구성합니다.',
        'play',
        'teal'
    ),
);
?>
<link rel="stylesheet"
      href="template/<?= $activity_add_escape($OJ_TEMPLATE) ?>/css/course.css">

<div class="course-page">
    <div class="course-page-header">
        <a class="ui small basic button"
           href="course_view.php?course_id=<?= (int)$course_id ?>">
            수업으로 돌아가기
        </a>
        <h1 class="ui header">학습 활동 추가</h1>
        <p class="course-page-description">
            <?= $activity_add_escape($view_course['course_name']) ?>
        </p>
    </div>

    <div class="ui segment">
        <h2 class="ui dividing header">
            <?= (int)$view_lesson['lesson_no'] ?>차시 —
            <?= $activity_add_escape($view_lesson['title']) ?>
        </h2>

        <?php if (!$activity_add_has_type): ?>

            <h3 class="ui header">어떤 활동을 추가할까요?</h3>
            <p>유형을 선택하면 해당 활동의 구성 화면으로 이동합니다.</p>

            <div class="ui stackable two column grid">
                <?php foreach ($view_activity_types as $type => $label): ?>
                    <?php if ($type === 'simulation_import') { continue; } ?>
                    <?php
                    $card = $activity_add_cards[$type] ?? array(
                        $label,
                        '선택한 유형의 활동을 구성합니다.',
                        'tasks',
                        'grey'
                    );

                    $url = 'course_activity_add.php?' .
                        http_build_query(array(
                            'lesson_id' => (string)$view_lesson['lesson_id'],
                            'activity_type' => (string)$type,
                        ));
                    ?>

                    <div class="column">
                        <a class="ui fluid card"
                           href="<?= $activity_add_escape($url) ?>">
                            <div class="content">
                                <i class="right floated large <?= $activity_add_escape($card[3]) ?> <?= $activity_add_escape($card[2]) ?> icon"
                                   aria-hidden="true"></i>

                                <div class="header">
                                    <?= $activity_add_escape($card[0]) ?>
                                </div>

                                <div class="description">
                                    <?= $activity_add_escape($card[1]) ?>
                                </div>
                            </div>
                            <div class="extra content">
                                선택하여 진행 →
                            </div>
                        </a>
                    </div>

                <?php endforeach; ?>
            </div>

        <?php elseif (
            $view_selected_activity_type === 'simulation' &&
            ($_GET['simulation_create'] ?? null) !== '1'
        ): ?>
            <a class="ui small basic button" href="course_activity_add.php?lesson_id=<?= (int)$view_lesson['lesson_id'] ?>">다른 활동 유형 선택</a>
            <h3 class="ui header">시뮬레이션 추가</h3>
            <p>시뮬레이션을 추가할 방법을 선택하세요.</p>
            <div class="ui stackable two column grid">
                <?php
                $simulation_methods = array(
                    array(
                        '직접 만들기',
                        '제목과 설명을 입력해 활동을 만든 뒤 기본 코드와 학생 편집·제출 설정을 구성합니다.',
                        'edit', 'teal', 'simulation'
                    ),
                    array(
                        '문제 가져오기',
                        '등록된 그림 문제나 자유 실습을 선택해 현재 차시에 가져옵니다.',
                        'download', 'purple', 'simulation_import'
                    ),
                );
                foreach ($simulation_methods as $method):
                    $params = array(
                        'lesson_id' => (string)$view_lesson['lesson_id'],
                        'activity_type' => $method[4],
                    );
                    if ($method[4] === 'simulation') {
                        $params['simulation_create'] = '1';
                    }
                    $url = 'course_activity_add.php?' . http_build_query($params);
                ?>
                    <div class="column">
                        <a class="ui fluid card" href="<?= $activity_add_escape($url) ?>">
                            <div class="content">
                                <i class="right floated large <?= $activity_add_escape($method[3]) ?> <?= $activity_add_escape($method[2]) ?> icon" aria-hidden="true"></i>
                                <div class="header"><?= $activity_add_escape($method[0]) ?></div>
                                <div class="description"><?= $activity_add_escape($method[1]) ?></div>
                            </div>
                            <div class="extra content">선택하여 진행 →</div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>

            <p>
                <a class="ui small basic button"
                   href="course_activity_add.php?lesson_id=<?= (int)$view_lesson['lesson_id'] ?>">
                    다른 활동 유형 선택
                </a>
            </p>

            <h3 class="ui header">
                <?= $activity_add_escape(
                    $view_activity_types[$view_selected_activity_type]
                ) ?>
            </h3>

            <div class="ui info message">
                활동은 비공개로 생성됩니다.
                내용을 확인한 뒤 학생에게 공개해 주세요.

                <?php if ($view_selected_activity_type === 'simulation'): ?>
                    <p>
                        활동을 생성한 뒤 시뮬레이션 편집 화면에서
                        기본 코드와 학생 편집·제출 설정을 구성합니다.
                    </p>
                <?php endif; ?>
            </div>

<form class="ui form" method="post" action="course_activity_create.php">
            <?php include('./csrf.php'); ?>
            <input type="hidden" name="course_id" value="<?php echo intval($course_id); ?>">
            <input type="hidden" name="lesson_id" value="<?php echo intval($view_lesson['lesson_id']); ?>">
            <input type="hidden" name="activity_type" value="<?php echo htmlspecialchars($view_selected_activity_type, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="required field">
                <label for="activity_title">활동 제목</label>
                <input type="text" id="activity_title" name="title" maxlength="255" placeholder="학생에게 보여줄 활동 제목" required>
            </div>
            <div class="field">
                <label for="activity_description">활동 설명</label>
                <textarea id="activity_description" name="description" rows="7" placeholder="학생에게 안내할 학습 내용과 활동 방법을 입력하세요."></textarea>
            </div>
            <button class="ui primary button" type="submit">학습 활동 생성</button>
            <a class="ui basic button" href="course_view.php?course_id=<?php echo intval($course_id); ?>">취소</a>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php include("template/$OJ_TEMPLATE/footer.php"); ?>
