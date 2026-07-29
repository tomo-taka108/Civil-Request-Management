<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OfficeSeeder::class,
            AdminUserSeeder::class,
            // 体験用サンプル（ローカル・本番共用のポートフォリオ初期データ）。
            // 職員 → 案件の順（案件の登録者に職員が必要）。いずれも冪等。
            SampleStaffSeeder::class,
            SampleRequestSeeder::class,
        ]);
    }
}
