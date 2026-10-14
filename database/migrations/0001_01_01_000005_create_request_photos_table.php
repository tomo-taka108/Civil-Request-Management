<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * request_photos（現場写真）。要件定義書 §2.8。
 *
 * - request_log_id が NULL の写真は「受付時の写真」（要望者提供分など、特定の対応に
 *   紐づかないもの）。値がある写真は、その対応履歴の時点で撮影したもの。
 * - path は保存ディスク上のパス。ファイル名はアプリが乱数で付けるため、
 *   利用者が付けた元のファイル名（original_name）とは別に持つ。
 * - 論理削除は持たない。写真の削除は「誤って登録した」「個人情報が写り込んだ」
 *   場合を想定するため、レコードと実ファイルの両方を物理削除する。
 * - restrictOnDelete：親の requests / request_logs は論理削除のため、
 *   物理削除で写真が巻き込まれることはない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('request_log_id')->nullable()
                ->comment('紐づく対応履歴（NULL は受付時の写真）')
                ->constrained('request_logs')->restrictOnDelete();
            $table->string('path')->comment('保存ディスク上のパス');
            $table->string('original_name')->comment('アップロード時の元のファイル名（表示用）');
            $table->unsignedInteger('size')->comment('ファイルサイズ（バイト）');
            $table->foreignId('uploaded_by')->comment('登録者')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // 案件の写真の一括取得と、対応履歴ごと・受付時の枚数カウントのため。
            $table->index(['request_id', 'request_log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_photos');
    }
};
