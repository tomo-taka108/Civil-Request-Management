<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 体験用サンプルデータ（SampleRequestSeeder）の整合性。
 * サンプルの文面や日付を書き換えたときに、件数や対応状況との矛盾が
 * 入り込まないよう検証する。
 */
class SampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // サンプル写真の保存先を差し替え、開発用の storage に書き込まないようにする。
        Storage::fake(RequestPhoto::disk());
        $this->seed(DatabaseSeeder::class);
    }

    public function test_事務所ごとの件数が指定の範囲に収まる(): void
    {
        $counts = Request::withoutGlobalScopes()
            ->get()
            ->countBy(fn (Request $r) => Office::find($r->office_id)?->name);

        $this->assertGreaterThanOrEqual(25, $counts['みどり土木事務所']);
        $this->assertLessThanOrEqual(30, $counts['みどり土木事務所']);
        foreach (['つばき土木事務所', 'こはく土木事務所'] as $name) {
            $this->assertGreaterThanOrEqual(20, $counts[$name], $name);
            $this->assertLessThanOrEqual(26, $counts[$name], $name);
        }
    }

    public function test_対応履歴は0件から3件の幅で分散し対応状況と矛盾しない(): void
    {
        $requests = Request::withoutGlobalScopes()->withCount('logs')->get();

        $this->assertEqualsCanonicalizing([0, 1, 2, 3], $requests->pluck('logs_count')->unique()->values()->all());

        foreach ($requests as $request) {
            if ($request->response_status === 'not_started') {
                $this->assertSame(0, $request->logs_count, "未対応なのに履歴がある：{$request->content}");
            }
            if ($request->response_status === 'completed') {
                $lastLoggedOn = RequestLog::where('request_id', $request->id)->max('logged_on');
                $this->assertSame($lastLoggedOn, $request->response_completed_date?->format('Y-m-d'), $request->content);
            }
        }
    }

    public function test_日付が未来にならず最終更新日時が投入日にそろわない(): void
    {
        $this->assertSame(0, RequestLog::where('logged_on', '>', Carbon::today())->count());
        $this->assertSame(0, Request::withoutGlobalScopes()->where('reception_date', '>', Carbon::today())->count());

        // RequestLog の $touches で全件が「今日」に更新されていないこと。
        $updatedToday = Request::withoutGlobalScopes()->whereDate('updated_at', Carbon::today())->count();
        $this->assertSame(0, $updatedToday);

        // 投入した履歴が「編集済み」と表示されないこと。
        $this->assertSame(0, RequestLog::all()->filter->wasEdited()->count());
    }

    public function test_受付番号が事務所内で重複しない(): void
    {
        $requests = Request::withoutGlobalScopes()->get();

        $this->assertSame(
            $requests->count(),
            $requests->unique(fn (Request $r) => $r->office_id.'|'.$r->reception_number)->count(),
        );
    }

    public function test_二重に実行しても件数が増えない(): void
    {
        $before = Request::withoutGlobalScopes()->count();
        $photosBefore = RequestPhoto::count();

        $this->seed(SampleRequestSeeder::class);

        $this->assertSame($before, Request::withoutGlobalScopes()->count());
        $this->assertSame($photosBefore, RequestPhoto::count());
    }

    public function test_サンプル写真が保存され案件と矛盾しない(): void
    {
        $photos = RequestPhoto::with('request', 'log')->get();

        $this->assertGreaterThan(0, $photos->count());
        foreach ($photos as $photo) {
            Storage::disk(RequestPhoto::disk())->assertExists($photo->path);
            $this->assertStringStartsWith(RequestPhoto::directoryFor($photo->request_id).'/', $photo->path);

            // 対応履歴の写真は同じ案件の履歴に付く。未対応の案件には受付時の写真しかない。
            if ($photo->log !== null) {
                $this->assertSame($photo->request_id, $photo->log->request_id);
            }
            if ($photo->request->response_status === 'not_started') {
                $this->assertNull($photo->request_log_id, $photo->request->content);
            }
        }

        // 1か所（受付時・各対応履歴）あたりの上限枚数を超えない。
        $maxPerGroup = $photos->countBy(fn (RequestPhoto $p) => $p->request_id.'|'.$p->request_log_id)->max();
        $this->assertLessThanOrEqual(RequestPhoto::MAX_PER_GROUP, $maxPerGroup);
    }

    public function test_みどりとつばきは一覧の先頭7件すべてに写真がある(): void
    {
        foreach (['みどり土木事務所', 'つばき土木事務所'] as $name) {
            $latest = Request::withoutGlobalScopes()
                ->where('office_id', Office::where('name', $name)->value('id'))
                ->withCount('photos')
                ->orderByDesc('reception_date')
                ->orderByDesc('reception_time')
                ->limit(7)
                ->get();

            foreach ($latest as $request) {
                $this->assertGreaterThan(0, $request->photos_count, "{$name}：{$request->content}");
            }
        }
    }
}
