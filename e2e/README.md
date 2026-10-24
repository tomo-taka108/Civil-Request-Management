# e2e — E2E テスト（Playwright）

設計・テストの一覧・結果は [docs/e2e-test.md](../docs/e2e-test.md) を参照。ここは実行手順だけを書く。

## 必要なもの

- Docker Desktop（起動しておく）
- Node.js（`node -v` で確認）

初回だけ、Playwright と Chromium（テスト用のブラウザ。約120MB）を入れる。

```bash
cd e2e
npm ci
npx playwright install chromium
```

## 実行

リポジトリ直下で、Git Bash から実行する。

```bash
./e2e/run-e2e.sh                         # すべて（約1分。アプリのコードを変えた直後はイメージの作り直しで約3分半）
./e2e/run-e2e.sh specs/map.spec.ts       # 一部だけ（引数はそのまま Playwright に渡す）
./e2e/run-e2e.sh --headed                # ブラウザの画面を出して、操作の様子を見る
E2E_REAL_TILES=1 ./e2e/run-e2e.sh        # 国土地理院の本物の地図タイルを読み込むテストも流す
KEEP=1 ./e2e/run-e2e.sh                  # 終わっても環境を残す
```

スクリプトが次の順に自動で行う。

1. 試験専用の環境（`docker-compose.e2e.yml`。ポート 8083）を、本番と同じイメージで起動する
2. 試験データ（ユーザー4人・案件7件。`database/seeders/E2eDataSeeder.php`）を投入する
3. Playwright でテストを1つずつ順に流す
4. 終わったら（失敗・Ctrl+C でも）環境を止め、データを消す

開発用の環境（ポート 8081・`mysql-1`）と負荷試験用の環境（8082）には触れない。

## 結果

`e2e/results/`（Git には入れない。実行のたびに上書き）

| 中身 | 見方 |
|---|---|
| HTML のレポート | `cd e2e && npm run report` でブラウザが開く |
| 失敗したテストの画面の写真・操作の記録（トレース） | レポートから開ける。トレースは操作を1手ずつ巻き戻して見られる |

## 試験用のログイン情報

`KEEP=1` で残した環境（http://localhost:8083）にブラウザで入るとき用。試験専用 DB にしか存在しない。

| ユーザーID | 役割 |
|---|---|
| `e2e_road` | みどり土木事務所・道路担当 |
| `e2e_river` | みどり土木事務所・河川担当 |
| `e2e_tsubaki` | つばき土木事務所・道路担当 |
| `e2e_admin` | システム管理者 |

パスワードはすべて `e2e-password`。

## 型チェック

```bash
cd e2e && npm run typecheck
```

## 注意

- **本番には向けない。** テストは案件の登録・削除やユーザーの登録を実際に行う。
- 地図のタイル（背景の画像）は、ふだんダミーの灰色の画像に差し替えている。理由は設計書 5章。
