<?php
$show_title = $view_title;

function drawproblemset_escape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function drawproblemset_page_url($page)
{
    global $view_problem_scope, $view_problem_search;

    return '/drawproblemset.php?' . http_build_query(array(
        'scope' => $view_problem_scope,
        'q' => $view_problem_search,
        'page' => $page,
    ));
}

include('template/' . $OJ_TEMPLATE . '/header.php');
?>

<div class="ui main container"
     style="margin-top:2rem;margin-bottom:3rem;">

    <div class="ui segment">

        <h2 class="ui header">
            <i class="paint brush icon"></i>
            <div class="content">
                그림 문제 목록
                <div class="sub header">
                    그림 그리기와 시뮬레이션 문제를 확인합니다.
                </div>
            </div>
        </h2>

        <form class="ui form" method="get" action="/drawproblemset.php">

            <div class="fields">

                <div class="four wide field">
                    <label for="drawproblemset-scope">
                        문제 구분
                    </label>

                    <select id="drawproblemset-scope" name="scope">
                        <?php foreach (array_merge(
                            array('public' => '공개 문제'),
                            $user_id !== ''
                                ? array('mine' => '내가 만든 문제')
                                : array()
                        ) as $scope_value => $scope_label) { ?>
                            <option
                                value="<?php echo drawproblemset_escape(
                                    $scope_value
                                ); ?>"
                                <?php echo $view_problem_scope === $scope_value
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?php echo drawproblemset_escape(
                                    $scope_label
                                ); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="eight wide field">
                    <label for="drawproblemset-search">
                        제목 검색
                    </label>

                    <input
                        id="drawproblemset-search"
                        type="search"
                        name="q"
                        maxlength="100"
                        value="<?php echo drawproblemset_escape(
                            $view_problem_search
                        ); ?>"
                    >
                </div>

                <div class="four wide field">
                    <label>
                        검색
                    </label>

                    <button class="ui primary button" type="submit">
                        검색
                    </button>

                    <a class="ui basic button" href="/drawproblemset.php">
                        초기화
                    </a>
                </div>

            </div>

        </form>

        <p>
            총 <?php echo number_format($view_problem_total); ?>개
        </p>

        <?php if ($user_id !== ''): ?>
            <?php if (!$view_problem_progress_available): ?>
                <div class="ui warning message" role="status">
                    풀이 상태를 불러오지 못했습니다. 새로고침해 주세요.
                </div>
            <?php else: ?>
                <p>
                    <i class="green checkmark icon" aria-hidden="true"></i> 통과
                    · <i class="red checkmark icon" aria-hidden="true"></i> 시도함
                    · 기록이 없으면 빈칸으로 표시합니다.<br>
                    이전 문제 버전의 기록도 포함하며,
                    수행모드에서는 현재 수행 범위만 반영합니다.
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($view_problem_rows)) { ?>

            <div class="ui message">
                <?php if ($view_problem_search !== '') { ?>
                    검색 조건에 맞는 그림 문제가 없습니다.
                <?php } else { ?>
                    표시할 그림 문제가 없습니다.
                <?php } ?>
            </div>

        <?php } else { ?>

            <div style="overflow-x:auto;">

                <table class="ui celled compact table">

                    <thead>
                        <tr>
                            <?php if ($user_id !== ''): ?>
                                <th class="collapsing center aligned" scope="col">통과</th>
                            <?php endif; ?>
                            <th>제목</th>
                            <th>유형</th>
                            <th>작성자</th>
                            <th>버전</th>
                            <th>등록 일시</th>
                            <th>기능</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($view_problem_rows as $problem) { ?>

                            <tr>
                                <?php if ($user_id !== ''): ?>
                                    <?php $progress_state = $view_problem_progress[
                                        (string)$problem['problem_id']
                                    ] ?? 'none'; ?>

                                    <td class="collapsing center aligned">
                                        <?php if (!$view_problem_progress_available): ?>
                                            <span aria-label="풀이 상태 조회 불가">—</span>
                                        <?php elseif ($progress_state === 'solved'): ?>
                                            <span class="status accepted"
                                                  role="img"
                                                  aria-label="통과"
                                                  title="이전 버전을 포함한 본인 제출 중 만점 기록이 있습니다.">
                                                <i class="green checkmark icon" aria-hidden="true"></i>
                                            </span>
                                        <?php elseif ($progress_state === 'submitted'): ?>
                                            <span class="status wrong_answer"
                                                  role="img"
                                                  aria-label="시도함, 아직 통과하지 못함"
                                                  title="제출 기록은 있지만 만점 기록은 없습니다.">
                                                <i class="red checkmark icon" aria-hidden="true"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <a href="/drawproblem.php?problem_id=<?= drawproblemset_escape(
                                        $problem['problem_id']
                                    ) ?>"><?= drawproblemset_escape(
                                        $problem['title']
                                    ) ?></a>
                                </td>

                                <td>
                                    <?php echo $problem['simulation_mode'] === 'drawing'
                                        ? '그림 문제'
                                        : '자유 실습'; ?>
                                </td>

                                <td>
                                    <?php echo drawproblemset_escape(
                                        $problem['owner_user_id']
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo drawproblemset_escape(
                                        $problem['version_no']
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo drawproblemset_escape(
                                        $problem['created_at']
                                    ); ?>
                                </td>

                                <td>
                                    <a class="ui small basic button"
                                       href="/drawproblem.php?problem_id=<?= drawproblemset_escape(
                                           $problem['problem_id']
                                       ) ?>">문제 보기</a>
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

        <?php if ($view_problem_pages > 1) { ?>

            <div class="ui divider"></div>

            <div>
                <?php if ($view_problem_page > 1) { ?>
                    <a
                        class="ui basic button"
                        href="<?php echo drawproblemset_escape(
                            drawproblemset_page_url(
                                $view_problem_page - 1
                            )
                        ); ?>"
                    >
                        이전
                    </a>
                <?php } ?>

                <span>
                    <?php echo intval($view_problem_page); ?>
                    /
                    <?php echo intval($view_problem_pages); ?>
                    페이지
                </span>

                <?php if ($view_problem_page < $view_problem_pages) { ?>
                    <a
                        class="ui basic button"
                        href="<?php echo drawproblemset_escape(
                            drawproblemset_page_url(
                                $view_problem_page + 1
                            )
                        ); ?>"
                    >
                        다음
                    </a>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

</div>

<?php
include('template/' . $OJ_TEMPLATE . '/footer.php');
?>
