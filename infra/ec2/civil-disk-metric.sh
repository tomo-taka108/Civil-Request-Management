#!/bin/sh
# EC2 のルートディスク使用率（%）を CloudWatch のメトリクス CivilTrack/DiskUsedPercent に送る。
# civil-disk-metric.timer から5分ごとに呼ばれる（ログ・監視設計書 8章、infra/monitoring.tf の disk_usage アラーム）。
# 認証は EC2 の IAM ロール（CivilTrackLogsAndMetrics の PutCivilMetrics）。キーは使わない。
set -eu

used=$(df --output=pcent / | tail -n 1 | tr -dc '0-9')

aws cloudwatch put-metric-data \
  --region ap-northeast-1 \
  --namespace CivilTrack \
  --metric-name DiskUsedPercent \
  --unit Percent \
  --value "$used"
