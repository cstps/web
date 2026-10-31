# 한 차시에 여러 코딩 실습 허용

## DB 변경

2026-10-31 운영 서버에 적용한 SQL:

    ALTER TABLE course_contest DROP INDEX uq_course_lesson_no;

이 문서는 적용 이력이며 자동 실행되지 않는다.
이미 적용한 서버에서는 위 SQL을 다시 실행하지 않는다.

## 유지하는 제약

- course_lesson.uk_course_lesson_no: 수업 안에서 차시 번호 중복 방지
- course_contest.uk_course_contest_contest_id: 동일 대회 중복 연결 방지
- course_activity_contest.uq_cac_course_contest: 활동과 대회 연결의 일대일 매핑

## 프로그램 변경

- 기존 차시에 여러 Contest 활동 추가 허용
- 기존 차시에 활동을 추가할 때 새 차시용 번호 중복 검사 제외
- 다른 Contest 활동이 있는 차시에도 개별 활동 복구·재배정 허용
- 기존 권한 검사와 저장 트랜잭션 유지

## 배포 시 유의 사항

PHP 변경과 DB 인덱스 변경을 함께 적용해야 한다.
여러 활동이 추가된 뒤에는 기존 UNIQUE 인덱스를 바로 복원할 수 없다.
