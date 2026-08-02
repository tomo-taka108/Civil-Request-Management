# infra/ — Terraform（famigo 相乗りデプロイ）

本システム（Civil Track）を famigo 既存 AWS 環境に相乗りさせるためのインフラを Terraform で管理する。
方針・背景は [インフラ設計書](../docs/infrastructure-design.md) を参照。

## このコードが作るもの / 作らないもの

**新規作成する（`resource`）**

| リソース | ファイル | ドメイン要否 |
|---|---|---|
| ターゲットグループ（8081, /health） | `target_group.tf` | 不要 |
| TG への EC2 登録 | `target_group.tf` | 不要 |
| EC2 SG への 8081 インバウンド許可（1本追加） | `security_group.tf` | 不要 |
| ACM 証明書（DNS 検証） | `acm.tf` | 必要 |
| Route53 ホストゾーン・A レコード・検証レコード | `route53.tf` | 必要 |
| ALB 443 リスナーへのホストベースルール追加・証明書追加 | `listener_rule.tf` | 必要 |

**作らない（既存 famigo を `data` 参照するだけ・`data.tf`）**：VPC / ALB 本体 / ALB リスナー / EC2 本体 / 既存 SG。

### famigo 無影響の設計

- 既存 SG には **1 本のルールを追加**するだけ（`aws_vpc_security_group_ingress_rule`）。SG 本体を管理下に置かないため既存ルールに触れない。
- 既存 443 リスナーには **ルールと証明書を追加**するだけ。デフォルトアクション（famigo-tg-8080 への forward）は不変。ホスト名が本システムのドメインに一致した通信だけを本システムへ振り分ける。
- Route53 は **別ドメインの別ホストゾーンを新規作成**。famigo の既存ゾーンには触れない。
- famigo リソースは一切 `import` しない（`data` 参照のみ）。

## state（tfstate）について

ローカル保存（`backend "local"`）。`terraform.tfstate` は機密を含みうるため **`.gitignore` でコミット除外**している。1 台での個人開発を前提とする。将来、複数 PC・複数人で作業する場合は S3 backend へ移行する。

## 使い方

### 1. 静的チェック（AWS 不要・課金なし）

```bash
cd infra
terraform init -backend=false   # プロバイダ取得のみ
terraform fmt -recursive -check
terraform validate
```

### 2. AWS 認証（実行前に必要）

`aws configure`（または SSO 等）で認証を通す。`aws sts get-caller-identity` で確認。

### 3. ドメイン取得前でも作れる部分だけ先に適用

`app_domain_name` を空のままにすると、ターゲットグループと SG ルールのみ作成される（ACM・Route53・リスナールールは作られない）。

```bash
terraform init          # ローカル state を初期化
terraform plan          # 作成内容を確認
terraform apply         # 適用
```

### 4. ドメイン取得後に全体を有効化

1. 独自ドメインを取得（レジストラは任意。インフラ設計書 §3.6）。
2. `terraform.tfvars` を作成し、取得したドメインを設定：

   ```bash
   cp terraform.tfvars.example terraform.tfvars
   # terraform.tfvars に app_domain_name = "取得したドメイン" を記入
   ```

3. `terraform apply` を実行。ACM 証明書・ホストゾーン・リスナールールが作られる。
4. 出力される `route53_name_servers`（4 つ）を、レジストラのネームサーバー設定に登録する。
5. DNS 伝播後、ACM の DNS 検証が自動完了し、`https://<ドメイン>` で本システムに到達できる。

### 主な出力（`terraform output`）

- `route53_name_servers`：レジストラに登録するネームサーバー
- `target_group_arn` / `target_group_name`：ターゲットグループ
- `app_url`：公開 URL（ドメイン設定時）

## 注意

- EC2・RDS へのアプリ配置（コンテナ起動）・RDS スキーマ作成は Terraform の管轄外（インフラ設計書 §3.1・§3.2）。デプロイ手順は設計書 §6 を参照。
- 適用は famigo 稼働への影響を考え、まず `terraform plan` で差分を必ず確認してから行う。
