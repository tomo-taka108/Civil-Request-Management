<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

/**
 * 事務所マスタの初期データ（データベース設計書 5章）。
 *
 * サンプルデータには実在の個人・団体名を使用しない（CLAUDE.md 6章）。
 * 明確に架空と分かる名称を用いる。
 */
class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'サンプル第一土木事務所',
            'サンプル第二土木事務所',
            'サンプル第三土木事務所',
        ];

        foreach ($names as $name) {
            // firstOrCreate で冪等化（db:seed を複数回実行しても重複しない）
            Office::firstOrCreate(['name' => $name]);
        }
    }
}
