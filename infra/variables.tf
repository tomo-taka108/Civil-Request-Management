# 変数定義。
# 既存 famigo リソースの識別子（インフラ設計書 §2 の実地調査結果）は
# 変更しない前提の定数的な値だが、環境差異や将来の付け替えに備えて変数化する。
# 実値のうち機密でないものは default を与え、そのまま validate/plan できるようにする。

variable "aws_region" {
  description = "AWS リージョン（famigo 環境と同じ東京）"
  type        = string
  default     = "ap-northeast-1"
}

# ------------------------------------------------------------
# 既存 famigo リソース（data source で参照する。新規作成はしない）
# 値の出所：インフラ設計書 §2（2026-07-20 AWS CLI 実地調査）
# ------------------------------------------------------------

variable "famigo_vpc_id" {
  description = "既存 famigo VPC の ID"
  type        = string
  default     = "vpc-0cc705e872edbf064"
}

variable "famigo_alb_name" {
  description = "既存 famigo ALB の名前（internet-facing）"
  type        = string
  default     = "famigo-alb"
}

variable "famigo_ec2_instance_id" {
  description = "既存 famigo EC2 インスタンスの ID"
  type        = string
  default     = "i-0dcf0d521ec4ae906"
}

variable "famigo_ec2_sg_id" {
  description = "既存 EC2 セキュリティグループの ID（famigo-sg-ec2）。8081 許可を追加する対象"
  type        = string
  default     = "sg-0bfa7f20eb86ee312"
}

variable "famigo_alb_sg_id" {
  description = "既存 ALB セキュリティグループの ID（famigo-sg-alb）。EC2 SG の 8081 許可の送信元に指定する"
  type        = string
  default     = "sg-06f4122a083fa07b8"
}

variable "famigo_alb_https_listener_port" {
  description = "既存 ALB の HTTPS リスナーのポート（443）。data で ARN を引くために使う"
  type        = number
  default     = 443
}

# ------------------------------------------------------------
# 本システムが新規作成するリソースのパラメータ
# ------------------------------------------------------------

variable "app_port" {
  description = "本システムのコンテナ（Nginx）が EC2 上で待ち受けるポート"
  type        = number
  default     = 8081
}

variable "health_check_path" {
  description = "ターゲットグループのヘルスチェックパス（Laravel 側に実装済みの /health）"
  type        = string
  default     = "/health"
}

variable "app_domain_name" {
  description = <<-EOT
    本システム用に新規取得する独自ドメイン名（例：civil-track.example）。
    ホストベースルーティング・ACM 証明書・Route53 ホストゾーンに使う。
    ドメイン取得後に terraform.tfvars で実値を設定する（未取得の間は空文字）。
  EOT
  type        = string
  default     = ""
}

variable "name_prefix" {
  description = "本システムが作る新規リソース名の接頭辞"
  type        = string
  default     = "civil-request"
}
