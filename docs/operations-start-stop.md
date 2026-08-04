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

### 3. 疎通確認（ここまでで自動復帰しているはず）

まず外部から確認する（PC のブラウザ or ターミナル）：

```bash
# 200 が返れば OK
curl -s -o /dev/null -w "HTTP=%{http_code}\n" https://civil-track.com/health
```

- **200 が返る** → 復旧完了。https://civil-track.com にアクセスして動作確認。
- **200 が返らない / つながらない** → コンテナが自動復帰していない可能性。下記「4. コンテナ手動起動」を実施。

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
