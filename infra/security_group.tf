# 既存 EC2 セキュリティグループ（famigo-sg-ec2）への 8081 インバウンド許可の「追加」。
# インフラ設計書 §3.5。
#
# ★famigo 無影響の要：
#   aws_vpc_security_group_ingress_rule は「1本のインバウンドルール」だけを表す
#   リソース。SG 本体（aws_security_group）を Terraform 管理下に置かないため、
#   既存の 8080 許可や SSH 許可など famigo のルールには一切触れず、
#   このルール1本を足すだけで済む（削除・上書きのリスクがない）。
#
#   送信元は IP ではなく ALB の SG を指定する（インフラ設計書のSG設計思想）。
#   これにより「ALB を経由した通信だけが 8081 に到達できる」を安全に表現する。

resource "aws_vpc_security_group_ingress_rule" "ec2_allow_alb_8081" {
  security_group_id            = data.aws_security_group.famigo_ec2.id
  referenced_security_group_id = data.aws_security_group.famigo_alb.id
  from_port                    = var.app_port
  to_port                      = var.app_port
  ip_protocol                  = "tcp"
  description                  = "Civil Track: allow ALB to reach app port ${var.app_port}"

  tags = {
    Name = "${var.name_prefix}-ec2-ingress-${var.app_port}"
  }
}
