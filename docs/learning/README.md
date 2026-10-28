# learning — 学習・振り返りメモ

このフォルダは、開発・デプロイを通じて理解を深めるために作成した**個人の学習用整理メモ**を格納する。
[docs/](../) 直下の設計書（要件定義・DB設計・画面設計・インフラ設計）とは性質が異なり、
「仕組みを噛み砕いて理解する」「やった作業を振り返る」ことが目的。正式な設計の根拠は各設計書を参照。

いずれも **単体で開ける HTML**（ブラウザでダブルクリックで表示。ライト/ダーク両対応・オフライン閲覧可）。

## ファイル一覧

| ファイル | 内容 |
|---|---|
| [aws-deploy-basics.html](aws-deploy-basics.html) | **AWSデプロイの仕組み**。「ブラウザ → ALB → EC2 → RDS」の流れと、Route53・ACM・リスナー・ターゲットグループ・セキュリティグループ等の各技術の役割を、たとえ話で解説 |
| [deploy-recap-image.html](deploy-recap-image.html) | **デプロイ振り返り（イメージ版）**。実際のデプロイ作業を「インフラ構築／本番設定／デプロイ」の3幕に分け、目的とやったことを直感的にたどる |
| [deploy-recap-technical.html](deploy-recap-technical.html) | **デプロイ振り返り（技術版）**。上記の各ステップに、実際のコマンド・作成ファイル・要点コード・「再実行が必要か早見表」を添えた技術寄りの版 |
| [logging-monitoring-basics.html](logging-monitoring-basics.html) | **ログと監視の基礎**。ログ・メトリクス・アラームの違い、ログレベル、構造化ログ、リクエストID、出してはいけない情報、Docker のログの流れ、CloudWatch の部品と、このアプリでの組み立て方 |
| [ci-basics.html](ci-basics.html) | **CI の基礎と導入**。CI の目的、CI と CD の違い（このアプリで CD を作らない理由）、GitHub Actions の部品（トリガー・ランナー・ジョブ・ステップ・サービスコンテナ等）、いつ動くか、CI で見つかること・見つからないこと、このアプリの `ci.yml` の読み方と作り方のポイント、❌ が出たときの直し方 |
| [testing-basics.html](testing-basics.html) | **テストの基礎と方針**。単体・結合・総合・E2E・パフォーマンスの違い（範囲と性質の2つの軸）、テストピラミッド、ブラックボックス／ホワイトボックス、このアプリのテストの現状（PHPUnit 204本の内訳）と、パフォーマンス → E2E → CI の順で足す方針 |

## 補足

- 内容は 2026-08-02 の本番デプロイ（https://civil-track.com 公開）時点のもの。
- 実際の設定値・切り分けの根拠は [infrastructure-design.md](../infrastructure-design.md)、停止・再開の運用は [operations-start-stop.md](../operations-start-stop.md) を参照。
- HTML は GitHub 上ではソースとして表示される（描画はされない）。閲覧するときはローカルにダウンロードしてブラウザで開く。
