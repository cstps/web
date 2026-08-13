<?php

require_once('./include/db_info.inc.php');
require_once('./include/const.inc.php');
require_once('./include/memcache.php');
require_once('./include/setlang.php');


// ============================================================
// 1. 로그인 확인
// ============================================================

if (
    !isset(
        $_SESSION[
            $OJ_NAME.'_user_id'
        ]
    )
) {

    http_response_code(403);
    exit;
}


// ============================================================
// 2. cid 확인
// ============================================================

$cid =
    isset($_GET['cid'])
        ? intval($_GET['cid'])
        : 0;


if ($cid <= 0) {

    http_response_code(400);
    exit;
}


// ============================================================
// 3. 권한 확인
//
// 허용:
// - administrator
// - 해당 대회의 m{cid}
//
// 다른 대회 교사 / contest_creator / source_browser 등은
// AJAX 데이터에도 접근할 수 없다.
// ============================================================

$is_admin =
    isset(
        $_SESSION[
            $OJ_NAME.'_administrator'
        ]
    );


$is_contest_manager =
    isset(
        $_SESSION[
            $OJ_NAME.'_m'.$cid
        ]
    );


if (
    !$is_admin &&
    !$is_contest_manager
) {

    http_response_code(403);
    exit;
}


// ============================================================
// 4. 대회 존재 확인
// ============================================================

$contest_result =
    pdo_query(
        "SELECT contest_id
         FROM contest
         WHERE contest_id=?
         LIMIT 1",
        $cid
    );


if (
    !$contest_result ||
    count($contest_result) == 0
) {

    http_response_code(404);
    exit;
}


// ============================================================
// 5. 대회 문제 목록
//
// num:
// 0 → A
// 1 → B
// 2 → C
// ...
// ============================================================

$contest_problem_sql =
    "SELECT
        cp.num,
        cp.problem_id
     FROM contest_problem cp
     WHERE cp.contest_id=?
     ORDER BY cp.num ASC";


$contest_problem_result =
    pdo_query(
        $contest_problem_sql,
        $cid
    );


if (!$contest_problem_result) {

    $contest_problem_result =
        array();
}


$contest_problems =
    array();


foreach (
    $contest_problem_result
    as
    $row
) {

    $num =
        isset(
            $row['num']
        )
            ? intval(
                $row['num']
            )
            : intval(
                $row[0]
            );


    $problem_id =
        isset(
            $row['problem_id']
        )
            ? intval(
                $row['problem_id']
            )
            : intval(
                $row[1]
            );


    $contest_problems[
        $num
    ] =
        array(

            'num' =>
                $num,

            'label' =>
                isset(
                    $PID[$num]
                )
                    ? $PID[$num]
                    : chr(
                        ord('A') +
                        $num
                    ),

            'problem_id' =>
                $problem_id
        );
}


// ============================================================
// 6. 학생 현황에서 제외할 운영계정 목록
//
// 제외:
// - administrator
// - contest_creator
// - problem_editor
// - source_browser
// - 해당 대회의 m{cid}
//
// 해당 계정이 문제를 실제 제출했더라도 학생 목록에서는 제외한다.
// ============================================================

$excluded_users =
    array();


$manager_right =
    "m".$cid;


$staff_sql =
    "SELECT DISTINCT user_id
     FROM privilege
     WHERE rightstr IN (
        'administrator',
        'contest_creator',
        'problem_editor',
        'source_browser',
        ?
     )";


$staff_result =
    pdo_query(
        $staff_sql,
        $manager_right
    );


if ($staff_result) {

    foreach (
        $staff_result
        as
        $row
    ) {

        $uid =
            isset(
                $row['user_id']
            )
                ? trim(
                    $row['user_id']
                )
                : trim(
                    $row[0]
                );


        if ($uid !== "") {

            $excluded_users[
                strtolower(
                    $uid
                )
            ] =
                true;
        }
    }
}


// ============================================================
// 7. 대회 참가 학생 목록
//
// 1) privilege c{cid}
// 2) 실제 대회 제출 사용자
//
// 두 목록을 합친다.
// ============================================================

$contest_students =
    array();


// ------------------------------------------------------------
// 7-1. c{cid} 권한 사용자
// ------------------------------------------------------------

$contest_right =
    "c".$cid;


$student_sql =
    "SELECT DISTINCT user_id
     FROM privilege
     WHERE rightstr=?
     ORDER BY user_id ASC";


$student_result =
    pdo_query(
        $student_sql,
        $contest_right
    );


if ($student_result) {

    foreach (
        $student_result
        as
        $row
    ) {

        $uid =
            isset(
                $row['user_id']
            )
                ? trim(
                    $row['user_id']
                )
                : trim(
                    $row[0]
                );


        if (
            $uid !== "" &&
            !isset(
                $excluded_users[
                    strtolower(
                        $uid
                    )
                ]
            )
        ) {

            $contest_students[
                $uid
            ] =
                true;
        }
    }
}


// ------------------------------------------------------------
// 7-2. 실제 대회 제출 사용자
//
// 공개 대회 또는 c{cid} 권한이 없는 경우도 포함
// ------------------------------------------------------------

$submitted_student_sql =
    "SELECT DISTINCT user_id
     FROM solution
     WHERE contest_id=?
       AND problem_id>0
     ORDER BY user_id ASC";


$submitted_student_result =
    pdo_query(
        $submitted_student_sql,
        $cid
    );


if ($submitted_student_result) {

    foreach (
        $submitted_student_result
        as
        $row
    ) {

        $uid =
            isset(
                $row['user_id']
            )
                ? trim(
                    $row['user_id']
                )
                : trim(
                    $row[0]
                );


        if (
            $uid !== "" &&
            !isset(
                $excluded_users[
                    strtolower(
                        $uid
                    )
                ]
            )
        ) {

            $contest_students[
                $uid
            ] =
                true;
        }
    }
}


// 학생 ID순 정렬
ksort(
    $contest_students
);


// ============================================================
// 8. 학생 + 문제별 과정 요약
//
// solution_process 기준
//
// - 제출 횟수
// - AI 사용 횟수
// - 최초 계획 여부
// - 최신 solution_id
// ============================================================

$summary_sql =
    "SELECT
        sp.user_id,
        sp.problem_id,

        COUNT(*) AS submit_count,

        SUM(
            CASE
                WHEN sp.ai_used=1
                THEN 1
                ELSE 0
            END
        ) AS ai_count,

        MAX(
            CASE
                WHEN sp.plan_text IS NOT NULL
                 AND TRIM(sp.plan_text) <> ''
                THEN 1
                ELSE 0
            END
        ) AS has_plan,

        MAX(sp.solution_id)
            AS latest_solution_id

     FROM solution_process sp

     WHERE sp.contest_id=?

     GROUP BY
        sp.user_id,
        sp.problem_id

     ORDER BY
        sp.user_id ASC,
        sp.problem_id ASC";


$summary_result =
    pdo_query(
        $summary_sql,
        $cid
    );


if (!$summary_result) {

    $summary_result =
        array();
}


// ============================================================
// 9. 최신 제출 결과를 한 번에 조회
//
// 학생×문제마다 SQL을 실행하지 않는다.
// ============================================================

$latest_ids =
    array();


foreach (
    $summary_result
    as
    $row
) {

    $latest_sid =
        isset(
            $row[
                'latest_solution_id'
            ]
        )
            ? intval(
                $row[
                    'latest_solution_id'
                ]
            )
            : intval(
                $row[5]
            );


    if ($latest_sid > 0) {

        $latest_ids[] =
            $latest_sid;
    }
}


$latest_result_map =
    array();


if (
    count(
        $latest_ids
    ) > 0
) {

    $latest_ids =
        array_values(
            array_unique(
                $latest_ids
            )
        );


    // intval을 거친 solution_id만 들어 있으므로
    // IN 절에서 사용 가능
    $safe_latest_ids =
        array();


    foreach (
        $latest_ids
        as
        $latest_id
    ) {

        $latest_id =
            intval(
                $latest_id
            );


        if ($latest_id > 0) {

            $safe_latest_ids[] =
                $latest_id;
        }
    }


    if (
        count(
            $safe_latest_ids
        ) > 0
    ) {

        $id_list =
            implode(
                ",",
                $safe_latest_ids
            );


        $latest_sql =
            "SELECT
                solution_id,
                result,
                num
             FROM solution
             WHERE solution_id IN ($id_list)";


        $latest_result =
            pdo_query(
                $latest_sql
            );


        if ($latest_result) {

            foreach (
                $latest_result
                as
                $row
            ) {

                $solution_id =
                    isset(
                        $row[
                            'solution_id'
                        ]
                    )
                        ? intval(
                            $row[
                                'solution_id'
                            ]
                        )
                        : intval(
                            $row[0]
                        );


                $latest_result_map[
                    $solution_id
                ] =
                    array(

                        'result' =>
                            isset(
                                $row[
                                    'result'
                                ]
                            )
                                ? intval(
                                    $row[
                                        'result'
                                    ]
                                )
                                : intval(
                                    $row[1]
                                ),

                        'num' =>
                            isset(
                                $row[
                                    'num'
                                ]
                            )
                                ? intval(
                                    $row[
                                        'num'
                                    ]
                                )
                                : intval(
                                    $row[2]
                                )
                    );
            }
        }
    }
}


// ============================================================
// 10. 화면용 학생+문제 데이터 생성
// ============================================================

$view_process_list =
    array();


foreach (
    $summary_result
    as
    $row
) {

    $user_id =
        isset(
            $row[
                'user_id'
            ]
        )
            ? trim(
                $row[
                    'user_id'
                ]
            )
            : trim(
                $row[0]
            );


    // --------------------------------------------------------
    // 운영계정 제외
    // --------------------------------------------------------

    $normalized_user_id =
        strtolower(
            $user_id
        );


    if (
        isset(
            $excluded_users[
                $normalized_user_id
            ]
        )
    ) {

        continue;
    }


    $problem_id =
        isset(
            $row[
                'problem_id'
            ]
        )
            ? intval(
                $row[
                    'problem_id'
                ]
            )
            : intval(
                $row[1]
            );


    $submit_count =
        isset(
            $row[
                'submit_count'
            ]
        )
            ? intval(
                $row[
                    'submit_count'
                ]
            )
            : intval(
                $row[2]
            );


    $ai_count =
        isset(
            $row[
                'ai_count'
            ]
        )
            ? intval(
                $row[
                    'ai_count'
                ]
            )
            : intval(
                $row[3]
            );


    $has_plan =
        isset(
            $row[
                'has_plan'
            ]
        )
            ? intval(
                $row[
                    'has_plan'
                ]
            )
            : intval(
                $row[4]
            );


    $latest_solution_id =
        isset(
            $row[
                'latest_solution_id'
            ]
        )
            ? intval(
                $row[
                    'latest_solution_id'
                ]
            )
            : intval(
                $row[5]
            );


    $latest_result_num =
        -1;

    $problem_num =
        -1;


    if (
        isset(
            $latest_result_map[
                $latest_solution_id
            ]
        )
    ) {

        $latest_result_num =
            intval(
                $latest_result_map[
                    $latest_solution_id
                ][
                    'result'
                ]
            );


        $problem_num =
            intval(
                $latest_result_map[
                    $latest_solution_id
                ][
                    'num'
                ]
            );
    }


    $view_process_list[] =
        array(

            'user_id' =>
                $user_id,

            'problem_id' =>
                $problem_id,

            'problem_num' =>
                $problem_num,

            'submit_count' =>
                $submit_count,

            'ai_count' =>
                $ai_count,

            'has_plan' =>
                $has_plan,

            'latest_solution_id' =>
                $latest_solution_id,

            'latest_result' =>
                $latest_result_num
        );
}


// ============================================================
// 11. 학생 × 문제 매트릭스 생성
// ============================================================

$student_matrix =
    array();


// ------------------------------------------------------------
// 모든 참가 학생을 먼저 등록
//
// 아직 제출하지 않은 학생도 표시
// ------------------------------------------------------------

foreach (
    $contest_students
    as
    $uid => $dummy
) {

    $student_matrix[
        $uid
    ] =
        array(

            'user_id' =>
                $uid,

            'problems' =>
                array(),

            'total_submit' =>
                0,

            'total_ai' =>
                0,

            'solved_count' =>
                0
        );
}


// ------------------------------------------------------------
// 실제 과정 데이터 적용
// ------------------------------------------------------------

foreach (
    $view_process_list
    as
    $item
) {

    $uid =
        trim(
            $item[
                'user_id'
            ]
        );


    $num =
        intval(
            $item[
                'problem_num'
            ]
        );


    // --------------------------------------------------------
    // 운영계정 한 번 더 차단
    // --------------------------------------------------------

    if (
        isset(
            $excluded_users[
                strtolower(
                    $uid
                )
            ]
        )
    ) {

        continue;
    }


    // --------------------------------------------------------
    // contest_students에 없던 실제 제출자 대비
    // --------------------------------------------------------

    if (
        !isset(
            $student_matrix[
                $uid
            ]
        )
    ) {

        $student_matrix[
            $uid
        ] =
            array(

                'user_id' =>
                    $uid,

                'problems' =>
                    array(),

                'total_submit' =>
                    0,

                'total_ai' =>
                    0,

                'solved_count' =>
                    0
            );
    }


    // --------------------------------------------------------
    // 문제 번호가 대회 문제 목록에 존재하는 경우만 적용
    // --------------------------------------------------------

    if (
        $num < 0 ||
        !isset(
            $contest_problems[
                $num
            ]
        )
    ) {

        continue;
    }


    // --------------------------------------------------------
    // 문제별 상태
    // --------------------------------------------------------

    $student_matrix[
        $uid
    ][
        'problems'
    ][
        $num
    ] =
        array(

            'problem_id' =>
                intval(
                    $item[
                        'problem_id'
                    ]
                ),

            'latest_solution_id' =>
                intval(
                    $item[
                        'latest_solution_id'
                    ]
                ),

            'latest_result' =>
                intval(
                    $item[
                        'latest_result'
                    ]
                ),

            'submit_count' =>
                intval(
                    $item[
                        'submit_count'
                    ]
                ),

            'ai_count' =>
                intval(
                    $item[
                        'ai_count'
                    ]
                ),

            'has_plan' =>
                intval(
                    $item[
                        'has_plan'
                    ]
                )
        );


    // --------------------------------------------------------
    // 학생 전체 제출
    // --------------------------------------------------------

    $student_matrix[
        $uid
    ][
        'total_submit'
    ] +=
        intval(
            $item[
                'submit_count'
            ]
        );


    // --------------------------------------------------------
    // 학생 전체 AI
    // --------------------------------------------------------

    $student_matrix[
        $uid
    ][
        'total_ai'
    ] +=
        intval(
            $item[
                'ai_count'
            ]
        );


    // --------------------------------------------------------
    // HUSTOJ result=4 → Accepted
    // --------------------------------------------------------

    if (
        intval(
            $item[
                'latest_result'
            ]
        ) === 4
    ) {

        $student_matrix[
            $uid
        ][
            'solved_count'
        ]++;
    }
}


// 학생 ID순 정렬
ksort(
    $student_matrix
);


// ============================================================
// 12. 교사 관찰 메모 개수
//
// 학생 + 문제 기준
//
// AJAX 갱신 후에도
// [메모 1], [메모 2]가 최신 상태로 표시된다.
// ============================================================

$teacher_note_count_map =
    array();


$note_sql =
    "SELECT
        user_id,
        problem_id,
        COUNT(*) AS note_count

     FROM teacher_process_note

     WHERE contest_id=?

     GROUP BY
        user_id,
        problem_id";


$note_result =
    pdo_query(
        $note_sql,
        $cid
    );


if ($note_result) {

    foreach (
        $note_result
        as
        $row
    ) {

        $note_user_id =
            isset(
                $row[
                    'user_id'
                ]
            )
                ? trim(
                    $row[
                        'user_id'
                    ]
                )
                : trim(
                    $row[0]
                );


        $note_problem_id =
            isset(
                $row[
                    'problem_id'
                ]
            )
                ? intval(
                    $row[
                        'problem_id'
                    ]
                )
                : intval(
                    $row[1]
                );


        $note_count =
            isset(
                $row[
                    'note_count'
                ]
            )
                ? intval(
                    $row[
                        'note_count'
                    ]
                )
                : intval(
                    $row[2]
                );


        if ($note_user_id === "") {

            continue;
        }


        if (
            !isset(
                $teacher_note_count_map[
                    $note_user_id
                ]
            )
        ) {

            $teacher_note_count_map[
                $note_user_id
            ] =
                array();
        }


        $teacher_note_count_map[
            $note_user_id
        ][
            $note_problem_id
        ] =
            $note_count;
    }
}


// ============================================================
// 13. AJAX 전용 템플릿 출력
//
// header.php / footer.php를 호출하지 않는다.
// 학생 현황 표 HTML만 반환한다.
// ============================================================

require(
    "template/".
    $OJ_TEMPLATE.
    "/contest_process_students_ajax.php"
);

?>