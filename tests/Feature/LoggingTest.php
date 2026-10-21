<?php

namespace Tests\Feature;

use App\Http\Middleware\TraceRequest;
use App\Models\Office;
use App\Models\Request;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * ログ（ログ・監視設計書）。リクエストIDの付与、アクセスログ、業務イベントの記録と、
 * 「ログに出してはいけないもの」が1行も出ないことを確かめる。
 *
 * ログは専用のチャンネル（Monolog の TestHandler）に差し替えて集める。
 * 機密の検査は、本番と同じ JsonFormatter で整形した全行を対象にする
 * （個別のログ文を1つずつ確認する方式だと、新しいログを足したときに検査の穴が残るため）。
 */
class LoggingTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.default' => 'capture',
            'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'level' => 'debug'],
        ]);
        /** @var Logger $monolog */
        $monolog = Log::channel('capture')->getLogger();
        /** @var TestHandler $handler */
        $handler = $monolog->getHandlers()[0];
        $this->logs = $handler;
    }

    /** @return array<int, LogRecord> */
    private function records(): array
    {
        return $this->logs->getRecords();
    }

    private function findRecord(string $message): LogRecord
    {
        foreach ($this->records() as $record) {
            if ($record->message === $message) {
                return $record;
            }
        }
        $this->fail("ログ「{$message}」が出ていない。出たログ：".implode(' / ', array_map(fn (LogRecord $r) => $r->message, $this->records())));
    }

    /** @return array<int, LogRecord> */
    private function accessRecords(): array
    {
        return array_values(array_filter($this->records(), fn (LogRecord $r) => $r->message === 'access'));
    }

    /** 本番と同じ形式（JSON）に整形した全行。 */
    private function allLinesAsJson(): string
    {
        $formatter = new JsonFormatter(includeStacktraces: true);

        return implode("\n", array_map(fn (LogRecord $r) => $formatter->format($r), $this->records()));
    }

    private function staff(Office $office, string $password = 'Correct#Pass2026'): User
    {
        return User::factory()->create([
            'user_id' => 'logtest01',
            'office_id' => $office->id,
            'department' => 'road',
            'role' => 'staff',
            'must_change_password' => false,
            'password_hash' => Hash::make($password),
        ]);
    }

    // ------------------------------------------------------------------
    // リクエストID
    // ------------------------------------------------------------------

    public function test_リクエストIDが無ければ発番しレスポンスヘッダとログに同じ値が付く(): void
    {
        $response = $this->get('/login');

        $id = $response->headers->get(TraceRequest::HEADER);
        $this->assertMatchesRegularExpression(self::UUID, (string) $id);
        $this->assertSame($id, $this->accessRecords()[0]->extra['request_id']);
    }

    public function test_形式が正しいリクエストIDは受け取った値をそのまま使う(): void
    {
        $this->get('/login', [TraceRequest::HEADER => 'abc-123-XYZ'])
            ->assertHeader(TraceRequest::HEADER, 'abc-123-XYZ');
    }

    public function test_改行や記号を含む_または長すぎるリクエストIDは使わずに発番し直す(): void
    {
        foreach (["abc\nfake-line", 'abc;rm', str_repeat('a', 65), ''] as $given) {
            $id = $this->get('/login', [TraceRequest::HEADER => $given])->headers->get(TraceRequest::HEADER);

            $this->assertMatchesRegularExpression(self::UUID, (string) $id, '受け取った値：'.json_encode($given));
        }
    }

    public function test_1回のリクエストで出たログ行はすべて同じリクエストIDを持つ(): void
    {
        $this->staff(Office::factory()->create());

        $id = $this->post('/login', ['user_id' => 'logtest01', 'password' => 'Correct#Pass2026'])
            ->headers->get(TraceRequest::HEADER);

        $this->assertGreaterThanOrEqual(2, count($this->records()));   // 「ログインしました」とアクセスログ
        foreach ($this->records() as $record) {
            $this->assertSame($id, $record->extra['request_id'] ?? null, $record->message);
        }
    }

    public function test_ログイン中はログ行にユーザーIDが付く(): void
    {
        $user = $this->staff(Office::factory()->create());

        $this->actingAs($user)->get(route('dashboard.index'))->assertOk();

        $this->assertSame($user->id, $this->accessRecords()[0]->extra['user_id'] ?? null);
    }

    // ------------------------------------------------------------------
    // アクセスログ
    // ------------------------------------------------------------------

    public function test_アクセスログは1リクエスト1行でクエリ文字列を含まない(): void
    {
        $user = $this->staff(Office::factory()->create());

        $this->actingAs($user)->get('/requests?keyword=QUERYWORD&address=QUERYADDR')->assertOk();

        $access = $this->accessRecords();
        $this->assertCount(1, $access);
        $this->assertSame([
            'method' => 'GET',
            'path' => '/requests',
            'route' => 'requests.index',
            'status' => 200,
        ], array_diff_key($access[0]->context, ['duration_ms' => true]));
        $this->assertIsInt($access[0]->context['duration_ms']);
        $this->assertStringNotContainsString('QUERY', $this->allLinesAsJson());
    }

    public function test_ヘルスチェックはアクセスログを出さない(): void
    {
        $this->get('/health')->assertOk();
        $this->get('/up')->assertOk();

        $this->assertSame([], $this->accessRecords());
    }

    // ------------------------------------------------------------------
    // 業務イベント
    // ------------------------------------------------------------------

    public function test_ログイン成功とログアウトをINFOで記録する(): void
    {
        $this->staff(Office::factory()->create());

        $this->post('/login', ['user_id' => 'logtest01', 'password' => 'Correct#Pass2026']);
        $this->post('/logout');

        $this->assertSame('INFO', $this->findRecord('ログインしました')->level->getName());
        $this->assertSame('INFO', $this->findRecord('ログアウトしました')->level->getName());
    }

    public function test_ログイン失敗はWARNINGで記録し入力値は出さない(): void
    {
        $this->staff(Office::factory()->create());

        $this->post('/login', ['user_id' => 'logtest01', 'password' => 'Wrong#Secret999'])
            ->assertSessionHasErrors('user_id');

        $record = $this->findRecord('ログインに失敗しました');
        $this->assertSame('WARNING', $record->level->getName());
        $this->assertSame([], $record->context);
        $this->assertStringNotContainsString('Wrong#Secret999', $this->allLinesAsJson());
        $this->assertStringNotContainsString('logtest01', $this->allLinesAsJson());
    }

    public function test_案件の削除は案件IDだけを記録する(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'office_id' => null, 'department' => null, 'must_change_password' => false]);
        $request = Request::factory()->create(['office_id' => $office->id, 'requester_name' => 'ログ確認用要望者']);

        $this->actingAs($admin)->delete(route('requests.destroy', $request))->assertRedirect();

        $this->assertSame(['case_id' => $request->id], $this->findRecord('案件を削除しました')->context);
    }

    public function test_写真の保存と削除を件数とIDで記録する(): void
    {
        Storage::fake(RequestPhoto::disk());
        $office = Office::factory()->create();
        $user = $this->staff($office);
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->actingAs($user)->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHasNoErrors();
        $photo = RequestPhoto::firstOrFail();
        $this->delete(route('requests.photos.destroy', [$request, $photo]))->assertSessionHasNoErrors();

        $this->assertSame(['case_id' => $request->id, 'log_id' => null, 'count' => 2], $this->findRecord('写真を保存しました')->context);
        $this->assertSame(['case_id' => $request->id, 'photo_id' => $photo->id], $this->findRecord('写真を削除しました')->context);
    }

    public function test_写真の保存に失敗したらERRORで記録し例外は投げ直す(): void
    {
        // 設定の無いディスクを指定し、保存先に接続できない状態を作る。
        config(['filesystems.photo_disk' => 'unavailable']);
        $office = Office::factory()->create();
        $user = $this->staff($office);
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->actingAs($user)->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertServerError();

        $record = $this->findRecord('写真の保存に失敗しました');
        $this->assertSame('ERROR', $record->level->getName());
        $this->assertSame($request->id, $record->context['case_id']);
    }

    // ------------------------------------------------------------------
    // 出してはいけないもの（ログ・監視設計書 6章）
    // ------------------------------------------------------------------

    public function test_パスワード_要望者名_要望内容_検索語_ファイル名はどのログ行にも出ない(): void
    {
        Storage::fake(RequestPhoto::disk());
        $office = Office::factory()->create();
        $user = $this->staff($office, 'Old#Secret2026');
        $admin = User::factory()->create(['role' => 'admin', 'office_id' => null, 'department' => null, 'must_change_password' => false]);

        // ログイン失敗・成功・パスワード変更
        $this->post('/login', ['user_id' => 'logtest01', 'password' => 'Wrong#Secret999']);
        $this->post('/login', ['user_id' => 'logtest01', 'password' => 'Old#Secret2026']);
        $this->put(route('password.update'), [
            'current_password' => 'Old#Secret2026',
            'password' => 'New#Secret2027',
            'password_confirmation' => 'New#Secret2027',
        ])->assertSessionHasNoErrors();

        // 案件の登録・検索・写真
        $this->post(route('requests.store'), [
            'reception_date' => '2026-07-26',
            'reception_time' => '09:00',
            'reception_method' => 'phone',
            'requester_category' => 'individual',
            'requester_name' => '機密確認用要望者',
            'department' => 'road',
            'content' => '機密確認用の要望内容',
            'request_type' => 'complaint',
            'address' => '機密確認用の住所',
            'response_necessity' => 'yes',
            'urgency' => 'medium',
            'response_status' => 'not_started',
        ])->assertSessionHasNoErrors();
        $request = Request::where('requester_name', '機密確認用要望者')->firstOrFail();
        $this->get('/requests?keyword=機密確認用要望者')->assertOk();
        $this->post(route('requests.photos.store', $request), [
            'photos' => [UploadedFile::fake()->image('機密確認用ファイル名.jpg')],
        ])->assertSessionHasNoErrors();
        $this->get(route('requests.show', $request))->assertOk();

        // 管理者による初期パスワードの再発行（新しいパスワードは画面にだけ出す）
        $this->actingAs($admin)->put(route('users.reissue-password', $user))->assertRedirect();
        $reissued = (string) preg_replace('/\A.*：/u', '', (string) session('status'));

        $lines = $this->allLinesAsJson();
        $this->assertNotSame('', $lines);
        foreach (['Wrong#Secret999', 'Old#Secret2026', 'New#Secret2027', $reissued, '機密確認用'] as $secret) {
            $this->assertStringNotContainsString($secret, $lines, "ログに「{$secret}」が出ている");
        }
    }

    public function test_DBエラーのログにはSQLに埋め込まれた値を出さない(): void
    {
        $e = new QueryException(
            'mysql',
            'insert into requests (requester_name) values (?)',
            ['機密確認用要望者'],
            new PDOException('SQLSTATE[22001]: String data, right truncated'),
        );
        // 標準の例外メッセージには値が入ってしまうことを前提として確かめておく。
        $this->assertStringContainsString('機密確認用要望者', $e->getMessage());

        report($e);

        $record = $this->findRecord('データベースの処理に失敗しました');
        $this->assertSame('ERROR', $record->level->getName());
        $this->assertSame('insert into requests (requester_name) values (?)', $record->context['sql']);
        $this->assertStringNotContainsString('機密確認用要望者', $this->allLinesAsJson());
    }

    // ------------------------------------------------------------------
    // エラー画面
    // ------------------------------------------------------------------

    public function test_500エラー画面に問い合わせ番号としてリクエストIDを表示する(): void
    {
        config(['app.debug' => false]);
        Route::get('/_logging-test/boom', fn () => throw new RuntimeException('テスト用の想定外エラー'));

        $response = $this->get('/_logging-test/boom');

        $response->assertStatus(500)
            ->assertSee('問い合わせ番号')
            ->assertSee((string) $response->headers->get(TraceRequest::HEADER))
            ->assertDontSee('テスト用の想定外エラー');
        $this->assertSame('ERROR', $this->findRecord('テスト用の想定外エラー')->level->getName());
    }
}
