<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * requests（案件：苦情・要望・異常箇所）
 *
 * データベース設計書 2.3、要件定義書 3章「登録項目」に対応。
 *
 * - office_id は登録時点の登録者の所属事務所に固定される（登録後、登録者が異動しても追従しない）。
 * - reception_number は reception_year + reception_seq から生成される表示用の非正規化カラム。
 * - updated_at を表示用「最終更新日時」として用いる（専用カラムは持たない。要件定義書 2.1）。
 * - deleted_at による論理削除（Laravel SoftDeletes を使用。要件定義書 2.1）。
 * - FULLTEXT(content) は別マイグレーション（000003）で追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            // データ分離用。登録時点の登録者の所属事務所に固定。
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();

            // 受付番号（表示用）と採番管理用カラム
            $table->string('reception_number', 20)->comment('表示用受付番号。例：2026-0142');
            $table->unsignedSmallInteger('reception_year')->comment('採番管理用（西暦）');
            $table->unsignedInteger('reception_seq')->comment('採番管理用（事務所・年ごとの連番）');

            // 受付情報
            $table->date('reception_date')->comment('受付日');
            $table->time('reception_time')->comment('受付時刻');
            $table->enum('reception_method', ['window', 'phone', 'email', 'letter', 'fax', 'patrol', 'other'])
                ->comment('受付方法（窓口／電話／メール／要望書／FAX／職員パトロール／その他）');
            $table->string('reception_method_other', 255)->nullable()->comment('受付方法が「その他」の場合の自由入力');

            // 登録者（受付職員）。ログインユーザーと連動。
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();

            // 相手方情報
            $table->enum('requester_category', [
                'individual', 'neighborhood_association', 'municipality',
                'council_member', 'anonymous', 'staff_patrol', 'other',
            ])->comment('区分（個人／自治会／市町村／議員／匿名／職員パトロール／その他）');
            $table->string('requester_name', 255)->nullable()->comment('要望者。匿名／職員パトロールの場合は不要');

            // 案件情報
            $table->enum('department', ['road', 'river', 'sabo'])->comment('対応部署。編集・削除権限の判定に使用');
            $table->text('content')->comment('要望の内容');
            $table->enum('request_type', ['complaint', 'request', 'anomaly'])->comment('種別（苦情／要望／異常発見）');
            $table->decimal('latitude', 9, 6)->nullable()->comment('要望箇所の緯度');
            $table->decimal('longitude', 9, 6)->nullable()->comment('要望箇所の経度');
            $table->string('address', 255)->nullable()->comment('要望箇所の住所表記');

            // 対応情報
            $table->enum('response_necessity', ['yes', 'no', 'unknown'])->comment('対応の必要性（あり／なし／不明）');
            $table->enum('urgency', ['high', 'medium', 'low'])->comment('緊急性（高／中／低）');
            $table->text('response_policy')->nullable()->comment('対応方針');
            $table->enum('response_status', ['not_started', 'in_progress', 'completed'])
                ->default('not_started')->comment('対応状況（未対応／対応中／対応完了）');
            $table->date('response_completed_date')->nullable()->comment('対応完了になった日');

            $table->timestamps();      // updated_at は表示用「最終更新日時」を兼ねる
            $table->softDeletes();     // deleted_at（論理削除）

            // インデックス（データベース設計書 2.3）
            $table->unique(['office_id', 'reception_number']);                  // 受付番号は事務所内で一意
            $table->unique(['office_id', 'reception_year', 'reception_seq']);   // 採番の重複防止
            $table->index(['office_id', 'department', 'response_status']);      // 一覧検索（対応部署・対応状況）
            $table->index(['office_id', 'urgency']);                           // 緊急性での絞り込み
            $table->index(['office_id', 'reception_date']);                     // 受付日（期間指定）
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
