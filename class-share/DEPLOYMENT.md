# 학교 행사 배포 설정

## 공개주소

- 학교: `/class-share/{학교 식별자}`
- 행사: `/class-share/{학교 식별자}/{행사 식별자}`
- 관리자: `/class-share/admin/login.php`

예시:

- `/class-share/gne`
- `/class-share/gne/2026-2`

## Nginx 설정

저장소의 다음 두 파일을 사용한다.

- `class-share/deploy/nginx/class-share-routes.conf`
- `class-share/deploy/nginx/web-sensitive-files.conf`

운영 서버 설치 위치는 다음과 같다.

- `/etc/nginx/snippets/class-share-routes.conf`
- `/etc/nginx/snippets/web-sensitive-files.conf`

설치 예시:

```bash
sudo install \
    -m 644 \
    class-share/deploy/nginx/class-share-routes.conf \
    /etc/nginx/snippets/class-share-routes.conf

sudo install \
    -m 644 \
    class-share/deploy/nginx/web-sensitive-files.conf \
    /etc/nginx/snippets/web-sensitive-files.conf
```

HTTPS를 처리하는 `server` 블록에서 일반 `location /`보다
앞에 다음 구문을 둔다. 두 규칙은
PHP 처리용 `location ~ \.php$`보다 앞에서 적용되어야 한다.

```nginx
include /etc/nginx/snippets/class-share-routes.conf;
include /etc/nginx/snippets/web-sensitive-files.conf;
```

`class-share-routes.conf`는 학교와 행사의 짧은 공개주소를 처리한다.
`web-sensitive-files.conf`는 저장소 메타데이터, 내부 PHP,
SQL·백업·설정 파일 및 업로드 폴더의 실행 가능 콘텐츠를
서버 전체에서 차단한다.

설정을 변경한 뒤 다음 순서로 확인한다.

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 주의사항

- Nginx 백업 파일을 `sites-enabled` 안에 두지 않는다.
- 백업은 `/etc/nginx/backups/`처럼 로드되지 않는 위치에 둔다.
- `admin`, `assets`, `include`는 학교 식별자로 처리하지 않는다.
- 기존 쿼리형 주소는 호환을 위해 계속 접근할 수 있다.
- 공개 화면이 생성하는 행사 주소는 짧은 주소를 사용한다.
- `web-sensitive-files.conf`는 행사관리뿐 아니라 전체 웹 루트에 적용한다.
- 차단 규칙을 변경한 뒤에는 정상 OJ 화면도 함께 회귀 확인한다.

## 배포 확인

```bash
curl -I https://1024.kr/class-share/gne
curl -I https://1024.kr/class-share/gne/2026-2
curl -I https://1024.kr/class-share/admin/login.php
```

다음 내부 파일과 경로는 `404`가 반환되어야 한다.

```bash
curl -I https://1024.kr/.git/HEAD
curl -I https://1024.kr/sql/
curl -I https://1024.kr/include/db_info.inc.php
curl -I https://1024.kr/saasinit.php
curl -I https://1024.kr/sae/install.php
```

## 개인정보 보관 기한 자동 파기

행사의 `retention_until`이 현재 날짜보다 지난 신청을 매일 확인한다.
신청 행 자체는 통계 유지를 위해 남기고 다음 개인정보와 인증 자료를
`NULL`로 변경한다.

- 신청번호
- 신청자 성명과 소속
- 암호화 연락처와 검색용 연락처 자료
- 신청 확인 비밀번호
- 관리자 메모

파기 후에는 `privacy_destroyed_at`에 파기 일시를 기록한다.
행사·프로그램·신청 상태·신청 및 취소 일시는 통계용으로 유지한다.
신청 대상 감사 로그의 JSON 자료와 IP 주소도 함께 비식별화한다.

### 최초 스키마 적용

DB 백업을 먼저 만든 뒤 다음 마이그레이션을 한 번만 실행한다.

```bash
sudo mysql \
    --default-character-set=utf8mb4 \
    jol \
    < sql/migrations/2026-09-25-class-share-privacy-retention.sql
```

파기 대상을 변경 없이 확인하려면 다음 명령을 사용한다.

```bash
php class-share/admin/cli/purge_expired_application_privacy.php --dry-run
```

### systemd 자동 실행

```bash
sudo install -m 644 \
    class-share/deploy/systemd/class-share-privacy-purge.service \
    /etc/systemd/system/class-share-privacy-purge.service

sudo install -m 644 \
    class-share/deploy/systemd/class-share-privacy-purge.timer \
    /etc/systemd/system/class-share-privacy-purge.timer

sudo systemctl daemon-reload
sudo systemctl enable --now class-share-privacy-purge.timer
```

타이머는 매일 오전 `03:20` 한국 시간에 실행된다.
서버가 꺼져 있던 동안 실행 시각이 지나간 경우에는 부팅 후 실행된다.

```bash
sudo systemctl start class-share-privacy-purge.service
sudo systemctl list-timers class-share-privacy-purge.timer --all
sudo journalctl -u class-share-privacy-purge.service -n 20 --no-pager
```
