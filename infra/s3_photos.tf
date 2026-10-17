# 現場写真（要件定義書 §2.8）の保存先 S3 バケットと、EC2 からの書き込み権限。
# インフラ設計書 §3.9。
#
# 本番の EC2 ではコンテナを作り直すとローカルのファイルが消えるため、写真は S3 に置く。
# アプリは EC2 のインスタンスプロファイル（IAM ロール）の権限で S3 にアクセスし、
# アクセスキーは一切使わない。表示は期限付きの署名付きURLで行うため、バケット自体は完全に非公開にする。

# EC2 に実際に付いているインスタンスプロファイル（famigo と共用）。
# ロール名を決め打ちせず EC2 から辿ることで、権限を付ける先を取り違えない。
data "aws_iam_instance_profile" "famigo_ec2" {
  name = data.aws_instance.famigo.iam_instance_profile
}

# バケット名は全世界で一意である必要がある。他のリソースと揃えて name_prefix を付ける。
resource "aws_s3_bucket" "photos" {
  bucket = "${var.name_prefix}-photos"

  tags = {
    Name = "${var.name_prefix}-photos"
  }
}

# 公開アクセスを全面的に禁止する（設定ミスで写真が公開されるのを防ぐ最後の砦）。
resource "aws_s3_bucket_public_access_block" "photos" {
  bucket = aws_s3_bucket.photos.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# ACL（オブジェクト単位のアクセス設定）を無効にし、権限はバケットポリシーと IAM だけで決める。
resource "aws_s3_bucket_ownership_controls" "photos" {
  bucket = aws_s3_bucket.photos.id

  rule {
    object_ownership = "BucketOwnerEnforced"
  }
}

# 保存時に自動で暗号化する（S3 管理の鍵。追加料金なし）。
resource "aws_s3_bucket_server_side_encryption_configuration" "photos" {
  bucket = aws_s3_bucket.photos.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# バージョニングは有効にしない。写真の削除は「誤登録・個人情報の写り込み」を取り除くための
# 物理削除（要件定義書 §2.8）であり、旧バージョンとして残ると削除の目的を果たせないため。

# HTTPS 以外での通信を拒否する。
resource "aws_s3_bucket_policy" "photos" {
  bucket = aws_s3_bucket.photos.id
  policy = data.aws_iam_policy_document.photos_bucket.json

  # 公開アクセスブロックの適用後にポリシーを付ける（順序によっては作成に失敗するため）。
  depends_on = [aws_s3_bucket_public_access_block.photos]
}

data "aws_iam_policy_document" "photos_bucket" {
  statement {
    sid     = "DenyInsecureTransport"
    effect  = "Deny"
    actions = ["s3:*"]
    resources = [
      aws_s3_bucket.photos.arn,
      "${aws_s3_bucket.photos.arn}/*",
    ]

    principals {
      type        = "*"
      identifiers = ["*"]
    }

    condition {
      test     = "Bool"
      variable = "aws:SecureTransport"
      values   = ["false"]
    }
  }
}

# EC2 のロールに、このバケットの写真だけを扱う権限を「1枚追加」する。
# aws_iam_role_policy はこのインラインポリシー1枚だけを管理し、ロールに付いている
# famigo 用の既存ポリシー（AmazonSSMManagedInstanceCore / ReadBackendArtifactFromS3）には触れない。
# （aws_iam_role でロール全体を管理すると、書いていない既存ポリシーを消してしまうため使わない。）
resource "aws_iam_role_policy" "civil_photos" {
  name   = "CivilTrackPhotosS3Access"
  role   = data.aws_iam_instance_profile.famigo_ec2.role_name
  policy = data.aws_iam_policy_document.civil_photos.json
}

data "aws_iam_policy_document" "civil_photos" {
  # 写真の保存・取得（署名付きURLの発行に必要）・削除。アプリが写真を置く
  # request-photos/ 配下に限る。
  statement {
    sid = "ReadWriteRequestPhotos"
    actions = [
      "s3:PutObject",
      "s3:GetObject",
      "s3:DeleteObject",
    ]
    resources = ["${aws_s3_bucket.photos.arn}/${var.photo_key_prefix}/*"]
  }

  # 一覧の取得。これが無いと、存在しない写真を確認したときに「見つからない（404）」ではなく
  # 「権限なし（403）」が返り、アプリ側で区別できない。範囲は同じく request-photos/ 配下に限る。
  statement {
    sid       = "ListRequestPhotos"
    actions   = ["s3:ListBucket"]
    resources = [aws_s3_bucket.photos.arn]

    condition {
      test     = "StringLike"
      variable = "s3:prefix"
      values   = ["${var.photo_key_prefix}/*"]
    }
  }
}
