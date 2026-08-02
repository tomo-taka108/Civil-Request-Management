# 本システム用のターゲットグループ（新規）。
# ALB がリクエストを転送する「送り先サーバー群の名簿」。
# EC2 上の本システムコンテナ（Nginx, 8081）を登録し、/health でヘルスチェックする。
# インフラ設計書 §3.5。

resource "aws_lb_target_group" "civil" {
  name        = "${var.name_prefix}-tg-${var.app_port}"
  port        = var.app_port
  protocol    = "HTTP"
  vpc_id      = data.aws_vpc.famigo.id
  target_type = "instance"

  # ヘルスチェック：Laravel 側に実装済みの /health が 200 を返すか定期確認する。
  # 200 以外・無応答なら unhealthy 判定となり、ALB は振り分け対象から外す。
  health_check {
    enabled             = true
    path                = var.health_check_path
    port                = "traffic-port"
    protocol            = "HTTP"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 3
    unhealthy_threshold = 3
  }

  tags = {
    Name = "${var.name_prefix}-tg-${var.app_port}"
  }
}

# ターゲットグループに EC2 インスタンスを登録する。
# target_type = "instance" のため、ALB は EC2 の app_port(8081) へ転送する。
resource "aws_lb_target_group_attachment" "civil" {
  target_group_arn = aws_lb_target_group.civil.arn
  target_id        = data.aws_instance.famigo.instance_id
  port             = var.app_port
}
