<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 案件登録（RequestController::create / store）のテスト。
 * 採番・連番・事務所独立・スコープ強制・バリデーション・権限を検証する。
 */
class RequestControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 有効な登録フォームの入力値。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'reception_date' => '2026-07-26',
            'reception_time' => '09:00',
            'reception_method' => 'phone',
            'requester_category' => 'individual',
            'requester_name' => 'テスト要望者',
            'department' => 'road',
            'content' => '道路に穴が空いている。',
            'request_type' => 'complaint',
            'address' => 'サンプル市サンプル町1丁目',
            'response_necessity' => 'yes',
            'urgency' => 'medium',
            'response_status' => 'not_started',
        ], $overrides);
    }

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

    public function test_一般職員は登録画面を表示できる(): void
    {
        $this->actingAsStaff();

        $this->get(route('requests.create'))
            ->assertOk()
            ->assertSee('新規登録');
    }

    public function test_未ログインは登録画面にアクセスできない(): void
    {
        $this->get(route('requests.create'))->assertRedirect('/login');
    }

    public function test_管理者は登録できない(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('requests.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('requests.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_登録に成功しDBに保存される(): void
    {
        $user = $this->actingAsStaff();

        $this->post(route('requests.store'), $this->validPayload())
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('requests', 1);

        $request = Request::first();
        // 登録者・事務所はログインユーザーから自動設定される
        $this->assertSame($user->id, $request->registered_by);
        $this->assertSame($user->office_id, $request->office_id);
    }

    public function test_受付番号が年_連番4桁で採番される(): void
    {
        $this->actingAsStaff();
        $year = now()->format('Y');

        $this->post(route('requests.store'), $this->validPayload());

        $this->assertSame("{$year}-0001", Request::first()->reception_number);
    }

    public function test_同一事務所同一年で連番がインクリメントする(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office);
        $year = now()->format('Y');

        $this->post(route('requests.store'), $this->validPayload());
        $this->post(route('requests.store'), $this->validPayload());

        $numbers = Request::orderBy('reception_seq')->pluck('reception_number')->all();
        $this->assertSame(["{$year}-0001", "{$year}-0002"], $numbers);
    }

    public function test_別事務所は連番が独立する(): void
    {
        $year = now()->format('Y');

        // 事務所Aで1件登録
        $officeA = Office::factory()->create();
        $this->actingAsStaff($officeA);
        $this->post(route('requests.store'), $this->validPayload());

        // 事務所Bで1件登録 → Bも0001から始まる
        $officeB = Office::factory()->create();
        $this->actingAsStaff($officeB);
        $this->post(route('requests.store'), $this->validPayload());

        $bRequest = Request::where('office_id', $officeB->id)->first();
        $this->assertSame("{$year}-0001", $bRequest->reception_number);
    }

    public function test_必須項目が空だとバリデーションエラーになる(): void
    {
        $this->actingAsStaff();

        $this->post(route('requests.store'), [])
            ->assertSessionHasErrors(['reception_date', 'reception_method', 'department', 'content', 'request_type', 'response_necessity', 'urgency', 'response_status']);

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_受付方法がその他の場合は詳細入力が必須(): void
    {
        $this->actingAsStaff();

        $this->post(route('requests.store'), $this->validPayload([
            'reception_method' => 'other',
            'reception_method_other' => '',
        ]))->assertSessionHasErrors('reception_method_other');
    }

    public function test_リクエストにoffice_idを混ぜても無視される(): void
    {
        $user = $this->actingAsStaff();
        $otherOffice = Office::factory()->create();

        // 悪意ある入力：別事務所のIDを混ぜる
        $this->post(route('requests.store'), $this->validPayload([
            'office_id' => $otherOffice->id,
            'registered_by' => 99999,
        ]));

        $request = Request::first();
        // ログインユーザーの事務所・IDが強制され、混入値は無視される
        $this->assertSame($user->office_id, $request->office_id);
        $this->assertSame($user->id, $request->registered_by);
    }
}
