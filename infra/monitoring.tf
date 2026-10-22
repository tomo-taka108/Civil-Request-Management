# ログの集約と監視（ログ・監視設計書 7章・8章）。
#
# - コンテナのログ（nginx・Laravel）は Docker の awslogs ドライバで CloudWatch Logs に送る
#   （docker-compose.prod.yml の logging）。EC2 のディスクには溜めない。
# - 異常があればメールで知らせる：①エラーログの発生 ②サイト停止 ③ディスク逼迫
#
# famigo と共用の EC2 ロールには、本システム用のインラインポリシーを「1枚追加」するだけ
# （s3_photos.tf と同じ方式。famigo 用の既存ポリシーには触れない）。

locals {
  # 自作メトリクスの名前空間。IAM の PutMetricData 権限もこの名前空間に限る。
  metric_namespace = "CivilTrack"
}

# ------------------------------------------------------------
# ログの保管先
# ------------------------------------------------------------

# 本システム専用のロググループ。nginx・php の2つのログストリームが入る。
# 保持期間を過ぎたログは自動で消える（無期限にすると保管料が積み上がるため）。
resource "aws_cloudwatch_log_group" "app" {
  name              = var.log_group_name
  retention_in_days = var.log_retention_days
}

# ------------------------------------------------------------
# EC2 からの書き込み権限
# ------------------------------------------------------------

resource "aws_iam_role_policy" "civil_monitoring" {
  name   = "CivilTrackLogsAndMetrics"
  role   = data.aws_iam_instance_profile.famigo_ec2.role_name
  policy = data.aws_iam_policy_document.civil_monitoring.json
}

data "aws_iam_policy_document" "civil_monitoring" {
  # Docker（awslogs ドライバ）がログストリームを作り、ログを書き込む。
  # 対象は本システムのロググループに限る。
  statement {
    sid = "WriteAppLogs"
    actions = [
      "logs:CreateLogStream",
      "logs:PutLogEvents",
    ]
    resources = ["${aws_cloudwatch_log_group.app.arn}:*"]
  }

  # EC2 上の定期実行（civil-disk-metric.timer）がディスク使用率を送る。
  # PutMetricData はリソースを指定できないため、名前空間で範囲を限る。
  statement {
    sid       = "PutCivilMetrics"
    actions   = ["cloudwatch:PutMetricData"]
    resources = ["*"]

    condition {
      test     = "StringEquals"
      variable = "cloudwatch:namespace"
      values   = [local.metric_namespace]
    }
  }
}

# ------------------------------------------------------------
# 通知先（メール）
# ------------------------------------------------------------

resource "aws_sns_topic" "alerts" {
  name = "${var.name_prefix}-alerts"
}

# メールアドレスは terraform.tfvars で設定する（コミットしない）。空なら購読を作らない。
# 作成後に AWS から確認メールが届くので、本文のリンクを押すと通知が届くようになる。
resource "aws_sns_topic_subscription" "alerts_email" {
  count = var.alert_email == "" ? 0 : 1

  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

# ------------------------------------------------------------
# ① エラーログの発生
# ------------------------------------------------------------

# Laravel の JSON ログのうち、レベルが ERROR（400）以上の行を数える。
# nginx の行には level が無いため数えられない。
resource "aws_cloudwatch_log_metric_filter" "app_errors" {
  name           = "${var.name_prefix}-app-errors"
  log_group_name = aws_cloudwatch_log_group.app.name
  pattern        = "{ $.level >= 400 }"

  metric_transformation {
    namespace     = local.metric_namespace
    name          = "AppErrorCount"
    value         = "1"
    default_value = "0"
  }
}

resource "aws_cloudwatch_metric_alarm" "app_errors" {
  alarm_name        = "${var.name_prefix}-app-errors"
  alarm_description = "アプリが ERROR 以上のログを出した。CloudWatch Logs の ${var.log_group_name} で level_name が ERROR の行を確認する（運用手順書）"

  namespace           = local.metric_namespace
  metric_name         = "AppErrorCount"
  statistic           = "Sum"
  period              = 300
  evaluation_periods  = 1
  comparison_operator = "GreaterThanOrEqualToThreshold"
  threshold           = 1
  # ログが1行も来ない間（EC2 停止中など）は正常とみなす。
  treat_missing_data = "notBreaching"

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]
}

# ------------------------------------------------------------
# ② サイト停止
# ------------------------------------------------------------

# ALB から見て正常なサーバーが 0 台の状態が2分続いたら知らせる。
# ヘルスチェック（/health）は PHP まで届くため、nginx・PHP・EC2 のどれが止まっても検知できる。
# EC2 を意図して停止したときも鳴る（運用手順書に記載）。
resource "aws_cloudwatch_metric_alarm" "site_down" {
  alarm_name        = "${var.name_prefix}-site-down"
  alarm_description = "ALB から見て正常なサーバーが0台。EC2 の状態・docker ps・/health を確認する（運用手順書）。EC2 を停止したときにも鳴る"

  namespace   = "AWS/ApplicationELB"
  metric_name = "HealthyHostCount"
  dimensions = {
    LoadBalancer = data.aws_lb.famigo.arn_suffix
    TargetGroup  = aws_lb_target_group.civil.arn_suffix
  }
  statistic           = "Minimum"
  period              = 60
  evaluation_periods  = 2
  comparison_operator = "LessThanThreshold"
  threshold           = 1
  treat_missing_data  = "breaching"

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]
}

# ------------------------------------------------------------
# ③ ディスク逼迫
# ------------------------------------------------------------

# EC2 のディスク使用率は AWS が自動では集めないため、EC2 上の定期実行が5分ごとに送る
# （infra/ec2/civil-disk-metric.*）。famigo と共用のディスクのため、famigo の巻き添えも防ぐ。
resource "aws_cloudwatch_metric_alarm" "disk_usage" {
  alarm_name        = "${var.name_prefix}-disk-usage"
  alarm_description = "EC2 のディスク使用率が ${var.disk_usage_threshold}% 以上。df -h / と docker system df を確認し、不要なイメージを docker image prune -a で消す（運用手順書・Issue #102）"

  namespace           = local.metric_namespace
  metric_name         = "DiskUsedPercent"
  statistic           = "Maximum"
  period              = 300
  evaluation_periods  = 1
  comparison_operator = "GreaterThanOrEqualToThreshold"
  threshold           = var.disk_usage_threshold
  # EC2 停止中は値が届かないが、異常ではない。
  treat_missing_data = "notBreaching"

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]
}
