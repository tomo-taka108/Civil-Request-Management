<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 初期システム管理者アカウント（データベース設計書 5章）。
 *
 * 誰も管理者がいない状態では管理者を登録できないため、初期構築時のみ
 * seeder で1件作成する運用とする。
 *
 * - role='admin' のため office_id / department は NULL（データベース設計書 2.2）。
 * - must_change_password=true。初期パスワードは本番投入前に必ず変更すること
 *   （初回ログイン時の強制変更で担保する。運用手順は README 参照）。
 */
class AdminUserSeeder extends Seeder
{
    /**
     * 初期パスワード（初回ログイン時に必ず変更する前提のローカル/初期構築用の値）。
     */
    private const INITIAL_PASSWORD = 'ChangeMe#2026';

    public function run(): void
    {
        // firstOrCreate で冪等化（既に admin が存在する場合は作成しない）
        User::firstOrCreate(
            ['user_id' => 'admin'],
            [
                'office_id' => null,
                'name' => 'システム管理者',
                'password_hash' => Hash::make(self::INITIAL_PASSWORD),
                'must_change_password' => true,
                'department' => null,
                'role' => 'admin',
                'status' => 'active',
            ],
        );
    }
}
