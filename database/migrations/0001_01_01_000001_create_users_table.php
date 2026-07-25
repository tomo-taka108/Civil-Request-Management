<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users（ユーザーアカウント）
 *
 * データベース設計書 2.2、要件定義書 3.1 に対応。
 *
 * - Laravel標準の email / email_verified_at / remember_token は持たない
 *   （ユーザー本人による登録経路がなく、管理者が発行するため。要件定義書 2.5）。
 * - password カラムではなく password_hash を採用（データベース設計書 2.2）。
 *   認証実装時（次フェーズ）に User モデルで getAuthPassword() を
 *   'password_hash' を返すようオーバーライドする必要がある点に注意。
 * - office_id / department は role='admin' の場合 NULL（システム管理者は
 *   事務所・担当部署に紐づかない）。この整合はアプリ層のバリデーションで担保する
 *   （データベース設計書 2.2 の「制約」参照。DBのCHECK制約では強制しない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // 所属事務所。role='admin' は NULL。異動時はシステム管理者が更新する。
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('user_id', 50)->unique()->comment('ログインID。システム全体で一意');
            $table->string('name', 100)->comment('氏名');
            $table->string('password_hash', 255)->comment('bcrypt等でハッシュ化');
            $table->boolean('must_change_password')->default(true)->comment('初回ログイン時の強制変更判定');
            // 担当部署（道路／河川／砂防）。role='admin' は NULL。
            $table->enum('department', ['road', 'river', 'sabo'])->nullable()->comment('担当部署。編集・削除権限の判定に使用');
            $table->enum('role', ['staff', 'admin'])->default('staff')->comment('権限区分（一般職員／システム管理者）');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('アカウント状態（有効／無効化）');
            $table->timestamps();

            // 事務所ごとのユーザー一覧の絞り込み用（管理者は office_id=NULL のため対象外）
            $table->index(['office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
