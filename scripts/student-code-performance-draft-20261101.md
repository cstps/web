# 일반 코딩 수행모드 초안 분리

배포 전에 student_code_draft_performance 테이블을 준비한다.
운영 서버에서 실행한 SQL은 다음과 같다. 이 문서는 자동 실행되지 않는다.

    CREATE TABLE student_code_draft_performance LIKE student_code_draft;
    ALTER TABLE student_code_draft_performance
      ENGINE = InnoDB,
      ADD COLUMN performance_started_at DATETIME NOT NULL AFTER contest_id,
      DROP INDEX uk_student_code_draft_user_problem_contest,
      ADD UNIQUE KEY uq_student_code_draft_performance
        (user_id, problem_id, contest_id, performance_started_at);

일반 초안은 기존 테이블에 보존한다. 수행 초안은 서버에서 확인한 가장 이른
활성 수행모드 시작 시각으로 구분한다. 이전 화면의 자동저장은 409로 거부한다.
교사용 모니터는 현재 모드에 해당하는 초안만 조회한다.
코드 롤백 시에도 수행 초안 데이터가 있는 새 테이블은 삭제하지 않는다.
