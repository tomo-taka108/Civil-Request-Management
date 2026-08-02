# Route53 ホストゾーン（新規）と、ドメイン → ALB を指す DNS レコード。
# インフラ設計書 §3.6：独自ドメインを新規取得し、そのホストゾーンを新規作成する。
# famigo の既存ゾーン（famigo-odekake.com）には一切触れない（別ゾーンを作るだけ）。
#
# app_domain_name が未設定（ドメイン未取得）の間は count=0 で何も作らないため、
# ドメイン取得前でも terraform validate / plan が通る。取得後に terraform.tfvars で
# app_domain_name を設定すると、これらのリソースが有効化される。

locals {
  domain_enabled = var.app_domain_name != ""
}

# 独自ドメインのホストゾーン。
# 取得したレジストラ側のネームサーバーに、このゾーンの NS レコード（下記 output）を
# 登録することで、名前解決がこのゾーンに向く（インフラ設計書 §3.6）。
resource "aws_route53_zone" "app" {
  count = local.domain_enabled ? 1 : 0
  name  = var.app_domain_name

  tags = {
    Name = "${var.name_prefix}-zone"
  }
}

# ドメイン（apex）→ ALB を指す A レコード（エイリアス）。
# ブラウザは名前解決でこのレコードを引き、ALB の在り処を知る。
resource "aws_route53_record" "app_alias" {
  count   = local.domain_enabled ? 1 : 0
  zone_id = aws_route53_zone.app[0].zone_id
  name    = var.app_domain_name
  type    = "A"

  alias {
    name                   = data.aws_lb.famigo.dns_name
    zone_id                = data.aws_lb.famigo.zone_id
    evaluate_target_health = false
  }
}

# ACM の DNS 検証用レコード（証明書の所有確認のため ACM が要求する CNAME）。
resource "aws_route53_record" "cert_validation" {
  for_each = local.domain_enabled ? {
    for dvo in aws_acm_certificate.app[0].domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      type   = dvo.resource_record_type
      record = dvo.resource_record_value
    }
  } : {}

  zone_id = aws_route53_zone.app[0].zone_id
  name    = each.value.name
  type    = each.value.type
  records = [each.value.record]
  ttl     = 60
}
