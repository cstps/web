# 학교 행사 배포 설정

## 공개주소

- 학교: `/class-share/{학교 식별자}`
- 행사: `/class-share/{학교 식별자}/{행사 식별자}`
- 관리자: `/class-share/admin/login.php`

예시:

- `/class-share/gne`
- `/class-share/gne/2026-2`

## Nginx 설정

저장소의 다음 파일을 사용한다.

`class-share/deploy/nginx/class-share-routes.conf`

운영 서버 설치 위치:

`/etc/nginx/snippets/class-share-routes.conf`

설치 예시:

```bash
sudo install \
    -m 644 \
    class-share/deploy/nginx/class-share-routes.conf \
    /etc/nginx/snippets/class-share-routes.conf
```

HTTPS를 처리하는 `server` 블록에서 일반 `location /`보다
앞에 다음 구문을 둔다.

```nginx
include /etc/nginx/snippets/class-share-routes.conf;
```

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

## 배포 확인

```bash
curl -I https://1024.kr/class-share/gne
curl -I https://1024.kr/class-share/gne/2026-2
curl -I https://1024.kr/class-share/admin/login.php
```
