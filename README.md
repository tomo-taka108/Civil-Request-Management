# Civil Request Management（土木要望管理システム）

土木事務所向けに、道路・河川・砂防施設に関する苦情・要望・異常箇所を一元管理するシステム。

- 技術スタック：PHP / Laravel（Blade）+ MySQL + Leaflet（国土地理院地図タイル）
- 設計ドキュメント：[要件定義書](docs/requirements.md) / [データベース設計書](docs/database-design.md) / [画面設計書](docs/screen-design.md) / [インフラ設計書](docs/infrastructure-design.md)
- 運用手順：[EC2/RDS の停止・再開](docs/operations-start-stop.md)
- 画面モックアップ：[`mockup/`](mockup/)

## インフラ構成

famigo 既存 AWS 環境（ALB・EC2・RDS）に相乗りする構成。詳細は [インフラ設計書](docs/infrastructure-design.md) を参照。

![要望管理システム インフラ構成図](docs/images/infrastructure-diagram.svg)

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

### サンプルデータ（体験用）

`migrate --seed`（または `db:seed`）で、体験・デモ用のサンプルデータも投入される。
一覧・検索・CSV出力などを「システムらしく」体験できるよう、3事務所 × 3部署に
サンプル案件を約30件、各事務所に一般職員を3名ずつ用意している
（`SampleStaffSeeder` / `SampleRequestSeeder`）。

- 氏名・団体名・地名はすべて**架空**（実在の個人・団体・市町村を指さない。CLAUDE.md 6章）。
- **冪等**：`db:seed` を複数回実行してもサンプル案件は二重登録されない。手動登録済みの
  案件があっても消さず、採番が衝突しないよう追加投入する。
- ローカル・本番共用（公開デモ時の初期データとしても利用する想定）。

サンプル一般職員のログイン情報（初期パスワードは共通・変更不要で体験できる）：

| ユーザーID | 氏名 | 所属 / 担当部署 | パスワード |
|---|---|---|---|
| `staff101` | 岡田 修 | みどり / 道路 | `Sample#2026` |
| `staff102` | 佐藤 花子 | みどり / 河川 | `Sample#2026` |
| `staff103` | 鈴木 一郎 | みどり / 砂防 | `Sample#2026` |
| `staff201`〜`staff203` | （つばき土木事務所の職員） | つばき / 道路・河川・砂防 | `Sample#2026` |
| `staff301`〜`staff303` | （こはく土木事務所の職員） | こはく / 道路・河川・砂防 | `Sample#2026` |

> 一般職員は**自事務所の案件のみ**閲覧でき、**自分の担当部署の案件のみ**編集・削除できる。
> 事務所スコープ・権限制御を体験するには、異なる事務所・部署の職員でログインして挙動を比較するとよい。

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

---

## ドキュメント用アセットの置き場（画像・動画）

README や設計書に貼る画像・図は、リポジトリ内の [`docs/images/`](docs/images/) に置く。

- **図・構成図**：SVG を第一候補とする（拡大しても鮮明・軽量・テキスト差分で修正しやすい）。
  例：[`docs/images/infrastructure-diagram.svg`](docs/images/infrastructure-diagram.svg)
- **スクリーンショット**：PNG を `docs/images/` に置く。

**動画（画面操作の録画など）はリポジトリにコミットしない。**
バイナリかつ容量が大きく、Git 履歴を肥大化させて clone を重くするため。
README に載せる場合は、以下のいずれかで「リポジトリ外に置いたものを参照」する。

- GitHub の Issue / PR のコメント欄に動画をドラッグ&ドロップ → 発行される URL を README から参照する
- GitHub Releases にファイルとして添付し、その URL を参照する

> 大容量バイナリを本格的に版管理したくなった場合は Git LFS の導入を検討する（現時点では未導入）。
