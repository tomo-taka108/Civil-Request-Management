# 脆弱性対応の記録

このアプリが使っている技術（PHP・Laravel・nginx・MySQL など）に重大な脆弱性が出たときの、
影響の調べ方・対応・結果を残す。新しい件は上に足す。

---

## 2026-10 PHP の重大な脆弱性（PHP2Shell）

Issue #118。

### 1. どんな脆弱性か

2026-10-21 に PHP 本体の脆弱性2件が公表された（通称 PHP2Shell）。

| 項目 | 内容 |
|---|---|
| 番号 | CVE-2026-109618、CVE-2026-109619 |
| 深刻度 | CVSS v4.0 9.2（緊急） |
| できてしまうこと | ログインしていない外部の人が、細工した HTTP リクエストでサーバー上のコードを実行できる |
| 仕組み | リクエストの中身（フォームの値・アップロード）を PHP が読み取る段階のメモリ処理の欠陥。Laravel のコードが動く前に起きるため、ログイン確認などアプリ側の守りでは防げない |
| 対象 | Web で動くほぼすべての PHP（PHP-FPM を含む） |
| 修正版 | 8.3.36（8.3 系）。ほかに 8.2.35・8.4.27・8.5.12 |
| 悪用 | 公表前の 10/17 から攻撃の試みが観測され、攻撃用のプログラムも出回っている |

10/23 に、別の脆弱性 CVE-2026-115560 も公表された。PHP 標準のセッション機能を、実行中に特殊な設定で
切り替える使い方をした場合だけが対象で、修正版は次回の定例リリースで出る予定。

参考：[CISA](https://www.cisa.gov/news-events/alerts/2026/10/21/critical-vulnerabilities-php-require-immediate-patching)、
[Datadog Security Labs](https://securitylabs.datadoghq.com/articles/php2shell-critical-vulnerabilities-in-php/)、
[BleepingComputer（CVE-2026-115560）](https://www.bleepingcomputer.com/news/security/php-shares-mitigation-for-new-and-unpatched-rce-vulnerability/)

### 2. このアプリへの影響（2026-10-25 調査）

| 項目 | 結果 |
|---|---|
| 当てはまるか | **当てはまる**。PHP-FPM で動き、インターネットに公開している。すべてのリクエストが PHP-FPM に届く |
| 本番の PHP の版 | 調査時は未確認（EC2 停止中のため）。反映作業で確かめたところ、**10/22 21:37 のデプロイ以降は 8.3.36（修正版）で動いていた**（5章） |
| 版が古いままになるおそれ | Dockerfile が `php:8.3-fpm`（8.3 系までしか決めていない）で、デプロイ手順も元イメージを取り直さなかった。EC2 に古い元イメージが残っていれば、それでビルドされる仕組みだった。調査時は「残っていた可能性が高い」と見ていたが、実際には前回のデプロイ後の掃除（`docker image prune -a`）で元イメージが消えていたため、10/22 のビルドで公開直後の修正版が取り寄せられていた。掃除を忘れれば古いまま作られるため、版の固定と `--pull` は引き続き必要 |
| CVE-2026-115560 | **当てはまらない**。Laravel は PHP 標準のセッション機能を使わず、独自にセッションを管理している（`session_start` や該当の設定変更はコードに無い） |
| famigo | 対象外（Java / Spring Boot）。ただし同じ EC2 のため、このアプリが乗っ取られると巻き込まれるおそれがあった |
| ローカル（開発用・負荷試験用・E2E 用） | 外から届かない。開発用コンテナは 8.3.32 だったため、作り直して 8.3.36 にした |

#### 攻撃が始まった 10/17 以降に、本番がインターネットに出ていた時間

EC2 は普段止めているため、外に出ていたのは次の3回、合計約4時間半（CloudTrail の起動・停止の記録）。

| 開始 | 停止 | PHP の版 |
|---|---|---|
| 10/18 4:59 | 7:07 | **確実に修正前**（修正版の公開前） |
| 10/20 18:32 | 19:17 | **確実に修正前**（同上） |
| 10/22 20:39 | 22:15 | 21:37 のデプロイまでは修正前、それ以降は 8.3.36（5章で判明） |

#### 侵入の痕跡の確認

| 確認したこと | 結果 |
|---|---|
| CloudWatch Logs（10/22 21:37〜22:15） | 攻撃時に出やすいエラー（メモリ不足の致命的エラー、PHP の異常終了など）は0件。アクセスは運用者自身の操作だけ |
| それより前のログ | 確認できない。CloudWatch へ送り始める前で、EC2 内に残る形だった。10/22 のデプロイでコンテナを作り直したため残っていない |
| コンテナの中身 | 本番のコンテナは保存場所（ボリューム）を持たず、作り直すと中身が入れ替わる。10/18・10/20 のコンテナは 10/22 に作り直し済み。10/22 のコンテナは反映作業のときに `docker diff` で確かめ、不審な変更は無かった（5章） |
| EC2 の AWS 権限の使われ方（CloudTrail） | SSM の定期連絡、ログの送り先の作成、運用者の接続だけ。S3・IAM などへの不審な操作は無い。ただし S3 のファイルの読み出しは CloudTrail の既定では記録されないため、確認できない |

### 3. 対応

| 対応 | 目的 |
|---|---|
| 元イメージを `php:8.3.36-fpm` に固定（`Dockerfile`・`Dockerfile.prod`） | 確実に修正版で作る。どの版で動いているかをコードで分かるようにする |
| `session.upload_progress.enabled = 0`（`docker/php/security.ini`） | 念のための二重の守り。攻撃の通り道になっていた機能で、このアプリは使っていない。CVE-2026-115560 の回避策でもある |
| デプロイ手順に `build --pull` と `php -v` の確認を追加（[運用手順](operations-start-stop.md)） | 今後、修正版が出ても本番に入らない、を防ぐ |

### 4. 本番への反映手順（今回だけ）

> 実際の作業（2026-10-26）では、反映前の時点ですでに 8.3.36 で動いており、掃除できる古いイメージも無かった。
> そのため手順4（止める）と手順6（消す・掃除）は行わず、動かしたまま手順5 → 7 → 8 → 9 → 10 の順で進めた。
> こうするとビルドに失敗しても今のコンテナが動き続け、止まるのは入れ替えの数秒で済む。下の手順は、反映前が修正前の版だった場合のもの。

ふだんのデプロイとの違いは、①起動直後に版を記録してすぐ止めること、②痕跡を確かめること、
③ディスクの空きを先に作ることの3つ。EC2 を起動すると、アプリのコンテナは古い PHP のまま自動で立ち上がるため、
外に出る時間を数分に抑える。

1. **RDS → EC2 の順に起動する**（[運用手順](operations-start-stop.md)「再開手順」の1・2）
2. **SSM で EC2 に入る**
   ```bash
   sudo -i
   cd /opt/civil
   ```
3. **今の版を記録する**（結果を控えて、あとでこの記録に書き足す）
   ```bash
   docker compose -f docker-compose.prod.yml exec php php -v
   docker images php
   ```
4. **すぐにアプリのコンテナだけを止める**（famigo には触れない）
   ```bash
   docker compose -f docker-compose.prod.yml stop
   ```
5. **痕跡を確かめる**：コンテナの中で、イメージから変わったファイルを一覧にする
   ```bash
   docker diff civil-php-1
   docker diff civil-nginx-1
   ```
   `storage/framework/`（画面・設定のキャッシュ、セッション）と `/tmp` の PHP の一時ファイル以外に、
   見覚えのないファイル（特に `public/` の下の `.php`、`/tmp` の実行ファイル）があれば作業を止めて相談する。
6. **ディスクの空きを作る**：止めたコンテナを消し、使われていない古いイメージを掃除する
   ```bash
   docker compose -f docker-compose.prod.yml down    # このアプリのコンテナだけを消す（-v は付けない）
   df -h /
   docker image prune -a
   df -h /
   ```
7. **最新のコードを取得する**（運用手順「コード更新デプロイ手順」のステップ1）
8. **元イメージを取り直してビルドし、起動する**
   ```bash
   export DOCKER_BUILDKIT=0 COMPOSE_DOCKER_CLI_BUILD=0
   docker compose -f docker-compose.prod.yml build --pull
   docker compose -f docker-compose.prod.yml up -d
   ```
9. **確かめる**
   ```bash
   docker compose -f docker-compose.prod.yml exec php php -v                                # 8.3.36 であること
   docker compose -f docker-compose.prod.yml exec php php -i | grep session.upload_progress.enabled   # Off であること
   docker compose -f docker-compose.prod.yml exec php ls -la /var/www/html | grep env       # .env.production が無いこと（#110）
   docker ps --format "{{.Names}} | {{.Status}}"                                           # famigo-api も Up
   curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://localhost:8081/health              # 200
   ```
   ブラウザで https://civil-track.com にログインできることも確かめる。
10. **古いイメージを掃除する**（運用手順「コード更新デプロイ手順」のステップ4）

### 5. 結果

2026-10-26 に本番へ反映した（EC2 起動 18:54、コンテナの入れ替え 19:32）。

#### 反映前

| 確認 | 結果 |
|---|---|
| PHP の版 | `PHP 8.3.36 (cli) (built: Oct 21 2026 ...)`。すでに修正版 |
| コンテナの作成日時 | 10/22 21:37（10/22 のデプロイで作ったもの。CloudWatch のログが始まった時刻とも一致） |
| EC2 に残る元イメージ | 無し（`docker images php` が空）。イメージは civil-php・civil-nginx・famigo-api の3つだけ |
| ディスク | 78%（空き 1.8GB） |

#### 痕跡の確認（`docker diff`）

| コンテナ | 変更があった場所 | 判断 |
|---|---|---|
| civil-php-1 | `storage/framework/`・`bootstrap/cache/`（Laravel のキャッシュ）と、その上のフォルダだけ | 通常の動作で変わる場所のみ |
| civil-nginx-1 | `/var/cache/nginx/*_temp`・`/run/nginx.pid`・`/etc/nginx/conf.d/default.conf` | 前の2つは nginx の起動時に作られるもの。`default.conf` は中身をイメージの元のファイルと比べて同一（`diff` で差分なし）。nginx の起動処理が書き込めるかを試したときに日時だけ変わったもの |

不審なファイル（`public/` の下の `.php`、`/tmp` の実行ファイルなど）は無かった。

#### 反映

- `build --pull`：civil-php を新しく作成。civil-nginx は材料が 10/22 から変わっていないため同じイメージのまま（入れ替えも無し）
- ディスク：ビルド後 83%（空き 1.4GB）→ 古いイメージの掃除後 78%（空き 1.8GB）

#### 反映後

| 確認 | 結果 |
|---|---|
| PHP の版 | 8.3.36 |
| `session.upload_progress.enabled` | Off |
| `.env.production` がイメージに入っていないか（#110） | 入っていない（`.env.example`・`.env.production.example` のみ） |
| コンテナ | civil-php-1・civil-nginx-1・famigo-api すべて Up |
| `/health` | 200 |
| ブラウザでのログイン | 成功 |
| CloudWatch Logs（入れ替え後） | エラー0件 |

> EC2 の起動直後（18:56 ごろ）に、DB の準備が終わる前にアプリが DB へつなごうとして失敗したエラーが5件出ている。
> 起動時の自動の再試行で回復しており、今回の反映とは関係ない（RDS を先に起動して `available` になってから EC2 を起動すれば出ない）。

### 6. 今後

- CVE-2026-115560 の修正版（次回の定例リリース）が出たら、`Dockerfile`・`Dockerfile.prod` の版を上げる
- PHP 8.3 のセキュリティ修正は 2027-12-31 まで。それまでに 8.4 以降への移行を検討する
- nginx の元イメージ `nginx:1.27-alpine` は作成から約18か月たち、1.27 系は更新が止まっている。更新が続いている版に上げる（#121）
