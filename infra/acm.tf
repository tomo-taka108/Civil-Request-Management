# ACM 証明書（新規）。HTTPS 通信のための「身分証明書」。
# ALB の HTTPS リスナールールに載せて使う。ALB と同一リージョン（東京）で発行する。
# インフラ設計書 §3.5・§3.6。DNS 検証を用いる（検証レコードは route53.tf 側で作成）。
#
# app_domain_name 未設定の間は count=0。ドメイン取得後に有効化される。

resource "aws_acm_certificate" "app" {
  count             = local.domain_enabled ? 1 : 0
  domain_name       = var.app_domain_name
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }

  tags = {
    Name = "${var.name_prefix}-cert"
  }
}

# DNS 検証の完了を待って「検証済み証明書」を表す。
# リスナールール（listener_rule.tf）はこの検証済み ARN を参照する。
resource "aws_acm_certificate_validation" "app" {
  count                   = local.domain_enabled ? 1 : 0
  certificate_arn         = aws_acm_certificate.app[0].arn
  validation_record_fqdns = [for record in aws_route53_record.cert_validation : record.fqdn]
}
