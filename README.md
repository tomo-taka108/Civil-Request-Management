# Civil Request Management（土木要望管理システム）

土木事務所向けに、道路・河川・砂防施設に関する苦情・要望・異常箇所を一元管理するシステム。

- 技術スタック：PHP / Laravel（Blade）+ MySQL + Leaflet（国土地理院地図タイル）
- 設計ドキュメント：[要件定義書](docs/requirements.md) / [データベース設計書](docs/database-design.md) / [画面設計書](docs/screen-design.md) / [インフラ設計書](docs/infrastructure-design.md)
- 画面モックアップ：[`mockup/`](mockup/)

---

## ローカル開発環境の構築

ローカルは Docker Compose で **nginx + php-fpm + mysql** の3コンテナ構成で動かす
（本番の Nginx + PHP-FPM 構成に揃える。[インフラ設計書 3.2節](docs/infrastructure-design.md) 参照）。

### 前提

- Docker Desktop（Docker Compose v2）
- ホストに PHP / Composer は不要（すべてコンテナ内で実行する）

### 手順（初回）

```powershell
# 1. 環境変数ファイルを作成
Copy-Item .env.example .env

# 2. イメージをビルドしてコンテナを起動
docker compose build
docker compose up -d

# 3. PHP依存関係をインストール（vendor は名前付きボリュームに入る）
docker compose exec php composer install

# 4. アプリケーションキーを生成
docker compose exec php php artisan key:generate

# 5. マイグレーション＋初期データ投入
docker compose exec php php artisan migrate --seed
```

### 動作確認

ブラウザで <http://localhost:8081> を開く（Laravel のウェルカムページが表示される）。

| サービス | ホスト側ポート | 用途 |
|---|---|---|
| nginx | 8081 | アプリケーション（ブラウザからアクセス） |
| mysql | 3307 | DBクライアントからの直接接続用（コンテナ内では 3306） |

### 初期管理者アカウント

初期構築時、seeder が初期システム管理者を1件作成する。

| 項目 | 値 |
|---|---|
| ユーザーID | `admin` |
| 初期パスワード | `ChangeMe#2026` |

> **初期パスワードは初回ログイン時に必ず変更すること。** 本番環境に投入する場合も、
> この初期パスワードのまま運用しないこと（初回ログイン時の強制変更で担保する予定）。

---

## よく使うコマンド

```powershell
# artisan（コンテナ内で実行）
docker compose exec php php artisan <command>

# DBをまっさらにして作り直し＋初期データ投入
docker compose exec php php artisan migrate:fresh --seed

# コンテナ停止（DBデータは名前付きボリュームに残る）
docker compose down

# コンテナ停止＋ボリューム削除（DBを完全にリセットしたいとき）
docker compose down -v
```

---

## メモ：ローカル環境の設計判断

- **`vendor/` と `storage/framework` は名前付きボリューム**に載せている。
  Windows + Docker Desktop ではバインドマウント経由の大量ファイル I/O が極端に遅く、
  1リクエストに数秒〜十数秒かかるため（`docker-compose.yml` のコメント参照）。
  そのため `composer install` は「ホストではなくコンテナ内の vendor ボリューム」に対して実行する。
- **セッション／キャッシュ／キューは file / sync 駆動**（`.env` の `SESSION_DRIVER` 等）。
  DBには `offices` / `users` / `requests` の3テーブルのみを持ち、
  Laravel 標準の sessions / cache / jobs テーブルは作らない。
- **文字コード・照合順序は `utf8mb4` / `utf8mb4_ja_0900_as_cs`** で本番（famigo-mysql 相乗り）と揃える
  （[インフラ設計書 3.1節](docs/infrastructure-design.md)）。
