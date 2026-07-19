# インフラ設計書 v0.4

本システムのAWSインフラ構築・デプロイ方針を定める。
[要件定義書](requirements.md) の「4.3 保守性・運用」「4.4 技術スタック」を前提とし、既存の別アプリ（famigo）用AWS環境への相乗り方針を具体化したもの。

## 改訂履歴

| 日付 | version | 内容 | 作成者 |
|---|---|---|---|
| 2026-07-20 | 0.1 | 初版作成。既存famigo環境の実地調査結果と、相乗り方針・Terraform化方針を確定 | - |
| 2026-07-20 | 0.2 | famigo-backendリポジトリの調査結果を反映：EC2上の実行方式がDocker Composeであること、フロントエンド（React）はS3+CloudFrontで別配信されており本システムの相乗り対象外であることを明記。ドメイン方針をfamigo-odekake.comのサブドメイン間借りに確定（想定URL例を追記）。ポートフォリオという表現、および稼働状態（停止中／再開予定）の記述を削除 | - |
| 2026-07-20 | 0.3 | ドメイン方針を変更：サブドメイン間借りはURLに`famigo-odekake`という文字列が残り紛らわしいため取りやめ、本システム専用の独自ドメインを新規取得する方針に確定。あわせてALBの要否を検討し、Cost ExplorerでALBの実コスト（月約17.5USD、99.9%が稼働時間課金でLCU従量課金は誤差レベル）を確認した上で、既存ALBへの相乗りを継続する方針を確定（3.6節に実コストを追記） | - |
| 2026-07-20 | 0.4 | 第三者レビュー指摘を反映：(1) EC2上のdocker-compose.ymlをfamigoと完全分離し事故防止（3.2）、(2) Laravel側コンテナ構成をNginx+PHP-FPMの2コンテナに修正（3.2）、(3) t3.microのメモリ検証手順を具体化（3.4）、(4) Terraform方針を「全リソースimport」から「data source参照中心＋新規依存部分のみimport」のハイブリッド方式に変更（4.1・4.2）、(5) ドメイン取得先はRoute53限定でない旨を明記（3.6）、(6) ALB相乗り先にLaravelの`/health`実装が必要な旨を追記（3.5）、(7) EC2デプロイはfamigoのCI/CD方式を踏襲する旨を明記（3.2）、(8) RDS権限をGRANT ALLから必要最小権限に変更（3.1）、(9) Dockerネットワークの分離方針を追加（3.3）、(10) tfstateをlocal管理とする理由を補記（4.4）。あわせて「無関係」等の感情的な表現を客観的な記述に修正 | - |

---

## 1. 前提・方針

- **追加コストを可能な限り発生させないこと**を最優先とする
- 既存環境（famigo）は別システムとして運用されており、本システムとはアプリケーションレベルで独立している。その稼働状況・障害リスクを本システムの設計判断で重く見る必要はない
- 一方で、famigo・本システムのいずれも公開時にブラウザから正常に閲覧・操作できることは必須要件とする
- **EC2・RDSともにfamigoの既存インスタンスに相乗りする**（インスタンスを分けて追加コストを発生させない）。相乗り可否をRDSのみ・EC2のみで分けると、どちらか一方に本システムのインスタンスが見かけ上存在しない状態になり後々の混乱要因になるため、両方相乗りで統一する
- 既存famigo環境は手動構築（ClickOps）されており、TerraformなどのIaC管理下にない。本システムのインフラはTerraformで管理するが、famigoの相乗り先リソース（VPC・Subnet・SG・ALB等、新規リソースが依存する部分）については、importとdata source参照を使い分ける（詳細・使い分けの基準は「4. Terraform化方針」）
- famigoとのリソース共存部分（EC2上のコンテナ実行環境、Dockerネットワーク）は、片方の変更・デプロイがもう片方に影響しないよう明確に分離する（「3.2 EC2相乗り」「3.7 Dockerネットワーク」参照）
- インフラ構築の着手順序：バックエンド・フロントエンドの実装が一定進んだ後に着手する。ただし方針自体は実装着手前の本ドキュメントで先に確定しておき、実装を進める中で判明した点は都度本ドキュメントに追記する

---

## 2. 既存famigo環境の実地調査結果（2026-07-20 時点、AWS CLIで確認）

### 2.1 VPC・サブネット

| 項目 | 値 |
|---|---|
| VPC ID | `vpc-0cc705e872edbf064`（タグ名: `famigo-vpc`） |
| CIDR | `10.0.0.0/16` |
| リージョン | ap-northeast-1（東京） |

| サブネット | AZ | CIDR | 用途 |
|---|---|---|---|
| `subnet-0bb8918a99a59e85c`（famigo-public-1a） | 1a | 10.0.0.0/20 | Public（ALB・IGW経路あり） |
| `subnet-0f9de8c53d6c30a3d`（famigo-public-1c） | 1c | 10.0.16.0/20 | Public（ALB・IGW経路あり） |
| `subnet-06e94ac8309ef3ad8`（famigo-private-1a） | 1a | 10.0.128.0/20 | Private（RDS配置） |
| `subnet-065e4594ccc4810f5`（famigo-private-1c） | 1c | 10.0.144.0/20 | Private（RDS配置） |

- **NATゲートウェイは存在しない**（コスト削減のため未設置）。Privateサブネットに外向きインターネット経路はない。EC2はPublicサブネットに配置されており、外部通信はIGW経由で直接行っている
- Internet Gateway: `igw-09d5f23acfadbadf7`

### 2.2 EC2

| 項目 | 値 |
|---|---|
| インスタンスID | `i-0dcf0d521ec4ae906`（タグ名: `famigo-ec2`） |
| タイプ | t3.micro |
| 配置サブネット | `subnet-0bb8918a99a59e85c`（public-1a） |
| プライベートIP | 10.0.15.78 |
| セキュリティグループ | `famigo-sg-ec2`（`sg-0bfa7f20eb86ee312`） |

SGルール（`famigo-sg-ec2`）：8080/tcp をALB SGから許可、SSHを自宅IPから許可（説明文ベース、要現物確認）。

**EC2上のアプリ実行方式**（`famigo-backend`リポジトリの調査結果）：famigoのバックエンドはJava 21 / Spring Boot構成で、8080番ポートで`/health`エンドポイントを公開している。実行方式はsystemd/PM2ではなく**Docker Compose**（GitHub Actions → S3にjarを配置 → SSM RunCommand経由でEC2上`/opt/famigo-docker`配下で`docker build` & `docker compose up -d`）。本システム（Laravel/PHP）を相乗りさせる場合も、既存構成に合わせて**別コンテナとしてDocker上で共存**させるのが自然（「3.2 EC2相乗り」参照）。

### 2.3 RDS

| 項目 | 値 |
|---|---|
| インスタンス識別子 | `famigo-mysql` |
| エンジン | MySQL 8.4.7 |
| クラス | db.t4g.micro |
| ストレージ | 20GB（gp2） |
| Multi-AZ | いいえ |
| パブリックアクセス | 不可 |
| エンドポイント | `famigo-mysql.cbm8aii4s3oe.ap-northeast-1.rds.amazonaws.com:3306` |
| サブネットグループ | `famigo-db-subnet-group`（private-1a / private-1c） |
| パラメータグループ | `default.mysql8.4` |
| 自動バックアップ保持期間 | 1日 |
| セキュリティグループ | `famigo-sg-rds`（`sg-09eda88841fdfc245`） |

SGルール（`famigo-sg-rds`）：3306/tcp を `famigo-sg-ec2`（EC2側SG）からのみ許可。インバウンドはこの1本のみ。

### 2.4 ALB・ドメイン・証明書

| 項目 | 値 |
|---|---|
| ALB名 | `famigo-alb`（internet-facing） |
| 配置サブネット | public-1a / public-1c |
| セキュリティグループ | `famigo-sg-alb`（`sg-06f4122a083fa07b8`、80/443許可） |
| リスナー | 80（→443へHTTPSリダイレクト）、443（HTTPS） |
| 443のデフォルトアクション | ターゲットグループ `famigo-tg-8080` へforward（現状ルールは1本のみ、パス/ホスト条件なし） |
| ターゲットグループ | `famigo-tg-8080`（HTTP:8080、ヘルスチェックパス `/health`） |
| Route53ホストゾーン | `famigo-odekake.com`（`Z08966072XUKH5FDY118M`） |
| ACM証明書 | `api.famigo-odekake.com`（ISSUED） |

**重要**：現状ALBの443リスナーはルール分岐がなく、`famigo-tg-8080`への単純forwardのみ。本システムを同一ALBに相乗りさせる場合、**ホストベース or パスベースのリスナールールを新設し、本システム用の別ターゲットグループへ振り分ける**必要がある（「3.3 ALB相乗り方式」参照）。

### 2.5 famigo全体構成の補足（フロントエンドはALB対象外）

`famigo-backend`リポジトリの調査により、famigoは以下の構成であることが判明した。

- **バックエンドAPI**（Java/Spring Boot）：ALB（`famigo-alb`）→ EC2の8080番ポート（Docker） という経路。ACM証明書のドメインが`api.famigo-odekake.com`となっているのはこのため（APIサブドメイン専用）
- **フロントエンド**（React SPA）：別リポジトリ`famigo-frontend`で開発され、**S3 + CloudFrontで配信**（ALB・EC2は経由しない）

つまり、ALB・EC2・RDSはfamigoの「API・DB」部分のみを担っており、フロントエンド配信用のS3・CloudFrontは完全に別経路。本システム（Laravel + Blade、画面表示とAPIが1プロセスに統合された構成）が相乗りの対象とするのは**ALB・EC2・RDSの3つのみ**であり、famigoのS3・CloudFrontとは関わりを持たない（Bladeがサーバーサイドで画面を描画するため、S3/CloudFrontのような静的フロント配信基盤はそもそも不要）。

---

## 3. 相乗り方式の詳細設計

### 3.1 RDS相乗り

`famigo-mysql`インスタンス内に、本システム専用のデータベース（スキーマ）を新規作成する。

```sql
CREATE DATABASE civil_request_management CHARACTER SET utf8mb4 COLLATE utf8mb4_ja_0900_as_cs;
CREATE USER 'civil_request_app'@'%' IDENTIFIED BY '<発行するパスワード>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON civil_request_management.* TO 'civil_request_app'@'%';
```

- famigo用のDB・ユーザーとは完全に分離し、本システム用アプリケーションユーザーは自分のスキーマにしかアクセスできないようにする
- 権限は`GRANT ALL`ではなく、Laravel（Eloquent・Migration）が実際に必要とする範囲（DML：SELECT/INSERT/UPDATE/DELETE、DDL：CREATE/ALTER/INDEX/DROP、外部キー制約用のREFERENCES）に絞った最小権限とする
- インスタンス自体（CPU・メモリ・IOPS・自動バックアップ設定）はfamigoと共用のため、パラメータグループ変更やバックアップ保持期間の変更は行わない（要件定義書4.3参照）
- スキーマ管理はLaravel Migrationで行う（Terraformの管轄外）

### 3.2 EC2相乗り

`famigo-ec2`（t3.micro）上に、famigoアプリ（Docker Composeで稼働するSpring Bootコンテナ）と本システムのLaravelアプリを共存させる。

**docker-compose.ymlはfamigoと完全に分離する（同一ファイルに統合しない）。**

- famigo用とは別に、本システム専用の`docker-compose.yml`を別ディレクトリに配置する（例：famigo側`/opt/famigo/docker-compose.yml`、本システム側`/opt/civil/docker-compose.yml`）
- デプロイ時は`docker compose -f /opt/civil/docker-compose.yml up -d`のように**対象ファイルを明示して実行する**。1つのファイルに両アプリのservicesを同居させると、どちらかのデプロイ時に実行する`docker compose up -d`がデフォルトでファイル内の全serviceを対象にしてしまい、意図せずもう片方のコンテナまで再起動される事故につながる。ファイル自体を分離することでこのリスクを構造的に防ぐ
- 本システム側のコンテナ構成：**Nginx + PHP-FPMの2コンテナ**（Laravelは「PHP-FPMのみ」では画面を返せず、リバースプロキシ・静的ファイル配信を担うNginxが別途必要なため）
  - famigo：既存の8080番ポート（変更しない）
  - 本システム：新規ポート（例：8081）でNginxがLISTEN、PHP-FPMはNginxからのみ到達可能な内部ポート
- famigoのデプロイフロー（GitHub Actions → S3 → SSM RunCommand → EC2上で`docker compose up -d`）と同様の方式を踏襲し、保守性を揃える。ただしdocker-compose.ymlの分離により、実行対象は本システム用ファイルのみに限定する
- OS・Dockerエンジン自体のアップデート、再起動を伴う作業はfamigoにも影響するため、実施タイミングに注意する

### 3.3 Dockerネットワーク

famigo用コンテナと本システム用コンテナで、**Dockerネットワークを分離する**（`famigo-network` / `civil-network`のように、docker-compose.yml側でネットワークを分ける）。同一ネットワークに乗せると、コンテナ名による名前解決で互いに到達可能になり、意図しない結合が生まれるため。

### 3.4 EC2のリソース検証

t3.micro（メモリ2GB）は、famigo（Java/Spring Boot、単体で目安300〜500MB）と本システム（PHP/Laravel + Nginx）を同時稼働させるとメモリが逼迫する可能性がある。Docker・OSのオーバーヘッドを含めると、2アプリ合計で1GB近くに達することも想定されるため、実測での検証を必須とする。

- デプロイ後、EC2上で`free -h`（OS全体のメモリ使用状況）・`docker stats`（コンテナ単位のCPU・メモリ使用率）を実行し、実測値を本ドキュメントに記録する
- 目安として、メモリ使用率・CPU使用率が常時80%を超える場合は、EC2インスタンスタイプのスケールアップ（例：t3.small）を検討する
- `[要検討: 実装・デプロイ完了後に実測し、本節に結果を追記]`

### 3.5 ALB相乗り方式

現行の443リスナーはルール分岐なし単純forwardのため、以下を新設する。

- 本システム用のターゲットグループを新規作成（例：`civil-request-tg-8081`）
- **Laravel側に`GET /health`エンドポイントを実装し、`200 OK`を返すようにする**（famigo側は`famigo-tg-8080`のヘルスチェックパス`/health`を既に実装済み。本システム側のターゲットグループも同様にヘルスチェックパスを`/health`とし、Laravel側にルーティング・レスポンスを実装しないとターゲットグループが常にunhealthy判定になり公開できない）
- 443リスナーに新規ルールを追加し、**ホストベースルーティング**で振り分ける（「3.6 ドメイン方針」参照。既存の`famigo-tg-8080`へのデフォルトforwardはそのまま維持し、新規ルールを追加する形にする）
- EC2側のSG（`famigo-sg-ec2`）に、新設ポート（例：8081）をALB SGから許可するインバウンドルールを追加

### 3.6 ドメイン方針

`famigo-odekake.com`のサブドメイン間借りは行わない。**本システム専用の独自ドメインを新規取得する。**

- 理由：サブドメイン間借り（例：`civil-request.famigo-odekake.com`）だと、URLに`famigo-odekake`という文字列が必ず残り、本システムとfamigoが無関係であることが利用者から見て分かりにくい。安価な独自ドメイン（年額数百円〜のTLDを含めて検討）を新規取得することで、この混乱を避ける
- ACM証明書は、この新規ドメイン用に発行する（DNS検証を利用するため、当該ドメインのRoute53ホストゾーンが必要）
- famigo側の既存ドメイン・レコード（`famigo-odekake.com`、`api.famigo-odekake.com`）には一切影響しない
- ドメインの取得先（レジストラ）はRoute53である必要はない。お名前.com等の安価なレジストラで取得し、DNS（ネームサーバー）だけをRoute53に向ける運用でよい。この場合、Route53側では当該ドメインのホストゾーンを作成し、そのゾーンのNSレコード群をレジストラ側のネームサーバー設定に登録する
- 具体的なドメイン名・TLD・取得先レジストラは実装時に確定する（`[要検討]`）

### 3.7 追加コストの見込み

| 項目 | 追加コスト |
|---|---|
| RDSスキーマ追加 | なし（ストレージ逼迫まで無料） |
| EC2アプリ追加 | なし（同一インスタンス内） |
| ALBターゲットグループ・リスナールール追加 | なし（詳細は「3.8 ALBコストの実態」参照） |
| 独自ドメイン取得 | ドメイン代（年額。安価なTLDを選定すれば年数百円〜） |
| 新規Route53ホストゾーン作成 | 約0.5USD/月（ホストゾーン1つあたりの固定費） |
| ACM証明書 | 無料 |

### 3.8 ALBコストの実態（Cost Explorerで確認、2026-07-20時点）

ALB自体は既にfamigo用として稼働しており、本システムの相乗りとは無関係に以下のコストが発生している。

| 費目 | 2026年6月の実績 |
|---|---|
| `LoadBalancerUsage`（稼働時間課金） | 17.496 USD（720時間＝1ヶ月フル稼働分） |
| `LCUUsage`（トラフィック処理量に応じた従量課金） | 0.0079 USD（ほぼゼロ） |
| 合計 | 約17.5 USD／月（約2,700円／月） |

月額コストの99.9%以上が「ALBが起動していること自体」に対する固定の稼働時間課金であり、実際のトラフィック量（LCU）による課金は誤差レベル。**本システムを既存ALBに相乗りさせても、稼働時間課金は1個のALB分のまま変わらず追加0円。トラフィック増加分のLCU課金も、想定同時利用者数30人・年間データ量1,000件程度の規模では数十円未満の増加に留まる見込み。** このALB月額約2,700円自体はfamigo運用に伴う既存コストであり、本システムの新規負担ではない。

---

## 4. Terraform化方針

### 4.1 スコープ

**famigoの既存リソースは原則data source参照とし、Terraformでコード管理（import）するのは本システムが新規追加するリソース、および新規リソースが直接依存し変更を加える既存リソースに限定する。**

- 全リソース（VPC・Subnet・RouteTable・NACL・IGW・SG・ALB・Listener・EC2・RDS・Route53・ACM・IAM等）を一律importする方針は、個人開発の工数に対して過大と判断し採らない。importしたリソースは実物との差分（設定漂流）を継続的に追従する管理コストも発生し続けるため、変更を予定しない既存部分まで抱え込む必要は薄い
- 一方で、`aws_lb_listener`のように**新規ルール（`aws_lb_listener_rule`）を追加する対象そのもの**は、参照だけでなく管理下に置かないと安全に追加できないため、この種のリソースは例外的にimportする
- 使い分けの基準：
  - **data source参照**（import不要）：VPC・Subnet・既存SG（`famigo-sg-ec2`等）・RDSインスタンス本体・EC2インスタンス本体・既存ALB本体・既存Route53ホストゾーン（famigo側）など、参照はするが本システム側から変更を加えない既存リソース
  - **import（管理下に置く）**：ALBの既存Listener（新規リスナールールを追加するため）。それ以外は新規作成リソースとして`resource`ブロックで素直に追加する

### 4.2 手順

1. `data "aws_vpc"` `data "aws_subnet"` `data "aws_security_group"` `data "aws_lb"` `data "aws_db_instance"` `data "aws_route53_zone"`（本システム用に新規取得するドメインのゾーンは`resource`側で新規作成）等で、依存する既存famigoリソースを参照する
2. ALBの既存Listener（443）のみ`terraform import`でtfstateに取り込み、`terraform plan`で差分ゼロを確認する（**差分が残ったまま`apply`すると既存famigoの443リスナー設定が意図せず変更されるリスクがあるため、差分ゼロを確認するまでは`apply`しない**）
3. 本システム用の新規リソース（SGルール、ターゲットグループ、リスナールール、Route53ホストゾーン・レコード、ACM証明書等）を`resource`として追加し、`plan`で追加差分のみであることを確認した上で`apply`する

### 4.3 Terraformの管轄外とするもの

- RDS内のデータベース（スキーマ）・ユーザー作成（`CREATE DATABASE` / `CREATE USER`）：Laravel Migration側で管理
- EC2上のコンテナ内部の設定（Dockerfile、Nginx server_block、PHP-FPM pool設定等）・docker-compose.ymlの中身：Terraformの対象はEC2インスタンス自体（起動設定・SG・アタッチするIAMロール等）までとし、コンテナ内部の構成はアプリケーションリポジトリ側で管理する（`[要検討: 将来的にUser Data / Systems Manager等での自動デプロイを検討]`）

### 4.4 State管理

- tfstateの保管先は`[要検討: S3 + DynamoDBロックか、個人用途のためlocal stateで十分か]`
- 単独の個人開発であり、複数人・複数端末からの同時applyを想定しないため、チーム開発を前提としたstate管理の厳格化（リモートバックエンド必須化等）は現時点では過剰と判断する。まずはlocal stateで進め、共同編集や複数端末からの運用が必要になった時点でリモート化を検討する。採用した方針とその理由は、実装時にリポジトリのREADME等にも明記する

---

## 5. 今後決めること

- [ ] 本システム用独自ドメインの選定・取得（ドメイン名・TLD・取得先レジストラを確定。「3.6 ドメイン方針」参照）
- [ ] EC2（t3.micro）でfamigo（Java/Spring Boot）・本システム（PHP/Laravel + Nginx）2アプリ同時稼働時のメモリ・CPU使用率を`free -h`・`docker stats`で実測し、本ドキュメント「3.4 EC2のリソース検証」に結果を追記
- [ ] famigo-ec2上の既存docker-compose.yml・ディレクトリ構成の現物確認（`/opt/famigo/`配下の実際のパス名等）、本システム用docker-compose.ymlの配置・分離方法の具体化
- [ ] famigo-sg-ec2の現物ルール確認（SSH許可元IP等、CLIでの要約は簡略化されているため`.tf`化前に詳細を再取得）
- [ ] Terraform tfstateの保管方式確定
- [ ] EC2上のアプリ切り替え・デプロイ手順の確定（famigoのGitHub Actions + SSM RunCommand方式を踏襲する方向で、本システム用のワークフローを具体化）
- [ ] Laravel側`/health`エンドポイントの実装（「3.5 ALB相乗り方式」参照）
