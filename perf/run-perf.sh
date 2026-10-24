#!/usr/bin/env bash
#
# 負荷試験の実行スクリプト（パフォーマンステスト設計書 2章・5章）。
#
# 「試験専用の環境を起動 → データ投入 → k6 で計測 → 片付け」を1本で行う。
# どんな終わり方（失敗・Ctrl+C）をしても、最後に試験専用の環境を止めてデータを消す。
# 開発用の環境（docker-compose.yml・ポート 8081）には触れない。
#
# 使い方（リポジトリ直下で。Git Bash を想定）:
#   ./perf/run-perf.sh                                   # 普段の業務（30人）
#   SCENARIO=perf/scenarios/export.js ./perf/run-perf.sh # CSV 出力
#   PEAK_VUS=60 ./perf/run-perf.sh                       # 人数を変える
#   KEEP=1 ./perf/run-perf.sh                            # 終わっても環境を残す（画面で中を見たいとき）
#
# 必要なもの：Docker Desktop、k6（https://grafana.com/docs/k6/latest/set-up/install-k6/）

set -euo pipefail

# Git Bash がコンテナ内のパス（/var/...）を Windows のパスに書き換えないようにする。
export MSYS_NO_PATHCONV=1

SCENARIO="${SCENARIO:-perf/scenarios/daily.js}"
PEAK_VUS="${PEAK_VUS:-30}"
KEEP="${KEEP:-0}"
BASE_URL="http://localhost:8082"
RESULT_DIR="perf/results"

cd "$(dirname "${BASH_SOURCE[0]}")/.."

compose() { docker compose -f docker-compose.perf.yml "$@"; }
log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

if ! command -v k6 >/dev/null 2>&1; then
  echo "k6 が見つかりません。インストールしてから実行してください（perf/README.md）。" >&2
  exit 1
fi

# 試験のたびに使い捨ての APP_KEY を作る（リポジトリやファイルには残さない）。
PERF_APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
export PERF_APP_KEY

STATS_PID=""
cleanup() {
  local code=$?
  if [ -n "$STATS_PID" ]; then kill "$STATS_PID" 2>/dev/null || true; fi
  if [ "$KEEP" = "1" ]; then
    log "環境を残しました（$BASE_URL）。止めるとき：docker compose -f docker-compose.perf.yml down -v"
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

log "試験データを投入します（3年分・約3,000件）"
compose exec -T php php artisan db:seed --class=PerfDataSeeder --force

# 起動直後の1回目はキャッシュの準備で遅いことがあるため、計測の前に1度開いておく。
curl -s -o /dev/null "$BASE_URL/login"

mkdir -p "$RESULT_DIR"
STATS_FILE="$RESULT_DIR/$(basename "$SCENARIO" .js)-docker-stats.txt"
log "コンテナの CPU・メモリを5秒ごとに記録します（$STATS_FILE）"
: > "$STATS_FILE"
(
  while true; do
    docker stats --no-stream --format '{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}' \
      civil-perf-php-1 civil-perf-mysql-1 civil-perf-nginx-1 2>/dev/null \
      | sed "s/^/$(date +%H:%M:%S)\t/" >> "$STATS_FILE" || true
    sleep 5
  done
) &
STATS_PID=$!

log "k6 を実行します（$SCENARIO・最大 ${PEAK_VUS} 人）"
set +e
k6 run --quiet -e BASE_URL="$BASE_URL" -e PEAK_VUS="$PEAK_VUS" -e RESULT_DIR="$RESULT_DIR" "$SCENARIO"
K6_EXIT=$?
set -e

if [ "$K6_EXIT" -eq 0 ]; then
  log "すべての目安を満たしました。結果：$RESULT_DIR/"
else
  log "目安を満たさない項目がありました（k6 の終了コード $K6_EXIT）。結果：$RESULT_DIR/"
fi
exit "$K6_EXIT"
