# 既存 famigo リソースの参照（data source）。
# これらは「読むだけ」で、Terraform は一切変更・削除しない。
# 新規リソースが依存する ID / ARN をここから取得する。
# インフラ設計書 §4.1：famigo 既存リソースは原則 data source 参照。

# VPC（新規ターゲットグループの配置先 VPC として ID を使う）
data "aws_vpc" "famigo" {
  id = var.famigo_vpc_id
}

# ALB 本体（リスナーを引くため、および出力・確認用）
data "aws_lb" "famigo" {
  name = var.famigo_alb_name
}

# ALB の HTTPS(443) リスナー。
# aws_lb_listener_rule は listener_arn（文字列）を取るだけで、その ARN の発行元が
# resource か data かを区別しない。よって既存リスナーを import せずに、
# ここで ARN を参照してルールだけ追加できる（インフラ設計書 §4.1）。
data "aws_lb_listener" "famigo_https" {
  load_balancer_arn = data.aws_lb.famigo.arn
  port              = var.famigo_alb_https_listener_port
}

# EC2 インスタンス（ターゲットグループへ登録する対象）
data "aws_instance" "famigo" {
  instance_id = var.famigo_ec2_instance_id
}

# 既存 EC2 セキュリティグループ（このグループに 8081 インバウンド許可を追加する）
data "aws_security_group" "famigo_ec2" {
  id = var.famigo_ec2_sg_id
}

# 既存 ALB セキュリティグループ（EC2 SG の 8081 許可で、送信元として参照する）
data "aws_security_group" "famigo_alb" {
  id = var.famigo_alb_sg_id
}
