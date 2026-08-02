# Terraform 本体・プロバイダのバージョン制約と、state の保存方式（backend）。
# インフラ設計書 §4・§5 に基づく。

terraform {
  required_version = ">= 1.9"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  # tfstate はローカル保存（インフラ設計書 §5 の残課題を「ローカル」で確定）。
  # 個人開発・1台での作業のため、まずはローカル state で十分。
  # state にはパスワード等が平文で入りうるため .gitignore でコミット除外している。
  # 将来、複数PC・複数人で作業する場合は S3 backend へ移行する。
  backend "local" {
    path = "terraform.tfstate"
  }
}
