<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * requests.content への FULLTEXT インデックス追加
 *
 * データベース設計書 2.3・5章、要件定義書 2.3（キーワード全文検索）に対応。
 *
 * Blueprint の fullText() ではなく生SQLで追加している理由：
 * 日本語全文検索には ngram パーサ（WITH PARSER ngram）が重要だが、その精度検証は
 * 今後の検討事項（データベース設計書 5章）。まずはパーサ指定なしの標準 FULLTEXT で作成し、
 * 将来 ngram 化が必要になった際にこのマイグレーションだけを差し替えられるよう、
 * テーブル本体（000002）とは分離して生SQLで管理する。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE requests ADD FULLTEXT ft_requests_content (content)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE requests DROP INDEX ft_requests_content');
    }
};
