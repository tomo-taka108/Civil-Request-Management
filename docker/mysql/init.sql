-- ローカル開発用MySQL初期化スクリプト
-- 本番（famigo-mysql相乗り）と文字コード・照合順序を揃える（infrastructure-design.md 3.1節）。
--
-- MYSQL_DATABASE 環境変数によるDB作成はコンテナ起動時にデフォルト照合順序
-- （utf8mb4_0900_ai_ci）で先に実行され、その後にこの init.sql が走る。
-- そのため CREATE ではなく ALTER でDB全体のデフォルト照合順序を矯正する
-- （各テーブルの照合順序は Laravel 側の DB_COLLATION 設定で個別に指定する）。
ALTER DATABASE civil_request_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_ja_0900_as_cs;
