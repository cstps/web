# 1024.kr PHP 8.5 및 관리자 화면 현대화 인수인계

작성일: 2026-09-14

## 1. 작업 환경

- 프로젝트: `/home/judge/src/web`
- 브랜치: `upgrade/php85-compat`
- 작업 시작 기준 커밋: `b5bb4da`
- 기준 태그: `pre-php85-upgrade-20260912`
- `tmp.php`는 임시 파일이므로 작업 대상에서 제외한다.
- 압축·임시 산출물은 다음 경로에 저장한다.

  `/home/judge/src/web/tmp/`

## 2. 공통 개발 원칙

관리자 화면은 다음 구조로 전환한다.

- `admin-init.php`: DB, 세션, 권한 공통 초기화
- `admin-layout-start.php`: HTML 문서, 상단 메뉴, 왼쪽 사이드바 시작
- `admin-layout-end.php`: 공통 레이아웃 종료
- 화면 파일: GET 조회와 form 출력만 담당
- 처리 파일: POST, CSRF, 권한, 입력 검증, DB 변경만 담당
- 권한은 화면에서 버튼을 숨기는 것과 별개로 처리 파일에서 다시 검사한다.
- GET 요청으로 DB 상태를 변경하지 않는다.
- KindEditor 대신 공통 `tinymce.php`를 사용한다.
- TinyMCE 사용 페이지는 반드시 `CSS1Compat` 표준 모드여야 한다.
- MyISAM 테이블에는 `beginTransaction()`과 `rollBack()`을 신뢰하지 않는다.
- MyISAM 변경 실패에 대비해 사전 검증과 보상 복구를 적용한다.

## 3. 완료 또는 확인된 작업

### 3.1 관리자 공통 레이아웃

- `admin/help.php` iframe 제거와 정식 공통 레이아웃 전환
- 왼쪽 관리자 메뉴 출력 확인
- 표준 모드 확인
- iframe이 없는 독립 관리자 화면 확인
- `admin/problem_add_page.php` 공통 레이아웃 전환
- 문제 추가 화면 TinyMCE 편집기 4개 출력 확인
- `admin/contest_add.php` 공통 레이아웃 및 TinyMCE 전환
- 대회 생성 화면 왼쪽 사이드바 출력 확인

### 3.2 문제 관리

- 문제 생성자의 공개/비공개 변경 권한 확인
- 다른 사용자가 만든 문제는 상태 변경 대신 `--` 출력 확인
- 문제 출처·분류, 앞 코드, 뒤 코드, 금지어 저장 흐름 검토
- `problem_template` 스키마 및 저장 동기화 확인
- 레거시 `front_code`·`rear_code` 자료 감사 수행
- 문제 템플릿 저장 동기화 확인
- `problem_df_change.php` 권한 및 상태 변경 동작 확인

템플릿 감사 당시 결과:

- MATCH: 34
- DIFFERENT: 30
- MISSING: 22
- EMPTY: 3
- UNRECOGNIZED: 0
- INSERTED: 0

`DIFFERENT`에는 줄바꿈 정규화 차이가 다수 포함되어 있었다.

### 3.3 대회 생성·목록

- `contest_add.php` 대회 생성 화면 개선
- 대회 운영 설정과 제출 가능 언어 UI 개선
- 언어 선택 시 화면이 사라지던 포커스·스크롤 문제 수정
- `contest_create.php` POST 처리 분리
- 제출 가능 언어 최소 1개 검증
- 문제 번호·점수 검증
- 문제 존재 여부 및 `allow_reuse` 정책 검사
- 참가자 ID 정규화·중복 제거·사용자 존재 검사
- 최대 참가자 2,000명 제한
- 대회 관리자 `m{cid}` 및 참가자 `c{cid}` 권한 공통 함수 사용
- `contest_list.php` 공통 레이아웃 및 왼쪽 관리자 메뉴 적용
- 공개·코드·복사·상태 버튼 AJAX 실시간 반영
- 복사 허용/금지 변경 시 관리 항목의 `복사`/`복사금지` 표시 동기화
- 여러 대회의 설정을 순차적으로 변경할 수 있도록 처리
- `contest_problem.c_submit` 증가 확인

### 3.4 권한 함수

`include/permission_functions.inc.php`에 다음 함수가 존재한다.

- `oj_can_manage_admin_contests()`
- `oj_can_manage_contest($contest_id)`
- `oj_normalize_privilege_user_id()`
- `oj_grant_contest_manager_right()`
- `oj_revoke_contest_manager_right()`
- `oj_grant_contest_participant_right()`
- `oj_revoke_contest_participant_right()`

`contest_creator` 권한만으로 다른 사람이 관리하는 기존 대회를 수정할 수 없다.

기존 대회 수정은 다음만 허용한다.

- administrator
- 해당 대회의 활성 `m{cid}` 권한 보유자

### 3.5 CSRF

- `include/set_post_key.php`가 한 화면 안에서 반복 호출되더라도 기존 토큰을 불필요하게 변경하지 않도록 수정
- 한 화면에 여러 form이 있어도 동일한 유효 POST 키를 사용할 수 있도록 정리
- 대회 목록의 변경 form에서 POST 키 존재 확인

### 3.6 DB 감사

2026-09-15 현재 관련 테이블의 스토리지 엔진을 다시 확인했다.

InnoDB:

- `contest`
- `contest_problem`
- `privilege`

MyISAM:

- `problem`
- `solution`
- `users`

따라서 대회 수정 처리에서는 `contest`, `contest_problem`,
`privilege`를 하나의 InnoDB 트랜잭션으로 처리한다.

`solution.num` 변경은 MyISAM이라 트랜잭션으로 되돌릴 수 없으므로,
기존 문제 순서를 미리 저장하고 실패 시 보상 복구한다.

과거 감사에서 확인된 항목:

- `contest_problem.contest_id IS NULL` 레거시 행
- 존재하지 않는 문제를 참조하는 `contest_problem` 행
- 존재하지 않는 대회를 참조하는 권한
- `privilege` 정확 중복 행
- 단독 `p`, `m` 레거시 권한
- `<![CDATA[root]]>` 형식의 잘못된 사용자 ID 권한

과거 감사 결과만으로 DB 정리가 완료되었다고 가정하지 않는다.
중복 권한 정리와 유일 인덱스 적용은 별도 DB 정리 단계에서 진행한다.


### 3.7 채점 시간 설정

문제 1321의 Python 제출이 1초 제한인데 총 8,023ms로 AC가 되는 현상을 확인했다.

원인:

- 테스트 데이터가 여러 개
- 기존 설정이 테스트별 시간 합계를 표시
- Python 시간 보너스 적용
- `OJ_USE_MAX_TIME=0`

테스트별 최대 시간을 기준으로 사용하도록 다음 설정을 반영하고 정상 확인했다.

- `OJ_JAVA_TIME_BONUS=0`
- `OJ_TIME_LIMIT_TO_TOTAL=0`
- `OJ_USE_MAX_TIME=1`

## 4. 대회 수정 처리 분리 완료

완료일: 2026-09-15

### 4.1 `admin/contest_edit.php`

대회 수정 화면과 저장 처리를 분리했다.

현재 역할:

- GET으로 대회 정보를 조회하고 수정 form을 출력
- 기존 POST 저장 로직 전체 제거
- form action을 `contest_update.php`로 연결
- 로그인 여부 확인 후 실제 대회 수정 권한을 별도로 확인
- administrator 또는 활성 `m{cid}` 권한 보유자만 수정 가능
- 참가자 목록에는 실제 사용자의 활성 `c{cid}` 권한만 중복 없이 표시
- 문제 점수는 `cpoint[문제번호]` 형식으로 전송
- 문제 순서는 `cproblem`에 표시 순서대로 전송
- CSRF용 POST 키 포함

현재 화면은 아직 `admin-header.php`와 KindEditor를 사용한다.
공통 관리자 레이아웃과 TinyMCE 전환은 후속 작업으로 남긴다.

### 4.2 `admin/contest_update.php`

POST 전용 대회 수정 처리 파일을 완성했다.

적용된 검증:

- POST 요청만 허용
- 대회 번호 형식과 존재 여부 확인
- `oj_can_manage_contest($cid)` 권한 재검사
- CSRF 확인
- 제목 필수·길이·UTF-8·제어문자 검사
- 설명 UTF-8·NUL·TEXT 바이트 길이 검사
- 시작·종료 날짜와 시간의 실제 유효성 검사
- 종료 시각이 시작 시각보다 늦은지 검사
- 운영 설정을 0 또는 1로 제한
- 제출 언어 형식·범위·중복 검사
- 문제 번호 형식·중복·존재 여부 검사
- 대회당 최대 128문제 제한
- 기존 문제에는 `allow_reuse` 정책을 소급 적용하지 않음
- 새 문제에는 소유권·공개 상태·`allow_reuse` 정책 적용
- 모든 문제 점수를 DB 변경 전에 검증
- 참가자 ID 정규화와 중복 제거
- 참가자 최대 2,000명 제한
- 참가자 존재 여부를 500명 단위로 일괄 확인

저장 방식:

- `contest`, `contest_problem`, `privilege`를 InnoDB 트랜잭션으로 처리
- 참가자 권한은 전체 삭제하지 않고 변경 대상만 부여·회수
- 모든 `pdo_query()` 실패 결과를 검사
- `solution.num`은 한 번의 CASE UPDATE로 새 문제 순서에 동기화
- 저장 실패 시 InnoDB 롤백
- 롤백 성공 시 MyISAM `solution.num`을 기존 순서로 보상 복구
- 복구 실패와 롤백 상태 불명은 `error_log()`에 기록
- 성공 시 303으로 `contest_list.php` 이동
- HTML, form, KindEditor 등 화면 코드 없음

### 4.3 완료된 실제 테스트

별도 테스트 대회 CID 3064에서 다음을 확인했다.

- 제목과 기본정보 수정
- 문제 추가·삭제
- 문제 순서 변경
- 문제 점수 변경
- 참가자 권한 부여
- 같은 참가자를 여러 번 입력해도 중복 권한이 생성되지 않음
- 참가자 권한 회수 후 재로그인 시 비공개 대회 접근 차단
- 기존 제출의 `solution.num`이 새 문제 순서에 맞게 변경됨
- 제외된 문제의 기존 제출은 삭제되지 않고 `num=-1`로 변경됨
- 제외 문제 재추가 시 기존 제출의 `num` 복원
- 존재하지 않는 참가자 입력 시 모든 DB 상태가 그대로 유지됨
- 허용 범위를 벗어난 점수 차단
- 제출 언어 미선택 차단
- PHP-FPM과 nginx 오류 로그에 치명적 오류 없음

## 5. 다음 작업

우선순위 순서:

1. 이번 대회 수정 처리 변경을 Git에 커밋
2. `contest_edit.php`를 `admin-init.php`와 공통 관리자 레이아웃으로 전환
3. KindEditor를 공통 TinyMCE로 교체
4. 선택 문제에 `위로`·`아래로` 순서 변경 버튼 추가
5. 대회 수정 오류를 일반 텍스트 대신 관리자 화면 오류 메시지로 개선
6. `privilege` 중복 자료 정리 후 적절한 유일 인덱스 검토
7. `problem`, `solution`, `users`의 MyISAM 유지 필요성 검토
8. DB 엔진 전환 전 백업·잠금·서비스 중단 계획 수립

테스트 대회 CID 3064는 작업 검증이 완전히 끝날 때까지 유지한다.
