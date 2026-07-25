---
description: ローカル開発環境（Docker Compose：nginx + php-fpm + mysql）の起動・停止・DBリセット・トラブルシュート手順。アプリが起動しない/500エラー/ポート競合/テストDB不足などの対処を含む。
allowed-tools: Bash
disable-model-invocation: false
---

# 開発環境（Docker Compose）操作手順

このプロジェクトは nginx + php-fpm + mysql の3コンテナ構成（`docker-compose.yml`）。
ホストに PHP/Composer は無く、すべてコンテナ内で実行する。ブラウザは http://localhost:8081 。

## 起動・停止

```bash
# 起動（バックグラウンド）
docker compose up -d

# 状態確認（3サービスとも running / mysql は healthy になっていること）
docker compose ps

# 停止（DBデータは名前付きボリュームに残る）
docker compose down

# 停止＋ボリューム削除（DBを完全リセットしたいときのみ。開発データも消える）
docker compose down -v
```

## 初回セットアップ / コンテナ再作成後

`vendor/` と `storage/framework` は名前付きボリューム。`docker compose down -v` で
ボリュームを消すと空になるため、再構築時は composer install が必要。

```bash
docker compose build
docker compose up -d
docker compose exec php composer install       # vendor ボリュームへ再インストール
cp .env.example .env                            # .env が無い場合
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
```

## DBリセット（開発データを初期状態に戻す）

```bash
docker compose exec php php artisan migrate:fresh --seed
```

- 初期管理者は `admin` / `ChangeMe#2026`（初回ログイン時に変更を強制される）

## よくあるトラブルと対処

### アプリが 500 エラー / ページが返らない

```bash
docker compose logs php --tail=50      # PHP側のエラーを確認
docker compose exec php sh -c 'tail -c 2000 storage/logs/laravel.log'
```

- 過去の事例：`SESSION_DRIVER=database` なのに sessions テーブルが無く500 →
  本プロジェクトは `SESSION_DRIVER=file`（DB非依存）にしている。`.env` を確認
- 設定変更が効かないときはキャッシュをクリア:

```bash
docker compose exec php php artisan config:clear
docker compose exec php php artisan route:clear
docker compose exec php php artisan view:clear
```

### 動作が極端に遅い（1リクエスト数秒〜十数秒）

- Windows + Docker Desktop のバインドマウント I/O 遅延が原因。
  `vendor/` と `storage/framework` を名前付きボリュームに載せて回避済み
  （`docker-compose.yml` 参照）。Docker Desktop の WSL2 バックエンドが
  有効か確認する

### ポート競合（8081 / 3307 が使用中）

- nginx=8081、mysql=3307（ホスト側）を使用。競合する場合は `docker-compose.yml` の
  `ports` を空きポートに変更してから `docker compose up -d`
- 何が使っているか確認: `netstat -ano | findstr :8081`（PowerShell）

### テスト用DBが無い（PHPUnit実行時のDBエラー）

- テストは `civil_request_management_testing` を使う。`docker/mysql/init.sql` が
  初回起動時に自動作成するが、既存コンテナに手動で作る場合は quality-check スキルの
  Step 3 のコマンドを参照

## コンテナ内でのartisan / composer実行（共通）

```bash
docker compose exec php php artisan <command>
docker compose exec php composer <command>
```
