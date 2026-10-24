#!/usr/bin/env bash
#
# E2E テスト（Playwright）の実行スクリプト（E2E テスト設計書 2章）。
#
# 「試験専用の環境を起動 → データ投入 → Playwright で画面を操作 → 片付け」を1本で行う。
# どんな終わり方（失敗・Ctrl+C）をしても、最後に試験専用の環境を止めてデータを消す。
# 開発用の環境（docker-compose.yml・ポート 8081）には触れない。
#
# 使い方（リポジトリ直下で。Git Bash を想定）:
#   ./e2e/run-e2e.sh                         # すべて（本物のタイルを使うテストを除く）
#   ./e2e/run-e2e.sh specs/map.spec.ts       # 一部だけ（引数はそのまま Playwright に渡す）
#   E2E_REAL_TILES=1 ./e2e/run-e2e.sh        # 本物の地図タイルを読み込むテストも流す
#   KEEP=1 ./e2e/run-e2e.sh                  # 終わっても環境を残す（画面で中を見たいとき）
#
# 必要なもの：Docker Desktop、Node.js（初回は e2e/README.md の準備を行う）

set -euo pipefail

KEEP="${KEEP:-0}"
BASE_URL="http://localhost:8083"

cd "$(dirname "${BASH_SOURCE[0]}")/.."

compose() { docker compose -f docker-compose.e2e.yml "$@"; }
log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

if [ ! -d e2e/node_modules/@playwright/test ]; then
  echo "Playwright が入っていません。先に準備してください（e2e/README.md）:" >&2
  echo "  cd e2e && npm ci && npx playwright install chromium" >&2
  exit 1
fi

# 試験のたびに使い捨ての APP_KEY を作る（リポジトリやファイルには残さない）。
E2E_APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
export E2E_APP_KEY

cleanup() {
  local code=$?
  if [ "$KEEP" = "1" ]; then
    log "環境を残しました（$BASE_URL）。止めるとき：docker compose -f docker-compose.e2e.yml down -v"
  else
    log "試験専用の環境を止めて、データを消します"
    compose down -v --remove-orphans >/dev/null 2>&1 || true
  fi
  exit "$code"
}
trap cleanup EXIT INT TERM

log "前回の試験環境が残っていれば片付けます"
compose down -v --remove-orphans >/dev/null 2>&1 || true

log "試験専用の環境を起動します（本番と同じイメージをビルド）"
compose up -d --build --wait

log "試験データを投入します"
# Git Bash がコンテナ内のパスを Windows のパスに書き換えないよう、この呼び出しだけで止める。
MSYS_NO_PATHCONV=1 compose exec -T php php artisan db:seed --class=E2eDataSeeder --force

# 起動直後はポートの準備が間に合わないことがあるため、画面が返るまで待つ。
for _ in $(seq 1 30); do
  if [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/login" || true)" = "200" ]; then break; fi
  sleep 1
done

log "Playwright を実行します（$BASE_URL）"
set +e
(cd e2e && E2E_BASE_URL="$BASE_URL" npx playwright test "$@")
RESULT=$?
set -e

if [ "$RESULT" -eq 0 ]; then
  log "すべて成功しました。"
else
  log "失敗したテストがあります（終了コード $RESULT）。画面の写真と操作の記録：cd e2e && npm run report"
fi
exit "$RESULT"
