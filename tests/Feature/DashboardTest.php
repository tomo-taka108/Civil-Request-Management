<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
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

    public function test_対応履歴のない古い受付案件がstale件数に含まれる(): void
    {
        $office = Office::factory()->create();

        // 対応履歴なし・受付から31日経過 → 動きなし
        Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'not_started',
            'reception_date' => now()->subDays(31),
        ]);

        // 対応履歴なしだが受付から10日 → まだ動きなしではない
        Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'not_started',
            'reception_date' => now()->subDays(10),
        ]);

        // 対応完了は対象外
        Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'completed',
            'reception_date' => now()->subDays(31),
        ]);

        $this->actingAsStaff($office);

        $this->assertSame(1, $this->currentDashboardStats()->staleCount);
    }

    /**
     * 受付から日が経っていても、最近の対応履歴があれば「動きあり」と判定する。
     */
    public function test_最近の対応履歴がある案件はstale件数に含まれない(): void
    {
        $office = Office::factory()->create();
        $user = $this->actingAsStaff($office);

        $request = Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'in_progress',
            'reception_date' => now()->subDays(90),
        ]);

        $log = new RequestLog(['logged_on' => now()->subDays(5), 'content' => '現地確認を実施']);
        $log->forceFill(['request_id' => $request->id, 'recorded_by' => $user->id])->save();

        $this->assertSame(0, $this->currentDashboardStats()->staleCount);
    }

    /**
     * 案件の項目を直しただけ（updated_at が新しい）でも、対応履歴が無ければ
     * 受付日から起算して「動きなし」と判定する。updated_at で判定していた頃の
     * 取りこぼしに対する回帰テスト（Issue #85）。
     */
    public function test_対応履歴がなければupdated_atが新しくてもstale件数に含まれる(): void
    {
        $office = Office::factory()->create();

        $request = Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'not_started',
            'reception_date' => now()->subDays(70),
        ]);
        // 受付から2か月後に住所の誤記だけ訂正した状況を作る
        $request->forceFill(['updated_at' => now()])->save();

        $this->actingAsStaff($office);

        $this->assertSame(1, $this->currentDashboardStats()->staleCount);
    }

    /**
     * 古い対応履歴しか無い案件は「動きなし」。論理削除した履歴は数えない。
     */
    public function test_古い対応履歴しかない案件はstale件数に含まれる(): void
    {
        $office = Office::factory()->create();
        $user = $this->actingAsStaff($office);

        $request = Request::factory()->create([
            'office_id' => $office->id,
            'response_status' => 'in_progress',
            'reception_date' => now()->subDays(90),
        ]);

        $old = new RequestLog(['logged_on' => now()->subDays(40), 'content' => '関係者へ連絡']);
        $old->forceFill(['request_id' => $request->id, 'recorded_by' => $user->id])->save();

        // 新しい履歴を入れてから論理削除すると、判定は古い履歴に戻る
        $deleted = new RequestLog(['logged_on' => now()->subDays(2), 'content' => '誤登録']);
        $deleted->forceFill(['request_id' => $request->id, 'recorded_by' => $user->id])->save();
        $deleted->delete();

        $this->assertSame(1, $this->currentDashboardStats()->staleCount);
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
