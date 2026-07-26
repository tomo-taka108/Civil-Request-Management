<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 案件一覧・検索（RequestController::index）のテスト。
 * 事務所スコープ・複数選択検索・期間検索・部分一致検索・権限を検証する。
 */
class RequestIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般職員（事務所所属）を作成してログインさせる。
     */
    private function actingAsStaff(?Office $office = null): User
    {
        $office ??= Office::factory()->create();
        $user = User::factory()->create([
            'office_id' => $office->id,
            'role' => 'staff',
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_未ログインは一覧にアクセスできない(): void
    {
        $this->get(route('requests.index'))->assertRedirect('/login');
    }

    public function test_一般職員は一覧画面を表示できる(): void
    {
        $this->actingAsStaff();

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSee('案件一覧');
    }

    public function test_一般職員は自事務所の案件のみ表示される(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $mine = Request::factory()->create(['office_id' => $officeA->id]);
        $others = Request::factory()->create(['office_id' => $officeB->id]);

        $this->actingAsStaff($officeA);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSee($mine->reception_number)
            ->assertDontSee($others->reception_number);
    }

    public function test_管理者は全事務所の案件が表示される(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $a = Request::factory()->create(['office_id' => $officeA->id]);
        $b = Request::factory()->create(['office_id' => $officeB->id]);

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSee($a->reception_number)
            ->assertSee($b->reception_number);
    }

    public function test_対応部署で複数選択検索できる(): void
    {
        $office = Office::factory()->create();
        $road = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $river = Request::factory()->create(['office_id' => $office->id, 'department' => 'river']);
        $sabo = Request::factory()->create(['office_id' => $office->id, 'department' => 'sabo']);

        $this->actingAsStaff($office);

        // 道路・河川を選択 → 砂防は除外される（項目内はOR）
        $this->get(route('requests.index', ['department' => ['road', 'river']]))
            ->assertSee($road->reception_number)
            ->assertSee($river->reception_number)
            ->assertDontSee($sabo->reception_number);
    }

    public function test_対応状況で検索できる(): void
    {
        $office = Office::factory()->create();
        $notStarted = Request::factory()->create(['office_id' => $office->id, 'response_status' => 'not_started']);
        $completed = Request::factory()->create(['office_id' => $office->id, 'response_status' => 'completed']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', ['response_status' => ['not_started']]))
            ->assertSee($notStarted->reception_number)
            ->assertDontSee($completed->reception_number);
    }

    public function test_緊急性で検索できる(): void
    {
        $office = Office::factory()->create();
        $high = Request::factory()->create(['office_id' => $office->id, 'urgency' => 'high']);
        $low = Request::factory()->create(['office_id' => $office->id, 'urgency' => 'low']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', ['urgency' => ['high']]))
            ->assertSee($high->reception_number)
            ->assertDontSee($low->reception_number);
    }

    public function test_受付日の期間で検索できる(): void
    {
        $office = Office::factory()->create();
        $inRange = Request::factory()->create(['office_id' => $office->id, 'reception_date' => '2026-07-10']);
        $before = Request::factory()->create(['office_id' => $office->id, 'reception_date' => '2026-06-30']);
        $after = Request::factory()->create(['office_id' => $office->id, 'reception_date' => '2026-08-01']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', [
            'reception_date_from' => '2026-07-01',
            'reception_date_to' => '2026-07-31',
        ]))
            ->assertSee($inRange->reception_number)
            ->assertDontSee($before->reception_number)
            ->assertDontSee($after->reception_number);
    }

    public function test_地区_場所で部分一致検索できる(): void
    {
        $office = Office::factory()->create();
        $hit = Request::factory()->create(['office_id' => $office->id, 'address' => 'サンプル市さくら町1丁目']);
        $miss = Request::factory()->create(['office_id' => $office->id, 'address' => 'サンプル市もみじ町2丁目']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', ['address' => 'さくら']))
            ->assertSee($hit->reception_number)
            ->assertDontSee($miss->reception_number);
    }

    public function test_キーワードで要望内容を部分一致検索できる(): void
    {
        $office = Office::factory()->create();
        $hit = Request::factory()->create(['office_id' => $office->id, 'content' => '路面にポットホールがあり危険です。']);
        $miss = Request::factory()->create(['office_id' => $office->id, 'content' => '街路樹の枝が伸びています。']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', ['keyword' => 'ポットホール']))
            ->assertSee($hit->reception_number)
            ->assertDontSee($miss->reception_number);
    }

    public function test_複数条件はAND条件で絞り込まれる(): void
    {
        $office = Office::factory()->create();
        // 道路 かつ 高 のみヒットさせる
        $match = Request::factory()->create(['office_id' => $office->id, 'department' => 'road', 'urgency' => 'high']);
        $onlyDept = Request::factory()->create(['office_id' => $office->id, 'department' => 'road', 'urgency' => 'low']);
        $onlyUrgency = Request::factory()->create(['office_id' => $office->id, 'department' => 'river', 'urgency' => 'high']);

        $this->actingAsStaff($office);

        $this->get(route('requests.index', ['department' => ['road'], 'urgency' => ['high']]))
            ->assertSee($match->reception_number)
            ->assertDontSee($onlyDept->reception_number)
            ->assertDontSee($onlyUrgency->reception_number);
    }

    public function test_不正なenum検索値はバリデーションエラーになる(): void
    {
        $this->actingAsStaff();

        $this->get(route('requests.index', ['department' => ['invalid']]))
            ->assertSessionHasErrors('department.0');
    }
}
