---
description: Laravel（Pint整形・PHPStan静的解析・PHPUnitテスト）の品質チェックを実行する。エラーがあれば自動修正または修正して再チェックする。インフラ（Terraform）は infra/ 追加後に有効化する。
allowed-tools: Bash
disable-model-invocation: false
---

# 品質チェック手順

このプロジェクトの品質チェックは以下のステップで実施する。
すべて Docker コンテナ内で実行するため、コマンドは `docker compose exec php ...` の形になる
（ローカルに PHP/Composer は無い前提）。**コミット前に必ず実施すること。**

## Step 1: Pint（コードフォーマット）

```bash
docker compose exec -T php ./vendor/bin/pint --test
```

- **PASS** が目標
- フォーマット違反がある場合は以下で自動修正してから再チェックする:

```bash
docker compose exec -T php ./vendor/bin/pint
docker compose exec -T php ./vendor/bin/pint --test
```

- 設定は `pint.json`（Laravel プリセット。テストメソッドの日本語名を許容するため
  `php_unit_method_casing` は無効化している）
- `pint`（--test なし）はコードの動作を変えずに書式のみを修正する

## Step 2: PHPStan / Larastan（静的解析）

```bash
docker compose exec -T php ./vendor/bin/phpstan analyse --memory-limit=512M
```

- **[OK] No errors** が目標
- 設定は `phpstan.neon`（解析対象: app / database / routes、レベル 5）
- 型の不整合・存在しないメソッド呼び出し・未定義プロパティ等を検出する
- レベルはコードの成熟に合わせて段階的に上げてよい（`phpstan.neon` の `level`）

## Step 3: PHPUnit（自動テスト）

```bash
docker compose exec -T php php artisan test
```

- **すべて PASS** が目標
- テストは**テスト専用DB** `civil_request_management_testing` を使う（`phpunit.xml` で指定）。
  開発用DB `civil_request_management` は破壊されない
- テスト用DBが存在しない場合（コンテナを作り直した直後など）は、
  `docker/mysql/init.sql` が初回起動時に自動作成する。既存コンテナに手動で作る場合:

```bash
docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS civil_request_management_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_ja_0900_as_cs; GRANT ALL PRIVILEGES ON civil_request_management_testing.* TO '"'"'civil_request_app'"'"'@'"'"'%'"'"'; FLUSH PRIVILEGES;"'
```

## Step 4: インフラ（Terraform）※ infra/ 追加後に有効化

Terraform コード（`infra/`）はまだ存在しない。インフラ着手後、以下を有効化する。

```bash
# cd infra/envs/dev && terraform fmt -check -recursive -diff
# cd infra/envs/dev && terraform validate   # 初回は terraform init -backend=false が必要
```

- `terraform fmt` / `terraform validate` を必須とし、`tflint` はインストール時のみ任意で実行する
- `terraform validate` は AWS 上にリソースを作成しない静的チェックで、課金は発生しない

## 重要事項

- 品質チェックはコミット前に必ず実施する（最低でも Step 1〜3）
- 3つとも緑（Pint PASS / PHPStan No errors / PHPUnit all passed）になってからコミットする
