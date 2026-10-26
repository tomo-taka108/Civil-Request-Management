# 運用手順：EC2 / RDS の停止・再開

コスト縮減のため、使わない期間は EC2・RDS を手動停止する。次回スムーズに再開するための手順。
本システムは famigo 既存環境（EC2 `famigo-ec2` / RDS `famigo-mysql`）に相乗りしている（[インフラ設計書](infrastructure-design.md) 参照）。

最終更新: 2026-08-15（本番公開 2026-08-02）

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
docker compose -f docker-compose.prod.yml build --pull
docker compose -f docker-compose.prod.yml up -d
```

`build` により新コードを焼き込んだイメージが作られ、`up -d` でコンテナが入れ替わる。
起動時に entrypoint が `php artisan migrate --force` と config/route/view キャッシュ再生成を自動実行する。

> **`--pull` を必ず付ける。** 付けないと、EC2 に元イメージ（PHP・nginx の土台）が残っている場合、それをそのまま使ってビルドする。
> そうなると、PHP や OS の修正版が出ていても本番に入らない。ステップ 4 の掃除で元イメージが消えていれば毎回取り直されるが、
> 掃除を忘れたときにも確実に最新にするため付ける（[脆弱性対応の記録](security-advisories.md)）。

#### ステップ 3：反映確認

```bash
docker ps --format "{{.Names}} | {{.Status}}"
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://localhost:8081/health
docker compose -f docker-compose.prod.yml exec php php -v   # PHP の版が Dockerfile.prod の FROM と同じか
```

ブラウザで https://civil-track.com を開き、修正内容が反映されていれば完了。

#### ステップ 4：不要になった旧イメージを掃除する（デプロイのたびに実施）

```bash
df -h /                 # 掃除前の使用率を確認
docker image prune -a   # 確認プロンプトに y で承認
df -h /                 # 減っていることを確認
```

`--build` で新しいイメージが作られると、**それまで使っていた旧イメージはタグを失い `<none>` として残り続ける**（1つあたり約580MB）。放置するとディスクを圧迫し、いずれビルドやログ書き込みが失敗する。

> デプロイ直後は使用率が一時的に90%前後まで上がるため、掃除が遅れると**ディスク逼迫のアラーム（85%以上）のメールが届く**（2026-10-22 のデプロイで実際に 78% → 90%）。掃除すれば数分後に「OK」のメールが届く。デプロイしたら、このステップまで続けて行う。

- **稼働中のコンテナには影響しない。** Docker は使用中イメージの削除を拒否するため、`civil-php` / `civil-nginx` / `famigo-api` は保護される（**famigo も停止しない**）
- `docker system prune -a` は**使わない**。ネットワークやボリュームまで対象に含み、famigo 側のリソースに触れるおそれがあるため、イメージのみを対象とする `image prune` を使う
- `--filter "until=24h"` のようなフィルタは付けない。相対時間の解釈が直感と異なり、**削除対象が0件になることがある**（2026-08-15 に実際に発生）
- 使用率が 90% を超える場合は EBS ボリュームの拡張を検討する（インフラ設計書 3.4）

> 参考：2026-08-15 時点で 80% → 77%（約227MB 回収）。表示上の「回収可能サイズ」はレイヤー共有分を含むため、実際の削減量はそれより小さくなる。

### DB データへの影響（重要）

コード更新デプロイで既存の DB データがどうなるか。

| 操作 | コード | DB データ（案件・職員等） |
|---|---|---|
| 通常のコード更新デプロイ（上記手順） | 新しくなる | **変わらない**。entrypoint の `migrate --force` は**テーブル構造の最新化のみ**で行データは保持（新規マイグレーションが無ければ何もしない） |
| entrypoint の自動処理 | — | `migrate --force` **のみ**。`db:seed` は走らない |
| 手動で `db:seed --class=Sample...` | — | seeder は**冪等**。二重登録されず、手動で追加したデータも消えない（[README.md](../README.md) サンプルデータ節） |
| 手動で `sample:refresh` | — | **サンプル職員が登録した案件**（デモ用アカウントで後から登録された案件を含む）を対応履歴・写真ごと消し、最新のサンプルを入れ直す。職員アカウントやそれ以外の案件は残る（下記「サンプルデータの入れ替え」） |
| 手動で `migrate:fresh` / `migrate:rollback` | — | ⚠️ テーブルを作り直す＝**既存データが消え初期サンプルだけに戻る**。**本番では絶対に実行しない** |

- **再デプロイで seeder は自動実行されない**（entrypoint に seed は含まない。`docker/php/entrypoint.prod.sh`）。
  よって「案件 29 件＋手動追加 5 件＝34 件」の状態で再デプロイしても **34 件のまま**で、初期 29 件には戻らない。
- **初期件数に戻る**のは `migrate:fresh` 等の破壊的コマンドを本番で意図的に叩いた場合のみ。
  本番 entrypoint は `migrate --force`（追加のみ）であり、fresh は使わない。

---

## サンプルデータの入れ替え（サンプルの内容を更新したとき）

体験用サンプル（案件・対応履歴・現場写真）の内容を更新しても、Seeder は「投入済みなら何もしない」ため、既存の環境には反映されない。反映するには `sample:refresh` を実行する。

### 消えるもの・残るもの

| 消える | 残る |
|---|---|
| サンプル職員（`staff101`〜`staff303`）が登録者の案件すべて（デモ用アカウントで後から登録された案件を含む） | 事務所・職員アカウント（変更済みのパスワードを含む） |
| それらの案件の対応履歴・現場写真（S3 上のファイルも消す） | サンプル職員以外（管理者など）が登録した案件と、その対応履歴・写真 |

### 本番での手順

1. **RDS のスナップショットを取る**（念のための戻し先。AWS コンソール → RDS → `famigo-mysql` →「アクション」→「スナップショットの取得」。famigo と共用のため、名前に `civil-before-sample-refresh-YYYYMMDD` のように目的を入れる）
2. 「コード更新デプロイ手順」で最新コードを反映する（サンプルのデータと写真はコードと一緒に届く）
3. EC2 で入れ替えを実行する：

   ```bash
   cd /opt/civil
   docker compose -f docker-compose.prod.yml exec php php artisan sample:refresh --force
   ```

   `サンプル案件を投入しました（73件・対応履歴96件・写真56枚）。` のように表示されれば完了。
4. ブラウザでデモ用アカウントにログインし、一覧の先頭の案件に写真が表示されることを確認する
5. 問題がなければ、手順1のスナップショットは削除してよい（保管中は容量に応じて課金される）

> `--force` は本番環境での確認プロンプトを省略する指定。付けない場合は実行前に確認を求められる。

---

## CloudWatch によるログ・監視

コンテナのログ（nginx・Laravel）は CloudWatch Logs のロググループ `/civil/app` に送られ、30日で自動で消える。
異常はメールで届く。設計は [ログ・監視設計書](logging-and-monitoring.md) 7章・8章、AWS 側の設定は [infra/monitoring.tf](../infra/monitoring.tf)。

### ログの見方

**ブラウザ（通常はこちら）**：AWS コンソール → CloudWatch → ロググループ → `/civil/app`

| ログストリーム | 中身 |
|---|---|
| `php` | Laravel のログ（JSON 1行）。業務の記録・アクセスログ・エラー |
| `nginx` | nginx のアクセスログ（JSON 1行）と nginx 自身のエラー |

条件で絞り込むときは「Logs Insights」で `/civil/app` を選び、次のように書く。

```
# 問い合わせ番号（リクエストID）で、1回の操作のログをすべて出す
fields @timestamp, @logStream, message, status, path
| filter extra.request_id = "ここに番号" or request_id = "ここに番号"
| sort @timestamp asc

# 直近のエラーだけを出す
fields @timestamp, message, context.exception.class, context.exception.message
| filter level >= 400
| sort @timestamp desc
| limit 20
```

**EC2 上**：`docker compose -f docker-compose.prod.yml logs --tail 50 php` でも直近のログを見られる（Docker が手元にも一時的に残している）。

> `docker compose exec php php artisan ...` で実行したコマンド（`sample:refresh` など）のログは、コンテナ本体の出力ではないため CloudWatch には届かない。実行した画面に表示される内容で確認する。

### アラームのメールが届いたら

| アラーム名 | 意味 | まずやること |
|---|---|---|
| `civil-request-app-errors` | アプリが ERROR のログを出した | 上の「直近のエラーだけを出す」で内容を確認。再現手順が分かれば Issue にする |
| `civil-request-site-down` | ALB から見て正常なサーバーが0台 | **EC2 を自分で停止した後なら対応不要**（停止から約10分後に1通届く。起動すると OK が1通）。そうでなければ `/health`・`docker ps` を確認し、落ちていれば「再開手順 4」で起動 |
| `civil-request-disk-usage` | EC2 のディスク使用率が85%以上 | `df -h /`・`docker system df` を確認し、`docker image prune -a` で古いイメージを消す。続くなら EBS 拡張（Issue #102） |

復旧すると「OK」のメールが届く。メールは状態が変わった瞬間に1通ずつで、異常が続いている間に繰り返し届くことはない。

### 初回セットアップ（2026-10-22 実施済み。作り直すときだけ行う）

**順番が大事。** awslogs ドライバは、ロググループと書き込み権限が無いと**コンテナが起動しない**。
AWS 側（手順 2）→ デプロイ（手順 3）の順で行う。

1. **RDS → EC2 の順に起動**し、サイトが表示されることを確認する（停止中にアラームを作ると、サイト停止のアラームがすぐ鳴るため）
2. **AWS 側を Terraform で作る**（ローカル PC の `infra/`）
   ```bash
   aws sso login --profile takashima
   cd infra
   # terraform.tfvars に通知先を書く（コミットされない）： alert_email = "自分のアドレス"
   AWS_PROFILE=takashima terraform plan    # 「8 to add, 0 to change, 0 to destroy」を確認
   AWS_PROFILE=takashima terraform apply   # yes で実行
   ```
   - 作られるもの：ロググループ、EC2 ロールへのインラインポリシー1枚（`CivilTrackLogsAndMetrics`）、SNS トピックとメール購読、メトリクスフィルタ、アラーム3つ
   - **届いた確認メール（AWS Notification - Subscription Confirmation）のリンクを押す**。押すまで通知は届かない
3. **EC2 で最新コードを反映する**（SSM。「コード更新デプロイ手順」のステップ 1）。続けて `.env.production` のログ設定を `.env.production.example` に合わせる
   ```bash
   cd /opt/civil
   grep -n '^LOG_' .env.production          # 変更前を確認
   vi .env.production
   #   LOG_CHANNEL=stderr
   #   LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter
   #   LOG_LEVEL=info
   #   （LOG_STACK の行は削除。LOG_DEPRECATIONS_CHANNEL=null はそのまま）
   grep -n '^LOG_' .env.production          # 変更後を確認
   ```
   そのままステップ 2（`up -d --build`）・ステップ 3（反映確認）を行う
4. **ログが CloudWatch に届いたか確認する**：ブラウザで https://civil-track.com を開いてから、`/civil/app` に `php`・`nginx` の2つのログストリームができていること
5. **ディスク使用率の送信を設定する**（EC2。1回だけ）
   ```bash
   sudo install -m 755 /opt/civil/infra/ec2/civil-disk-metric.sh /usr/local/bin/
   sudo install -m 644 /opt/civil/infra/ec2/civil-disk-metric.service /opt/civil/infra/ec2/civil-disk-metric.timer /etc/systemd/system/
   sudo systemctl daemon-reload
   sudo systemctl enable --now civil-disk-metric.timer
   sudo systemctl start civil-disk-metric.service     # 1回すぐ送る
   systemctl status civil-disk-metric.service --no-pager   # 「status=0/SUCCESS」なら成功
   systemctl list-timers civil-disk-metric.timer --no-pager # 次回の実行予定
   ```
6. **エラー通知のテスト**：コンテナの出力にテスト用の ERROR 行を1行書き、5分以内にメールが届くことを確認する
   ```bash
   docker compose -f docker-compose.prod.yml exec php sh -c \
     'echo "{\"message\":\"監視テスト：アラーム通知の確認\",\"level\":400,\"level_name\":\"ERROR\"}" > /proc/1/fd/2'
   ```
   （`/proc/1/fd/2` はコンテナ本体の標準エラー出力。`artisan tinker` からログを書いても CloudWatch には届かないため、この方法で試す）。10分ほどで「OK」のメールも届く
7. 「コード更新デプロイ手順」のステップ 4（旧イメージの掃除）を行う

**うまくいかないとき（元に戻す）**：コンテナが起動しない場合は、`docker-compose.prod.yml` の `logging:` ブロック（nginx・php の2か所）を消して `up -d` すると、従来どおり EC2 上にログを出して起動する。AWS 側を消すときは `terraform destroy` ではなく、monitoring.tf の中身を消してから `terraform apply` する（`destroy` は写真の S3 バケット等も消してしまう）。

---

## トラブル時の確認ポイント

| 症状 | 確認 |
|---|---|
| `/health` が 200 を返さない | `docker ps` で civil コンテナが Up か。落ちていれば手動 `up -d`（手順4） |
| コンテナが `Restarting` を繰り返す | `docker compose -f docker-compose.prod.yml logs php` でエラー確認（DB 接続不可＝RDS 未起動が典型）。CloudWatch の `/civil/app` でも見られる |
| コンテナが起動しない（`awslogs` を含むエラー） | ロググループ `/civil/app` と EC2 ロールの `CivilTrackLogsAndMetrics` があるか（「CloudWatch によるログ・監視」の初回セットアップ） |
| DB 接続エラー | RDS が `available` か。停止したままでないか |
| メモリ逼迫 | `free -h`・`docker stats` で確認。Swap 2GB 設定済み。常時逼迫なら t3.small 検討（[インフラ設計書 3.4](infrastructure-design.md)） |

---

## 参考：関連ファイル・場所（EC2 上）

| 項目 | 場所 |
|---|---|
| 本システムのコード | `/opt/civil`（GitHub の tarball を展開して配置） |
| 本番 compose | `/opt/civil/docker-compose.prod.yml` |
| 本番環境変数（機密） | `/opt/civil/.env.production`（コミットしない） |
| ディスク使用率の送信 | `/usr/local/bin/civil-disk-metric.sh`・`/etc/systemd/system/civil-disk-metric.{service,timer}`（元は `/opt/civil/infra/ec2/`） |
| famigo のコード | `/opt/famigo`・`/opt/famigo-docker`（**触らない**） |
| Swap | `/swapfile`（2GB。`/etc/fstab` 登録済み） |

> 本システムの操作は必ず `-f docker-compose.prod.yml` を明示する。付けないと famigo コンテナを巻き込む恐れがある（[インフラ設計書 3.2](infrastructure-design.md)）。
