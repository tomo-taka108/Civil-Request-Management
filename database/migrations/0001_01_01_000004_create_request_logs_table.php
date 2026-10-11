<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * request_logs（対応履歴：案件に対する対応内容・経過の記録）
 *
 * 要件定義書 §1.1「過去の要望・対応履歴の検索・参照に時間がかかる」を解消するための
 * 追加機能（当初の要件定義書 §3 では「対応内容・経過記録」はスコープ外としていたが、
 * モックアップ合意（Issue #79 / PR #80）を踏まえて正式な機能要件に追加する）。
 *
 * - office_id は持たせない。ログは必ず requests 経由でアクセスされるため、
 *   事務所スコープは Request モデルの OfficeScope に一元化する。
 * - restrictOnDelete：requests は論理削除のため、対応する request が消えても
 *   request_logs は残す（親の論理削除時に外部キー制約で落ちないよう、削除は
 *   アプリ側で論理削除のみを行う運用とする）。
 * - deleted_at による論理削除（誤登録の削除後も痕跡を残す）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->date('logged_on')->comment('対応日（実際に対応した日）');
            $table->text('content')->comment('対応内容・経過');
            $table->foreignId('recorded_by')->comment('記録者')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // タイムラインの表示順（logged_on 降順）・案件ごとの履歴取得のため。
            $table->index(['request_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
