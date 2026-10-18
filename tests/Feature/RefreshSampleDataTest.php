<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * サンプル入れ替えコマンド（php artisan sample:refresh）。
 * サンプル職員の案件だけが入れ替わり、それ以外のデータが残ることを確認する。
 */
class RefreshSampleDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(RequestPhoto::disk());
        $this->seed(DatabaseSeeder::class);
    }

    public function test_サンプル案件が入れ替わりサンプル職員以外の案件は残る(): void
    {
        $sampleCount = Request::withoutGlobalScopes()->count();
        $photoCount = RequestPhoto::count();
        $oldSampleIds = Request::withoutGlobalScopes()->pluck('id');
        $oldPhotoPaths = RequestPhoto::pluck('path');

        $office = Office::where('name', 'みどり土木事務所')->firstOrFail();
        $sampleStaff = User::where('user_id', 'staff101')->firstOrFail();
        $otherStaff = User::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        // デモ用アカウントで後から登録された案件（サンプル職員が登録者）→ 消える
        $byDemoAccount = Request::factory()->create([
            'office_id' => $office->id, 'registered_by' => $sampleStaff->id,
            'reception_year' => 2099, 'reception_seq' => 1, 'reception_number' => '2099-0001',
        ]);
        // サンプル職員以外が登録した案件と、そこにサンプル職員が付けた対応履歴 → 残る
        $byOtherStaff = Request::factory()->create([
            'office_id' => $office->id, 'registered_by' => $otherStaff->id,
            'reception_year' => 2099, 'reception_seq' => 2, 'reception_number' => '2099-0002',
        ]);
        $logOnOtherCase = RequestLog::factory()->create(['request_id' => $byOtherStaff->id, 'recorded_by' => $sampleStaff->id]);
        // 論理削除済みのサンプル案件 → 消える
        Request::withoutGlobalScopes()->whereKey($oldSampleIds->first())->firstOrFail()->delete();

        $this->artisan('sample:refresh', ['--force' => true])->assertSuccessful();

        // サンプルは元と同じ件数・枚数で入れ直され、古い案件・写真ファイルは残らない。
        $this->assertSame($sampleCount, Request::withoutGlobalScopes()->where('registered_by', '!=', $otherStaff->id)->count());
        $this->assertSame($photoCount, RequestPhoto::count());
        $this->assertSame(0, Request::withTrashed()->withoutGlobalScopes()->whereIn('id', $oldSampleIds)->count());
        $this->assertNull(Request::withTrashed()->withoutGlobalScopes()->find($byDemoAccount->id));
        foreach ($oldPhotoPaths as $path) {
            Storage::disk(RequestPhoto::disk())->assertMissing($path);
        }

        // サンプル職員以外の案件・職員アカウントは残る。
        $this->assertNotNull(Request::withoutGlobalScopes()->find($byOtherStaff->id));
        $this->assertNotNull(RequestLog::find($logOnOtherCase->id));
        $this->assertNotNull(User::find($sampleStaff->id));
    }

    public function test_二回続けて実行しても件数は変わらない(): void
    {
        $this->artisan('sample:refresh', ['--force' => true])->assertSuccessful();
        $requests = Request::withoutGlobalScopes()->count();
        $photos = RequestPhoto::count();

        $this->artisan('sample:refresh', ['--force' => true])->assertSuccessful();

        $this->assertSame($requests, Request::withoutGlobalScopes()->count());
        $this->assertSame($photos, RequestPhoto::count());
        $this->assertCount($photos, Storage::disk(RequestPhoto::disk())->allFiles('request-photos'));
    }
}
