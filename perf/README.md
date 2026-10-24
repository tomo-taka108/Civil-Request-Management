# perf — 負荷試験（k6）

設計・結果は [docs/performance-test.md](../docs/performance-test.md) を参照。ここは実行手順だけを書く。

## 必要なもの

- Docker Desktop（起動しておく）
- k6（`k6 version` で確認。Windows は `winget install k6 --source winget`）

## 実行

リポジトリ直下で、Git Bash から実行する。

```bash
./perf/run-perf.sh                                   # 普段の業務（30人・約3分半）
SCENARIO=perf/scenarios/export.js ./perf/run-perf.sh # CSV 出力（1人ずつ・約1分半）
PEAK_VUS=60 ./perf/run-perf.sh                       # 人数を変える（限界を探すとき）
KEEP=1 ./perf/run-perf.sh                            # 終わっても環境を残す
```

スクリプトが次の順に自動で行う。初回は本番用イメージのビルドで数分余計にかかる。

1. 試験専用の環境（`docker-compose.perf.yml`。ポート 8082）を、本番と同じイメージで起動する
2. 試験データ（3年分・約3,000件。`database/seeders/PerfDataSeeder.php`）を投入する
3. k6 でシナリオを流しながら、コンテナの CPU・メモリを5秒ごとに記録する
4. 終わったら（失敗・Ctrl+C でも）環境を止め、データを消す

開発用の環境（ポート 8081・`mysql-1`）には触れない。

## 結果

`perf/results/`（Git には入れない。実行のたびに上書き）

| ファイル | 中身 |
|---|---|
| `daily.md` / `export.md` | 画面ごとの件数・中央値・p95・p99・最大と合否 |
| `daily.json` / `export.json` | k6 の集計の全データ |
| `*-docker-stats.txt` | 試験中のコンテナの CPU・メモリ（5秒ごと） |

残したい結果は `docs/performance-test.md` に書き写す。

## 試験用のログイン情報

`KEEP=1` で残した環境（http://localhost:8082）にブラウザで入るとき用。試験専用 DB にしか存在しない。

| ユーザーID | 役割 |
|---|---|
| `perf01`〜`perf30` | 一般職員（各事務所10人） |
| `perfadmin` | システム管理者 |

パスワードはすべて `perf-password`。

## 手で1画面だけ測りたいとき

`KEEP=1` で残した環境に curl で測るときは、`localhost` ではなく **`127.0.0.1`** を使う。
Windows では `localhost` がまず IPv6 で接続を試みるため、毎回約0.6秒余計にかかり、アプリが遅いように見える
（k6 はこの影響を受けない）。

```bash
curl -s -o /dev/null -w '%{http_code} %{time_total}\n' http://127.0.0.1:8082/login
```

## 注意

- **本番には向けない。** 本番の EC2 は famigo と共用で、負荷をかけると famigo も止まりかねない。
- 数値は「前回と比べて遅くなっていないか」「どこが詰まるか」を見るためのもの。本番の実力とは言わない。
