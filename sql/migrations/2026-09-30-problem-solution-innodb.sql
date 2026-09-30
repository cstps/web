-- 운영 서버 적용 완료: 2026-09-30
-- 실행 전 백업 및 웹/채점 쓰기 중단 필요.
ALTER TABLE `problem` ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;
ALTER TABLE `solution` ENGINE=InnoDB, ROW_FORMAT=DYNAMIC;
