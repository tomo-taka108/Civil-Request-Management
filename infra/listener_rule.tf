# 既存 ALB の HTTPS(443) リスナーへの「ルール追加」と「証明書の追加」。
# インフラ設計書 §3.5。
#
# ★famigo 無影響の要：
#   - aws_lb_listener_rule は既存リスナーに「1本のルールを足す」リソース。
#     既存のデフォルトアクション（famigo-tg-8080 への forward）には一切触れない。
#   - aws_lb_listener_certificate は SNI 用に証明書を「追加」する。既存 famigo の
#     証明書（api.famigo-odekake.com）はそのまま残り、本システムの証明書が併存する。
#   - ルールは「ホスト名 = 本システムのドメイン」に一致したものだけを本システムの
#     ターゲットグループへ送る。ホストが一致しなければ従来どおりデフォルト（famigo）へ。
#
# app_domain_name 未設定の間は count=0。ドメイン取得後に有効化される。

# 本システムのドメイン宛て HTTPS で使う証明書を、既存 443 リスナーに追加する。
resource "aws_lb_listener_certificate" "app" {
  count           = local.domain_enabled ? 1 : 0
  listener_arn    = data.aws_lb_listener.famigo_https.arn
  certificate_arn = aws_acm_certificate_validation.app[0].certificate_arn
}

# ホストベースルーティングのルール（新規追加）。
# priority は既存ルールと衝突しない値を選ぶ。famigo 側は現状デフォルトのみ
# （ルール分岐なし）だが、将来の追加余地を空けて 100 とする。
resource "aws_lb_listener_rule" "app_host" {
  count        = local.domain_enabled ? 1 : 0
  listener_arn = data.aws_lb_listener.famigo_https.arn
  priority     = 100

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.civil.arn
  }

  condition {
    host_header {
      values = [var.app_domain_name]
    }
  }

  tags = {
    Name = "${var.name_prefix}-host-rule"
  }
}
