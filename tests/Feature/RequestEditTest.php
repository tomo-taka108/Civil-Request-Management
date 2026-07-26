<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 案件編集・削除（RequestController::edit / update / destroy）のテスト。
 * 権限（担当部署・事務所スコープ）・更新・論理削除・完了日整合を検証する。
 */
class RequestEditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般職員を作成してログインさせる。
     */
    private function actingAsStaff(Office $office, string $department = 'road'): User
    {
        $user = User::factory()->create([
            'office_id' => $office->id,
            'department' => $department,
            'role' => 'staff',
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * 有効な更新フォームの入力値。
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
            'content' => '更新後の内容です。',
            'request_type' => 'complaint',
            'response_necessity' => 'yes',
            'urgency' => 'medium',
            'response_status' => 'not_started',
        ], $overrides);
    }

    // --- 編集フォーム表示 ---

    public function test_担当部署が一致する職員は編集画面を表示できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->get(route('requests.edit', $request))
            ->assertOk()
            ->assertSee('案件編集')
            ->assertSee($request->reception_number);
    }

    public function test_担当部署が異なる職員は編集画面にアクセスできない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->get(route('requests.edit', $request))->assertForbidden();
    }

    public function test_他事務所の案件は編集画面が404になる(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $this->actingAsStaff($officeA, 'road');
        $others = Request::factory()->create(['office_id' => $officeB->id, 'department' => 'road']);

        $this->get(route('requests.edit', $others))->assertNotFound();
    }

    public function test_管理者は他事務所_他部署でも編集画面を表示できる(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'sabo']);

        $this->get(route('requests.edit', $request))->assertOk();
    }

    // --- 更新 ---

    public function test_担当部署が一致する職員は更新できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->put(route('requests.update', $request), $this->validPayload(['content' => '更新しました。']))
            ->assertRedirect(route('requests.show', $request))
            ->assertSessionHas('status');

        $this->assertSame('更新しました。', $request->fresh()->content);
    }

    public function test_更新しても事務所_登録者_受付番号は変わらない(): void
    {
        $office = Office::factory()->create();
        $user = $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create([
            'office_id' => $office->id,
            'department' => 'road',
            'registered_by' => $user->id,
        ]);
        $originalNumber = $request->reception_number;

        // 悪意ある入力：事務所・登録者・受付番号の改ざんを試みる
        $this->put(route('requests.update', $request), $this->validPayload([
            'office_id' => 99999,
            'registered_by' => 99999,
            'reception_number' => '9999-9999',
        ]));

        $fresh = $request->fresh();
        $this->assertSame($office->id, $fresh->office_id);
        $this->assertSame($user->id, $fresh->registered_by);
        $this->assertSame($originalNumber, $fresh->reception_number);
    }

    public function test_担当部署が異なる職員は更新できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->put(route('requests.update', $request), $this->validPayload())
            ->assertForbidden();
    }

    // --- 完了日と対応状況の整合 ---

    public function test_対応完了なのに完了日が空だとエラーになる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->put(route('requests.update', $request), $this->validPayload([
            'response_status' => 'completed',
            'response_completed_date' => '',
        ]))->assertSessionHasErrors('response_completed_date');
    }

    public function test_未完了なのに完了日を入れるとエラーになる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->put(route('requests.update', $request), $this->validPayload([
            'response_status' => 'in_progress',
            'response_completed_date' => '2026-07-26',
        ]))->assertSessionHasErrors('response_completed_date');
    }

    public function test_対応完了かつ完了日ありなら更新できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->put(route('requests.update', $request), $this->validPayload([
            'response_status' => 'completed',
            'response_completed_date' => '2026-07-26',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('completed', $request->fresh()->response_status);
    }

    // --- 削除（論理削除） ---

    public function test_担当部署が一致する職員は削除できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->delete(route('requests.destroy', $request))
            ->assertRedirect(route('requests.index'))
            ->assertSessionHas('status');

        // 論理削除：レコードは残るが deleted_at がセットされる
        $this->assertSoftDeleted('requests', ['id' => $request->id]);
    }

    public function test_担当部署が異なる職員は削除できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->delete(route('requests.destroy', $request))->assertForbidden();
        $this->assertDatabaseHas('requests', ['id' => $request->id, 'deleted_at' => null]);
    }

    public function test_他事務所の案件は削除できない(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $this->actingAsStaff($officeA, 'road');
        $others = Request::factory()->create(['office_id' => $officeB->id, 'department' => 'road']);

        $this->delete(route('requests.destroy', $others))->assertNotFound();
    }

    public function test_削除された案件は一覧に表示されない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $request->delete();

        $this->get(route('requests.index'))->assertDontSee($request->reception_number);
    }
}
