<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use App\Support\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 進捗ダッシュボード（DashboardController）のテスト。
 *
 * 事務所スコープ（一般職員は自事務所のみ、管理者は全事務所）が集計にも
 * 正しく適用されることを重点的に検証する。
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStaff(?Office $office = null, string $department = 'road'): User
    {
        $office ??= Office::factory()->create();
        $user = User::factory()->create([
            'office_id' => $office->id,
            'role' => 'staff',
            'department' => $department,
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_未認証はログインへリダイレクトされる(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect('/login');
    }

    public function test_ログイン後のトップはダッシュボードにリダイレクトされる(): void
    {
        $this->actingAsStaff();

        $this->get('/')->assertRedirect(route('dashboard.index'));
    }

    public function test_一般職員の集計に他事務所の件数が混ざらない(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        Request::factory()->count(3)->create(['office_id' => $officeA->id, 'response_status' => 'not_started']);
        Request::factory()->count(5)->create(['office_id' => $officeB->id, 'response_status' => 'not_started']);

        $this->actingAsStaff($officeA);

        $this->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('ダッシュボード');

        $stats = $this->currentDashboardStats();
        $this->assertSame(3, $stats->totalCount);
        $this->assertSame(3, $stats->byStatus['not_started']);
    }

    public function test_管理者は全事務所の合計と事務所別内訳を見られる(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        Request::factory()->count(2)->create(['office_id' => $officeA->id]);
        Request::factory()->count(4)->create(['office_id' => $officeB->id]);

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        $response = $this->get(route('dashboard.index'))->assertOk();
        $response->assertSee('事務所別の内訳');
        $response->assertSee($officeA->name);
        $response->assertSee($officeB->name);
    }

    public function test_一般職員には事務所別の内訳が表示されない(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee('事務所別の内訳');
    }

    public function test_論理削除済みの案件は集計に含まれない(): void
    {
        $office = Office::factory()->create();
        Request::factory()->create(['office_id' => $office->id])->delete();
        Request::factory()->create(['office_id' => $office->id]);

        $this->actingAsStaff($office);

        $this->get(route('dashboard.index'))->assertOk();

        $this->assertSame(1, (new DashboardStats(false))->totalCount);
    }

    public function test_状況別緊急性別部署別の件数が正しい(): void
    {
        $office = Office::factory()->create();
        Request::factory()->create(['office_id' => $office->id, 'department' => 'road', 'urgency' => 'high', 'response_status' => 'not_started']);
        Request::factory()->create(['office_id' => $office->id, 'department' => 'road', 'urgency' => 'low', 'response_status' => 'completed']);
        Request::factory()->create(['office_id' => $office->id, 'department' => 'river', 'urgency' => 'medium', 'response_status' => 'in_progress']);

        $this->actingAsStaff($office);

        $this->get(route('dashboard.index'))->assertOk();

        $stats = $this->currentDashboardStats();
        $this->assertSame(1, $stats->byStatus['not_started']);
        $this->assertSame(1, $stats->byStatus['in_progress']);
        $this->assertSame(1, $stats->byStatus['completed']);
        $this->assertSame(1, $stats->byUrgency['high']);
        $this->assertSame(1, $stats->byUrgency['medium']);
        $this->assertSame(1, $stats->byUrgency['low']);
        $this->assertSame(2, $stats->matrix['road']['not_started'] + $stats->matrix['road']['completed']);
        $this->assertSame(1, $stats->matrix['river']['in_progress']);
    }

    public function test_未対応かつ緊急性高の件数が正しい(): void
    {
        $office = Office::factory()->create();
        Request::factory()->create(['office_id' => $office->id, 'urgency' => 'high', 'response_status' => 'not_started']);
        Request::factory()->create(['office_id' => $office->id, 'urgency' => 'high', 'response_status' => 'in_progress']);
        Request::factory()->create(['office_id' => $office->id, 'urgency' => 'low', 'response_status' => 'not_started']);

        $this->actingAsStaff($office);

        $stats = $this->currentDashboardStats();
        $this->assertSame(1, $stats->highNotStartedCount);
    }

    public function test_30日以上更新がなく未完了の案件がstale件数に含まれる(): void
    {
        $office = Office::factory()->create();

        $stale = Request::factory()->create(['office_id' => $office->id, 'response_status' => 'not_started']);
        $stale->forceFill(['updated_at' => now()->subDays(31)])->save();

        $recent = Request::factory()->create(['office_id' => $office->id, 'response_status' => 'not_started']);
        $recent->forceFill(['updated_at' => now()->subDays(10)])->save();

        $completed = Request::factory()->create(['office_id' => $office->id, 'response_status' => 'completed']);
        $completed->forceFill(['updated_at' => now()->subDays(31)])->save();

        $this->actingAsStaff($office);

        $stats = $this->currentDashboardStats();
        $this->assertSame(1, $stats->staleCount);
    }

    public function test_要対応リストは事務所スコープ内かつ最大10件(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        Request::factory()->count(12)->create([
            'office_id' => $officeA->id,
            'urgency' => 'high',
            'response_status' => 'not_started',
        ]);
        Request::factory()->create([
            'office_id' => $officeB->id,
            'urgency' => 'high',
            'response_status' => 'not_started',
        ]);

        $this->actingAsStaff($officeA);

        $stats = $this->currentDashboardStats();
        $this->assertCount(10, $stats->attentionList);
        $this->assertTrue($stats->attentionList->every(fn ($r) => $r->office_id === $officeA->id));
    }

    public function test_要対応サマリーのリンクに検索条件が付与される(): void
    {
        $this->actingAsStaff();

        $this->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('response_status%5B0%5D=not_started', false)
            ->assertSee('stale=1', false);
    }

    /**
     * 現在ログイン中のユーザー（一般職員）の事務所スコープが適用された状態で
     * DashboardStats を生成する。
     */
    private function currentDashboardStats(): DashboardStats
    {
        return new DashboardStats(false);
    }
}
