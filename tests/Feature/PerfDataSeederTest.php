<?php

namespace Tests\Feature;

use App\Models\Request;
use App\Models\User;
use Database\Seeders\PerfDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * 負荷試験用データ（PerfDataSeeder）は試験専用 DB 以外では動かないこと
 * （パフォーマンステスト設計書 3章）。開発用・本番の DB に誤って流しても、
 * 試験用の職員や3,000件の案件が混ざらないようにする安全装置の確認。
 */
class PerfDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_試験専用DB以外では何も投入せずに止まる(): void
    {
        try {
            $this->seed(PerfDataSeeder::class);
            $this->fail('試験専用 DB 以外で動いてしまった');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(PerfDataSeeder::DATABASE, $e->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, Request::withoutGlobalScopes()->count());
    }
}
