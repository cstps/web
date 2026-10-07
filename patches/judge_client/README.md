# judge_client patch 관리

## 2026-10-07-test-run-trace.patch

Python 단계적 실행 기능을 위해
`/home/judge/src/core/judge_client/judge_client.cc`에 적용하는 patch이다.

주요 변경:

- `trace.json` 읽기
- `test_run_trace` 테이블 저장
- 일반 전체 실행에는 영향 없음
- 512 KiB를 초과한 trace JSON은 저장하지 않음

## 적용

```bash
cd /home/judge/src/core/judge_client

git apply \
  /home/judge/src/web/patches/judge_client/2026-10-07-test-run-trace.patch

cd /home/judge/src/core/judge_client

make -f makefile clean
make -f makefile
sudo cp judge_client /usr/bin/judge_client
sudo chown root:root /usr/bin/judge_client
sudo chmod 755 /usr/bin/judge_client

sudo systemctl restart hustoj.service
systemctl is-active hustoj.service

가장 중요한 것은 마지막 줄입니다.

```text
