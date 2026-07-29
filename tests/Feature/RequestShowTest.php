<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 案件詳細（RequestController::show）のテスト。
 * 表示・事務所スコープによる404・編集ボタンの権限出し分けを検証する。
 */
class RequestShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一般職員（事務所所属）を作成してログインさせる。
     */
    private function actingAsStaff(?Office $office = null, string $department = 'road'): User
    {
        $office ??= Office::factory()->create();
        $user = User::factory()->create([
            'office_id' => $office->id,
            'department' => $department,
            'role' => 'staff',
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_未ログインは詳細にアクセスできない(): void
    {
        $request = Request::factory()->create();

        $this->get(route('requests.show', $request))->assertRedirect('/login');
    }

    public function test_一般職員は自事務所の案件詳細を表示できる(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create([
            'office_id' => $office->id,
            'content' => '路面にポットホールがあり危険です。',
        ]);

        $this->actingAsStaff($office);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee($request->reception_number)
            ->assertSee('路面にポットホールがあり危険です。');
    }

    public function test_他事務所の案件詳細は404になる(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $others = Request::factory()->create(['office_id' => $officeB->id]);

        $this->actingAsStaff($officeA);

        $this->get(route('requests.show', $others))->assertNotFound();
    }

    public function test_管理者は他事務所の案件詳細も表示できる(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create(['office_id' => $office->id]);

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee($request->reception_number)
            // 管理者は事務所が混在するため、詳細でどの事務所の案件か分かること。
            ->assertSee($office->name);
    }

    public function test_受付方法がその他の場合は詳細が併記される(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create([
            'office_id' => $office->id,
            'reception_method' => 'other',
            'reception_method_other' => 'SNSのダイレクトメッセージ',
        ]);

        $this->actingAsStaff($office);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('SNSのダイレクトメッセージ');
    }

    public function test_存在しない案件は404になる(): void
    {
        $this->actingAsStaff();

        $this->get(route('requests.show', 999999))->assertNotFound();
    }

    public function test_緯度経度がある案件の詳細には地図が表示される(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create([
            'office_id' => $office->id,
            'latitude' => 35.681200,
            'longitude' => 139.767100,
        ]);

        $this->actingAsStaff($office);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('vendor/leaflet/leaflet.js')
            ->assertSee('detail-map');
    }

    public function test_緯度経度がない案件の詳細には地図を表示しない(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create([
            'office_id' => $office->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAsStaff($office);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('detail-map');
    }
}
