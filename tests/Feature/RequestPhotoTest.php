<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 現場写真（RequestPhotoController / RequestLogController の写真部分）のテスト。
 * 要件定義書 §2.8。登録・形式と容量・枚数上限・権限・事務所スコープ・削除・
 * 署名付きURLでの配信を検証する。
 */
class RequestPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage::fake() は temporaryUrl を署名なしの URL に差し替えてしまい、
        // 署名付きURLでの配信を検証できない。テスト専用ディレクトリを root にした
        // 本物の local ディスクを使う。
        $root = storage_path('framework/testing/request-photos');
        File::deleteDirectory($root);
        config([
            'filesystems.photo_disk' => 'local',
            'filesystems.disks.local.root' => $root,
        ]);
        Storage::forgetDisk('local');

        $this->office = Office::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/request-photos'));

        parent::tearDown();
    }

    /**
     * 中身から形式を判定させるため、実ファイルから UploadedFile を作る
     * （UploadedFile::fake() は形式をファイル名の拡張子から返すため、偽装の検証に使えない）。
     */
    private function realUpload(string $originalName, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $originalName, null, null, true);
    }

    private function actingAsStaff(string $department = 'road', ?Office $office = null): User
    {
        $user = User::factory()->create([
            'office_id' => ($office ?? $this->office)->id,
            'department' => $department,
            'role' => 'staff',
            'must_change_password' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    private function roadRequest(): Request
    {
        return Request::factory()->create(['office_id' => $this->office->id, 'department' => 'road']);
    }

    private function logOf(Request $request): RequestLog
    {
        return RequestLog::factory()->create(['request_id' => $request->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function photoOf(Request $request, array $attributes = []): RequestPhoto
    {
        $path = RequestPhoto::directoryFor($request).'/'.fake()->uuid().'.jpg';
        Storage::disk('local')->put($path, 'dummy');

        return RequestPhoto::factory()->create([
            'request_id' => $request->id,
            'path' => $path,
            ...$attributes,
        ]);
    }

    // ------------------------------------------------------------------
    // 対応履歴に紐づく写真
    // ------------------------------------------------------------------

    public function test_対応履歴の追加と同時に写真を登録できる(): void
    {
        $staff = $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '仮補修を実施した。',
            'photos' => [
                UploadedFile::fake()->image('仮補修前.jpg'),
                UploadedFile::fake()->image('仮補修後.png'),
            ],
        ])->assertRedirect(route('requests.show', $request))->assertSessionHasNoErrors();

        $log = RequestLog::firstOrFail();
        $photos = RequestPhoto::orderBy('id')->get();
        $this->assertCount(2, $photos);
        $this->assertSame($log->id, $photos[0]->request_log_id);
        $this->assertSame('仮補修前.jpg', $photos[0]->original_name);
        $this->assertSame($staff->id, $photos[0]->uploaded_by);
        // 保存名は利用者のファイル名を使わず乱数にする。
        $this->assertStringNotContainsString('仮補修前', $photos[0]->path);
        Storage::disk('local')->assertExists($photos[0]->path);
        Storage::disk('local')->assertExists($photos[1]->path);
    }

    public function test_写真なしでも対応履歴は追加できる(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '現地確認を実施した。',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('request_logs', 1);
        $this->assertDatabaseCount('request_photos', 0);
    }

    public function test_写真の形式エラーがあると対応履歴も登録されない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '仮補修を実施した。',
            'photos' => [UploadedFile::fake()->create('memo.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $this->assertDatabaseCount('request_logs', 0);
        $this->assertDatabaseCount('request_photos', 0);
    }

    public function test_対応履歴の編集で写真を追加しても編集済みにならない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $log = $this->logOf($request);

        $this->travel(1)->hours();
        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => $log->logged_on->format('Y-m-d'),
            'content' => $log->content,
            'photos' => [UploadedFile::fake()->image('追加.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $log->photos()->count());
        $this->assertFalse($log->fresh()?->wasEdited());
    }

    public function test_対応履歴ごとに写真は5枚まで(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $log = $this->logOf($request);
        foreach (range(1, 4) as $_) {
            $this->photoOf($request, ['request_log_id' => $log->id]);
        }

        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => $log->logged_on->format('Y-m-d'),
            'content' => $log->content,
            'editing_log_id' => $log->id,
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHasErrors('photos');

        $this->assertSame(4, $log->photos()->count());
    }

    public function test_枚数上限は対応履歴ごとに数え案件合計では制限しない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $full = $this->logOf($request);
        foreach (range(1, 5) as $_) {
            $this->photoOf($request, ['request_log_id' => $full->id]);
        }
        $other = $this->logOf($request);

        $this->put(route('requests.logs.update', [$request, $other]), [
            'logged_on' => $other->logged_on->format('Y-m-d'),
            'content' => $other->content,
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $other->photos()->count());
    }

    // ------------------------------------------------------------------
    // 受付時の写真
    // ------------------------------------------------------------------

    public function test_受付時の写真を登録できる(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('提供写真.jpg')],
        ])->assertRedirect(route('requests.show', $request))->assertSessionHasNoErrors();

        $photo = RequestPhoto::firstOrFail();
        $this->assertNull($photo->request_log_id);
        Storage::disk('local')->assertExists($photo->path);
    }

    public function test_受付時の写真は未選択だと登録できない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [])
            ->assertSessionHasErrorsIn('receptionPhotos', 'photos');
    }

    public function test_受付時の写真も5枚まで(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        foreach (range(1, 5) as $_) {
            $this->photoOf($request);
        }

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasErrorsIn('receptionPhotos', 'photos');

        $this->assertDatabaseCount('request_photos', 5);
    }

    // ------------------------------------------------------------------
    // 形式・容量
    // ------------------------------------------------------------------

    public function test_拡張子をpngに偽装したSVGは登録できない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->post(route('requests.photos.store', $request), [
            'photos' => [$this->realUpload('photo.png', $svg)],
        ])->assertSessionHasErrorsIn('receptionPhotos', 'photos.0');

        $this->assertDatabaseCount('request_photos', 0);
    }

    public function test_5MBを超える写真は登録できない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('big.jpg')->size(RequestPhoto::MAX_SIZE_KB + 1)],
        ])->assertSessionHasErrorsIn('receptionPhotos', 'photos.0');

        $this->assertDatabaseCount('request_photos', 0);
    }

    // ------------------------------------------------------------------
    // 権限・スコープ
    // ------------------------------------------------------------------

    public function test_他部署の職員は写真を登録できない(): void
    {
        $this->actingAsStaff('river');
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertForbidden();

        $this->assertDatabaseCount('request_photos', 0);
    }

    public function test_他部署の職員は写真を削除できない(): void
    {
        $this->actingAsStaff('river');
        $request = $this->roadRequest();
        $photo = $this->photoOf($request);

        $this->delete(route('requests.photos.destroy', [$request, $photo]))->assertForbidden();

        $this->assertModelExists($photo);
    }

    public function test_他部署の職員は写真を閲覧できるが操作は表示されない(): void
    {
        $this->actingAsStaff('river');
        $request = $this->roadRequest();
        $this->photoOf($request, ['original_name' => '受付時の路面.jpg']);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('受付時の路面.jpg')
            ->assertDontSee('写真をアップロードする')
            ->assertDontSee(route('requests.photos.store', $request));
    }

    public function test_管理者は写真を登録できる(): void
    {
        $this->actingAs(User::factory()->admin()->create(['must_change_password' => false]));
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('request_photos', 1);
    }

    public function test_他事務所の案件には写真を登録できない(): void
    {
        $this->actingAsStaff('road', Office::factory()->create());
        $request = $this->roadRequest();

        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    public function test_写真を削除するとレコードとファイルの両方が消える(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $photo = $this->photoOf($request);

        $this->delete(route('requests.photos.destroy', [$request, $photo]))
            ->assertRedirect(route('requests.show', $request));

        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_別案件のURLから写真を削除すると404になる(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $other = $this->roadRequest();
        $photo = $this->photoOf($other);

        $this->delete(route('requests.photos.destroy', [$request, $photo]))->assertNotFound();

        $this->assertModelExists($photo);
    }

    // ------------------------------------------------------------------
    // 表示（署名付きURL）
    // ------------------------------------------------------------------

    public function test_詳細画面で対応履歴の写真と受付時の写真が表示される(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $log = $this->logOf($request);
        $this->photoOf($request, ['request_log_id' => $log->id, 'original_name' => '仮補修前.jpg']);
        $this->photoOf($request, ['original_name' => '提供写真.jpg']);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('仮補修前.jpg')
            ->assertSee('提供写真.jpg')
            ->assertSee('signature=', false);
    }

    public function test_写真は署名付きURLでのみ取得でき期限切れや改ざんでは取得できない(): void
    {
        $this->actingAsStaff();
        $request = $this->roadRequest();
        $photo = $this->photoOf($request);
        $url = $photo->temporaryUrl();

        $this->get($url)->assertOk();
        $this->get(strtok($url, '?'))->assertForbidden();

        $this->travel(RequestPhoto::URL_TTL_MINUTES + 1)->minutes();
        $this->get($url)->assertForbidden();
    }
}
