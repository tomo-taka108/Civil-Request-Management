<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 体験用サンプルの一般職員アカウント（ローカル・本番共用）。
 *
 * ポートフォリオ公開時に「登録者」欄などがそれらしく見えるよう、各事務所に
 * 複数名のサンプル職員を用意する。氏名は実在人物を指さない典型的なダミー名を
 * 用いる（CLAUDE.md 6章。特定の実在個人・団体は使わない）。
 *
 * - role='staff'。事務所・担当部署を持つ。
 * - must_change_password=false（公開体験用にそのままログイン確認できる）。
 * - 冪等化：user_id をキーに firstOrCreate（複数回 seed しても重複しない）。
 */
class SampleStaffSeeder extends Seeder
{
    /** 体験用サンプル職員の初期パスワード（公開デモ用の共通値）。 */
    private const SAMPLE_PASSWORD = 'Sample#2026';

    /**
     * 事務所名 => その事務所に所属させるサンプル職員（user_id / 氏名 / 担当部署）。
     *
     * user_id は staff{事務所番号}{連番} 形式で衝突しないようにする。
     *
     * @var array<string, list<array{user_id: string, name: string, department: string}>>
     */
    private const STAFF_BY_OFFICE = [
        'サンプル第一土木事務所' => [
            ['user_id' => 'staff101', 'name' => '山田 太郎', 'department' => 'road'],
            ['user_id' => 'staff102', 'name' => '佐藤 花子', 'department' => 'river'],
            ['user_id' => 'staff103', 'name' => '鈴木 一郎', 'department' => 'sabo'],
        ],
        'サンプル第二土木事務所' => [
            ['user_id' => 'staff201', 'name' => '田中 健太', 'department' => 'road'],
            ['user_id' => 'staff202', 'name' => '高橋 美咲', 'department' => 'river'],
            ['user_id' => 'staff203', 'name' => '伊藤 大輔', 'department' => 'sabo'],
        ],
        'サンプル第三土木事務所' => [
            ['user_id' => 'staff301', 'name' => '渡辺 千尋', 'department' => 'road'],
            ['user_id' => 'staff302', 'name' => '中村 直樹', 'department' => 'river'],
            ['user_id' => 'staff303', 'name' => '小林 由美', 'department' => 'sabo'],
        ],
    ];

    /**
     * このSeederが作成するサンプル職員の user_id 一覧。
     *
     * サンプル案件（SampleRequestSeeder）の冪等判定・登録者選定で参照する。
     * 既存の別ユーザー（例：手動作成の staff01）と取り違えないよう、ここで
     * 定義した user_id のみを「サンプル職員」とみなす。
     *
     * @return list<string>
     */
    public static function sampleStaffUserIds(): array
    {
        $ids = [];
        foreach (self::STAFF_BY_OFFICE as $staffList) {
            foreach ($staffList as $staff) {
                $ids[] = $staff['user_id'];
            }
        }

        return $ids;
    }

    public function run(): void
    {
        foreach (self::STAFF_BY_OFFICE as $officeName => $staffList) {
            $office = Office::firstOrCreate(['name' => $officeName]);

            foreach ($staffList as $staff) {
                User::firstOrCreate(
                    ['user_id' => $staff['user_id']],
                    [
                        'office_id' => $office->id,
                        'name' => $staff['name'],
                        'password_hash' => Hash::make(self::SAMPLE_PASSWORD),
                        'must_change_password' => false,
                        'department' => $staff['department'],
                        'role' => 'staff',
                        'status' => 'active',
                    ],
                );
            }
        }
    }
}
