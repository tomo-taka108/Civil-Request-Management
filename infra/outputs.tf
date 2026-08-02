# apply 後に必要になる値の出力。

# ドメインのレジストラ側に登録するネームサーバー。
# ドメイン取得後、レジストラ（お名前.com 等）のネームサーバー設定に
# この4つを登録すると、名前解決が本ホストゾーンに向く（インフラ設計書 §3.6）。
output "route53_name_servers" {
  description = "レジストラに登録するネームサーバー（ドメイン設定時のみ）"
  value       = local.domain_enabled ? aws_route53_zone.app[0].name_servers : []
}

output "target_group_arn" {
  description = "本システムのターゲットグループ ARN"
  value       = aws_lb_target_group.civil.arn
}

output "target_group_name" {
  description = "本システムのターゲットグループ名"
  value       = aws_lb_target_group.civil.name
}

output "alb_dns_name" {
  description = "相乗り先 ALB の DNS 名（動作確認・レコード設定の参考用）"
  value       = data.aws_lb.famigo.dns_name
}

output "app_url" {
  description = "本システムの公開 URL（ドメイン設定時のみ）"
  value       = local.domain_enabled ? "https://${var.app_domain_name}" : "(app_domain_name 未設定)"
}
