<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 地図表示（MapController::index / pins）のテスト。
 * 認証・事務所スコープ・検索条件の適用・GeoJSON形式・緯度経度なしの除外を検証する。
 */
class MapTest extends TestCase
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

    public function test_未ログインは地図画面にアクセスできない(): void
    {
        $this->get(route('map.index'))->assertRedirect('/login');
    }

    public function test_未ログインはピン取得にアクセスできない(): void
    {
        $this->get(route('map.pins'))->assertRedirect('/login');
    }

    public function test_一般職員は地図画面を表示できる(): void
    {
        $this->actingAsStaff();

        $this->get(route('map.index'))
            ->assertOk()
            ->assertSee('地図表示')
            // Leaflet アセットとピン取得エンドポイントが読み込まれていること。
            // （pins URL は @json でスラッシュがエスケープされるため map\/pins で照合）
            ->assertSee('vendor/leaflet/leaflet.js')
            ->assertSee('map\/pins', false);
    }

    public function test_ピンはGeoJSONのFeatureCollectionで返る(): void
    {
        $office = Office::factory()->create();
        Request::factory()->create([
            'office_id' => $office->id,
            'latitude' => 35.681200,
            'longitude' => 139.767100,
        ]);

        $this->actingAsStaff($office);

        $this->get(route('map.pins'))
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(1, 'features')
            // GeoJSON は [経度, 緯度] の順。
            ->assertJsonPath('features.0.geometry.coordinates.0', 139.7671)
            ->assertJsonPath('features.0.geometry.coordinates.1', 35.6812);
    }

    public function test_緯度経度が無い案件はピンに含まれない(): void
    {
        $office = Office::factory()->create();
        Request::factory()->create([
            'office_id' => $office->id,
            'latitude' => 35.681200,
            'longitude' => 139.767100,
        ]);
        // 位置情報なし（地図に出せない）。
        Request::factory()->create([
            'office_id' => $office->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->actingAsStaff($office);

        $this->get(route('map.pins'))
            ->assertOk()
            ->assertJsonCount(1, 'features');
    }

    public function test_一般職員は自事務所のピンのみ取得できる(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $mine = Request::factory()->create([
            'office_id' => $officeA->id,
            'latitude' => 35.0,
            'longitude' => 139.0,
        ]);
        Request::factory()->create([
            'office_id' => $officeB->id,
            'latitude' => 34.0,
            'longitude' => 135.0,
        ]);

        $this->actingAsStaff($officeA);

        $this->get(route('map.pins'))
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.reception_number', $mine->reception_number);
    }

    public function test_管理者は全事務所のピンを取得できる(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Request::factory()->create(['office_id' => $officeA->id, 'latitude' => 35.0, 'longitude' => 139.0]);
        Request::factory()->create(['office_id' => $officeB->id, 'latitude' => 34.0, 'longitude' => 135.0]);

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        $this->get(route('map.pins'))
            ->assertOk()
            ->assertJsonCount(2, 'features');
    }

    public function test_ピンは検索条件で絞り込める(): void
    {
        $office = Office::factory()->create();
        $road = Request::factory()->create([
            'office_id' => $office->id, 'department' => 'road',
            'latitude' => 35.0, 'longitude' => 139.0,
        ]);
        Request::factory()->create([
            'office_id' => $office->id, 'department' => 'river',
            'latitude' => 35.1, 'longitude' => 139.1,
        ]);

        $this->actingAsStaff($office);

        $this->get(route('map.pins', ['department' => ['road']]))
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.reception_number', $road->reception_number);
    }

    public function test_不正なenum検索値はバリデーションエラーになる(): void
    {
        $this->actingAsStaff();

        $this->get(route('map.pins', ['urgency' => ['invalid']]))
            ->assertSessionHasErrors('urgency.0');
    }
}
