<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * offices（事務所マスタ）
 *
 * データベース設計書 2.1 に対応。
 * 要件定義書 1.3「事務所ごとのデータ分離方式」に基づき、
 * ユーザー・案件を事務所単位で分離するためのマスタ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('事務所名（例：〇〇土木事務所）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
