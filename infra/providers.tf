# AWS プロバイダ設定。
# リージョンは famigo 環境と同じ ap-northeast-1（東京）。
# ALB に載せる ACM 証明書は ALB と同一リージョンで発行するため、
# CloudFront 用の us-east-1 エイリアスは不要（本システムは CloudFront を使わない）。

provider "aws" {
  region = var.aws_region

  # 本システムが新規作成する全リソースに共通タグを付け、
  # famigo のリソースと視覚的に区別できるようにする（コンソール・請求分析での識別用）。
  default_tags {
    tags = {
      Project   = "civil-request-management"
      System    = "Civil Track"
      ManagedBy = "Terraform"
    }
  }
}
