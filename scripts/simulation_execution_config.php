<?php

// 엔진 버전은 Python 인터프리터 버전과 구분한다.
// Python 실험 엔진은 운영 실행 경로에 연결하기 전까지 기본 차단한다.
function simulation_execution_profiles()
{
    return array(
        array(
            'source_language' => 'javascript',
            'execution_engine' => 'p5js',
            'execution_version' => '2.3.4',
            'experimental' => false,
        ),
        array(
            'source_language' => 'python',
            'execution_engine' => 'python-stdlib-rgba-poc',
            'execution_version' => '1',
            'experimental' => true,
        ),
    );
}


function simulation_validate_execution_metadata(
    array $settings,
    $allow_experimental = false
) {
    if (!is_bool($allow_experimental)) {
        throw new InvalidArgumentException(
            '실험 엔진 허용 여부는 논리값이어야 합니다.'
        );
    }

    $keys = array(
        'source_language',
        'execution_engine',
        'execution_version',
    );

    foreach ($keys as $key) {
        if (
            !array_key_exists($key, $settings) ||
            !is_string($settings[$key]) ||
            $settings[$key] === ''
        ) {
            throw new InvalidArgumentException(
                '실행 설정의 언어·엔진·버전을 확인해 주세요.'
            );
        }
    }

    foreach (simulation_execution_profiles() as $profile) {
        $matches = true;

        foreach ($keys as $key) {
            if ($settings[$key] !== $profile[$key]) {
                $matches = false;
                break;
            }
        }

        if (!$matches) {
            continue;
        }

        if ($profile['experimental'] && !$allow_experimental) {
            throw new RuntimeException(
                '현재 처리 경로에서는 실험용 실행 엔진을 사용할 수 없습니다.'
            );
        }

        // 기존 p5.js 버전 열이 있는 정답·문제 버전은 일치 여부도 검사한다.
        if (
            $profile['execution_engine'] === 'p5js' &&
            array_key_exists('p5_version', $settings) &&
            $settings['p5_version'] !== $profile['execution_version']
        ) {
            throw new RuntimeException(
                '기존 p5.js 버전과 실행 설정의 버전이 다릅니다.'
            );
        }

        return array(
            'source_language' => $profile['source_language'],
            'execution_engine' => $profile['execution_engine'],
            'execution_version' => $profile['execution_version'],
        );
    }

    throw new InvalidArgumentException(
        '지원하지 않는 언어·실행 엔진·버전 조합입니다.'
    );
}
