# 運用手順：EC2 / RDS の停止・再開

コスト縮減のため、使わない期間は EC2・RDS を手動停止する。次回スムーズに再開するための手順。
本システムは famigo 既存環境（EC2 `famigo-ec2` / RDS `famigo-mysql`）に相乗りしている（[インフラ設計書](infrastructure-design.md) 参照）。

最終更新: 2026-08-04（本番公開 2026-08-02）

---

## 対象リソース

| リソース | 識別子 | 備考 |
|---|---|---|
| EC2 | `i-0dcf0d521ec4ae906`（`famigo-ec2`, t3.micro） | **famigo と共用**。停止すると famigo も止まる |
| RDS | `famigo-mysql`（MySQL 8.4） | **famigo と共用**。停止すると famigo の DB も止まる |
| 公開URL | https://civil-track.com | ALB 経由。ALB は停止しない（稼働課金は famigo 既存分） |

> ⚠️ **famigo への影響**：EC2・RDS はともに famigo と共用。停止すると **famigo も同時に停止**する。両システムとも使わない期間にのみ停止すること。

---

## ⚠️ 重要な前提・注意

- **RDS は最大 7 日で自動再起動する**（AWS 仕様）。7 日以上止めておきたい場合は、7 日ごとに再度停止する必要がある。
- **ALB は停止しない**（停止機能がない）。ただし ALB の稼働課金は famigo の既存コストで、本システムの相乗りで増える分ではない（[インフラ設計書 3.8](infrastructure-design.md)）。
- **コンテナは `restart: unless-stopped` 設定**（`docker-compose.prod.yml`）。EC2 再開時に Docker が起動すれば、コンテナも**自動で復帰する見込み**。ただし停止のされ方によっては手動起動が要る場合があるため、再開後は必ずヘルスチェックで確認する。
- EC2 への接続は **SSM Session Manager**（AWS コンソール → EC2 → 対象インスタンス → 「接続」→「セッションマネージャー」）。SSH 鍵は不要。

---

## 停止手順（AWS コンソール操作）

コスト縮減のため止める。**両システム（Civil Track・famigo）を使わないことを確認してから**行う。

### 1. EC2 を停止
1. AWS コンソール → **EC2** → インスタンス一覧
2. `famigo-ec2`（`i-0dcf0d521ec4ae906`）を選択
3. 「**インスタンスの状態**」→「**インスタンスを停止**」
4. 状態が `stopped` になるまで待つ

> ※「停止」であって「終了（Terminate）」ではない。**終了は絶対に選ばない**（インスタンスが消え、復旧不能になる）。

### 2. RDS を停止
1. AWS コンソール → **RDS** → データベース一覧
2. `famigo-mysql` を選択
3. 「**アクション**」→「**一時的に停止**」
4. 状態が `stopped` になるまで待つ

> ※ RDS は停止後 **最大 7 日で自動再起動**する点に注意（上記「重要な前提」参照）。

---

## 再開手順

### 1. RDS を起動
1. AWS コンソール → **RDS** → `famigo-mysql` を選択
2. 「**アクション**」→「**起動**」
3. 状態が `available` になるまで待つ（数分）

### 2. EC2 を起動
1. AWS コンソール → **EC2** → `famigo-ec2` を選択
2. 「**インスタンスの状態**」→「**インスタンスを開始**」
3. 状態が `running` になるまで待つ

### 3. ブラウザで確認（ここまでで自動復帰しているはず）

ブラウザで **https://civil-track.com** を開く。

- **ログイン画面が表示される** → 復旧完了。そのまま利用できる。
- **表示されない / つながらない** → コンテナが自動復帰していない可能性。下記「4. コンテナ手動起動」を実施。

> 補足：切り分け目的で機械的に確認したい場合は、`https://civil-track.com/health` を開いて `OK`（HTTP 200）が返るかを見てもよい（ヘルスチェック用の軽量エンドポイント）。通常はトップページを開くだけで十分。

### 4.（必要時のみ）コンテナ手動起動

`/health` が 200 を返さない場合、EC2 に SSM で入ってコンテナを起動する。

1. AWS コンソール → EC2 → `famigo-ec2` →「接続」→「セッションマネージャー」→「接続」
2. ブラウザ内ターミナルで以下を実行：

```bash
sudo -i
cd /opt/civil
# ビルド済みイメージで起動（この環境は buildx が古いため BUILDKIT=0 を付ける）
export DOCKER_BUILDKIT=0 COMPOSE_DOCKER_CLI_BUILD=0
docker compose -f docker-compose.prod.yml up -d

# 状態確認：civil-php-1 / civil-nginx-1 が Up、famigo-api も Up
docker ps --format "{{.Names}} | {{.Status}}"

# ローカルヘルスチェック（200 が返れば OK）
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://localhost:8081/health
```

3. 再度、外部から `https://civil-track.com/health` が 200 を返すか確認。

> ALB のターゲットグループが healthy 判定になるまで、起動後 1 分程度かかることがある（ヘルスチェック 3 回連続成功が必要）。すぐ 200 にならなくても少し待つ。

---

## コード更新デプロイ手順（プログラムを修正して本番へ反映する）

プログラム（PHP / Blade / CSS 等）を修正して本番 https://civil-track.com に反映する手順。
当面は **手動デプロイ**（CI/CD は将来検討。[インフラ設計書 3.2](infrastructure-design.md)）。

### 大前提：コードと DB データは別物

- **コード**は Docker イメージに焼き込まれる（`docker-compose.prod.yml` の「ソースを焼き込む」）。
  更新する＝**イメージを作り直してコンテナを入れ替える**。
- **DB データ**（案件・職員等）は **RDS の中**にある。**コンテナ／イメージを作り直しても触れられない**。

→ したがって **コードを更新しても DB の既存データは消えない・初期状態に戻らない**（詳細は下記「DB データへの影響」）。

### 手順

#### ステップ 0：ローカルで正規フローに沿って main を更新

1. Issue 作成 → `feature/xx-...` ブランチを切る（CLAUDE.md）
2. コードを修正する
3. `quality-check` スキルで Pint / PHPStan / PHPUnit を緑にする
4. Conventional Commit → push → PR 作成 → **ユーザーがマージ**して main を最新化

> この時点では本番未反映。本番 EC2 は main を自動追従しないため、次に手動で持っていく。

#### ステップ 1：EC2 に入り最新コードを取得（tarball 方式）

EC2 に git は無いため tarball を取得して `/opt/civil` を上書き展開する。

```bash
sudo -i
cd /opt/civil

curl -L https://github.com/tomo-taka108/Civil-Request-Management/archive/refs/heads/main.tar.gz -o /tmp/civil.tar.gz
tar xzf /tmp/civil.tar.gz --strip-components=1
```

> ⚠️ この上書きで**コードは差し替わるが `.env.production` は上書きされない**
> （gitignore 対象で tarball に含まれないため）。機密設定はそのまま残る。

#### ステップ 2：イメージを作り直して起動（＝反映の本体）

```bash
export DOCKER_BUILDKIT=0 COMPOSE_DOCKER_CLI_BUILD=0   # EC2 の buildx が旧版のため
docker compose -f docker-compose.prod.yml up -d --build
```

`--build` により新コードを焼き込んだイメージが作られ、コンテナが入れ替わる。
起動時に entrypoint が `php artisan migrate --force` と config/route/view キャッシュ再生成を自動実行する。

#### ステップ 3：反映確認

```bash
docker ps --format "{{.Names}} | {{.Status}}"
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://localhost:8081/health
```

ブラウザで https://civil-track.com を開き、修正内容が反映されていれば完了。

### DB データへの影響（重要）

コード更新デプロイで既存の DB データがどうなるか。

| 操作 | コード | DB データ（案件・職員等） |
|---|---|---|
| 通常のコード更新デプロイ（上記手順） | 新しくなる | **変わらない**。entrypoint の `migrate --force` は**テーブル構造の最新化のみ**で行データは保持（新規マイグレーションが無ければ何もしない） |
| entrypoint の自動処理 | — | `migrate --force` **のみ**。`db:seed` は走らない |
| 手動で `db:seed --class=Sample...` | — | seeder は**冪等**。二重登録されず、手動で追加したデータも消えない（[README.md](../README.md) サンプルデータ節） |
| 手動で `migrate:fresh` / `migrate:rollback` | — | ⚠️ テーブルを作り直す＝**既存データが消え初期サンプルだけに戻る**。**本番では絶対に実行しない** |

- **再デプロイで seeder は自動実行されない**（entrypoint に seed は含まない。`docker/php/entrypoint.prod.sh`）。
  よって「案件 29 件＋手動追加 5 件＝34 件」の状態で再デプロイしても **34 件のまま**で、初期 29 件には戻らない。
- **初期件数に戻る**のは `migrate:fresh` 等の破壊的コマンドを本番で意図的に叩いた場合のみ。
  本番 entrypoint は `migrate --force`（追加のみ）であり、fresh は使わない。

---

## トラブル時の確認ポイント

| 症状 | 確認 |
|---|---|
| `/health` が 200 を返さない | `docker ps` で civil コンテナが Up か。落ちていれば手動 `up -d`（手順4） |
| コンテナが `Restarting` を繰り返す | `docker compose -f docker-compose.prod.yml logs php` でエラー確認（DB 接続不可＝RDS 未起動が典型） |
| DB 接続エラー | RDS が `available` か。停止したままでないか |
| メモリ逼迫 | `free -h`・`docker stats` で確認。Swap 2GB 設定済み。常時逼迫なら t3.small 検討（[インフラ設計書 3.4](infrastructure-design.md)） |

---

## 参考：関連ファイル・場所（EC2 上）

| 項目 | 場所 |
|---|---|
| 本システムのコード | `/opt/civil`（GitHub の tarball を展開して配置） |
| 本番 compose | `/opt/civil/docker-compose.prod.yml` |
| 本番環境変数（機密） | `/opt/civil/.env.production`（コミットしない） |
| famigo のコード | `/opt/famigo`・`/opt/famigo-docker`（**触らない**） |
| Swap | `/swapfile`（2GB。`/etc/fstab` 登録済み） |

> 本システムの操作は必ず `-f docker-compose.prod.yml` を明示する。付けないと famigo コンテナを巻き込む恐れがある（[インフラ設計書 3.2](infrastructure-design.md)）。
