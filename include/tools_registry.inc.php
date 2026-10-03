<?php

/**
 * 유틸리티 도구 목록
 *
 * 새 유틸리티를 추가할 때 이 배열에 항목을 추가한다.
 *
 * 필수 항목:
 * - id          : 도구 식별자
 * - url         : 도구 주소
 * - icon        : Semantic UI 아이콘
 * - title       : 기본 제목
 * - meta        : 도구 분류 설명
 * - description : 도구 설명
 * - category    : 카드 하단 분류
 *
 * 선택 항목:
 * - message_var : 다국어 메시지 변수명
 */

return [
    [
        'id' => 'pc',
        'url' => '/tools/pc.php',
        'icon' => 'calculator',
        'title' => '한자리 합 계산기',
        'message_var' => 'MSG_POINTCHECK',
        'meta' => '점수 및 숫자 계산',
        'description' =>
            '입력한 숫자의 합을 실시간으로 계산하고 필요한 숫자 연산을 간편하게 처리합니다.',
        'category' => '계산 도구',
    ],

    [
        'id' => 'charcount',
        'url' => '/tools/charcount.php',
        'icon' => 'font',
        'title' => '글자 수 세기',
        'message_var' => 'MSG_CHARCOUNT',
        'meta' => '텍스트 분석',
        'description' =>
            '공백 포함·제외 글자 수와 바이트 수, 단어 수를 실시간으로 확인합니다.',
        'category' => '텍스트 도구',
    ],

    [
        'id' => 'seat_assign',
        'url' => '/tools/seat_assign.php',
        'icon' => 'users',
        'title' => '자리 배치',
        'message_var' => 'MSG_SEATASSIGN',
        'meta' => '수업 및 시험 운영',
        'description' =>
            '학생들의 실습실 또는 시험 좌석을 무작위로 배치합니다.',
        'category' => '수업 도구',
    ],

    [
        'id' => 'sadari',
        'url' => '/tools/sadari.php',
        'icon' => 'random',
        'title' => '사다리 타기',
        'message_var' => 'MSG_SADARI',
        'meta' => '무작위 선택',
        'description' =>
            '발표 순서, 역할 배정, 팀 선택 등에 활용할 수 있는 사다리 도구입니다.',
        'category' => '추첨 도구',
    ],

    [
        'id' => 'shorturl',
        'url' => '/tools/shorturl.php',
        'icon' => 'linkify',
        'title' => '단축 URL 생성기',
        'meta' => '주소 정리',
        'description' =>
            '긴 웹 주소를 짧고 공유하기 쉬운 주소로 변환합니다.',
        'category' => '웹 도구',
    ],

    [
        'id' => 'timer',
        'url' => '/tools/timer.php',
        'icon' => 'stopwatch',
        'title' => '수업용 타이머',
        'meta' => '시간 관리',
        'description' =>
            '실습, 발표, 활동 시간을 큰 화면으로 확인하고 관리할 수 있습니다.',
        'category' => '수업 도구',
    ],

    [
        'id' => 'picker',
        'url' => '/tools/picker.php',
        'icon' => 'user',
        'title' => '랜덤 학생 추첨기',
        'meta' => '발표 및 질문',
        'description' =>
            '학생 번호나 이름을 입력해 무작위로 한 명을 선택합니다.',
        'category' => '추첨 도구',
    ],

    [
        'id' => 'humanbenchmark',
        'url' => '/tools/humanbenchmark.php',
        'icon' => 'gamepad',
        'title' => '휴먼벤치마크',
        'meta' => '반응 및 기억 테스트',
        'description' =>
            '반응속도와 순서 기억, 숫자 기억 등 간단한 인지 테스트를 제공합니다.',
        'category' => '학습 도구',
    ],
];
