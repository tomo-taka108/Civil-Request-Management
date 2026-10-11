<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 対応履歴（RequestLogController）のテスト。要件定義書 §2.6。
 * 権限・$touches による親の更新・編集時の記録者保持・事務所スコープ・
 * バリデーション・Mass assignment 防御を検証する。
 */
class RequestLogTest extends TestCase
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

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        return $admin;
    }

    // ------------------------------------------------------------------
    // 追加
    // ------------------------------------------------------------------

    public function test_同部署の職員は対応履歴を追加できる(): void
    {
        $office = Office::factory()->create();
        $staff = $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $response = $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '応急措置として常温合材による仮補修を実施した。',
        ]);

        $response->assertRedirect(route('requests.show', $request));
        $this->assertDatabaseHas('request_logs', [
            'request_id' => $request->id,
            'content' => '応急措置として常温合材による仮補修を実施した。',
            'recorded_by' => $staff->id,
        ]);
    }

    public function test_対応履歴の追加で親案件のupdated_atが更新される(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $before = $request->updated_at;
        $this->travel(1)->hours();

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '現地確認を実施した。',
        ]);

        $request->refresh();
        $this->assertTrue($request->updated_at->greaterThan($before));
    }

    public function test_管理者は対応履歴を追加できる(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create(['office_id' => $office->id]);
        $admin = $this->actingAsAdmin();

        $response = $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '管理者による記録。',
        ]);

        $response->assertRedirect(route('requests.show', $request));
        $this->assertDatabaseHas('request_logs', [
            'request_id' => $request->id,
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_他部署の職員は対応履歴を追加できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '書き込めないはず。',
        ])->assertForbidden();

        $this->assertDatabaseMissing('request_logs', ['request_id' => $request->id]);
    }

    public function test_他部署の職員はタイムラインを閲覧できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        RequestLog::factory()->create([
            'request_id' => $request->id,
            'content' => '道路課による対応記録。',
        ]);

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('道路課による対応記録。')
            // 他部署には編集・削除・追加フォームが出ない。
            ->assertDontSee('履歴を追加する');
    }

    public function test_他事務所の職員は対応履歴を追加できない(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $this->actingAsStaff($officeA, 'road');
        $request = Request::factory()->create(['office_id' => $officeB->id, 'department' => 'road']);

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '他事務所からは届かないはず。',
        ])->assertNotFound();
    }

    public function test_Mass_assignment防御_recorded_byとrequest_idを混ぜても無視される(): void
    {
        $office = Office::factory()->create();
        $staff = $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $otherRequest = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $otherUser = User::factory()->create();

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '偽装を試みる。',
            'recorded_by' => $otherUser->id,
            'request_id' => $otherRequest->id,
        ]);

        $this->assertDatabaseHas('request_logs', [
            'request_id' => $request->id,
            'recorded_by' => $staff->id,
        ]);
        $this->assertDatabaseMissing('request_logs', ['request_id' => $otherRequest->id]);
    }

    // ------------------------------------------------------------------
    // バリデーション
    // ------------------------------------------------------------------

    public function test_未来日の対応日は登録できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => now()->addDay()->format('Y-m-d'),
            'content' => '未来の対応。',
        ])->assertSessionHasErrors('logged_on');
    }

    public function test_内容が空だと登録できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => '',
        ])->assertSessionHasErrors('content');
    }

    public function test_内容が1000文字を超えると登録できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        $this->post(route('requests.logs.store', $request), [
            'logged_on' => '2026-07-25',
            'content' => str_repeat('あ', 1001),
        ])->assertSessionHasErrors('content');
    }

    // ------------------------------------------------------------------
    // 編集
    // ------------------------------------------------------------------

    public function test_同部署の職員は対応履歴を編集できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id, 'content' => '訂正前の内容。']);

        $response = $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => '2026-07-26',
            'content' => '訂正後の内容。',
        ]);

        $response->assertRedirect(route('requests.show', $request));
        $log->refresh();
        $this->assertSame('訂正後の内容。', $log->content);
        $this->assertSame('2026-07-26', $log->logged_on->format('Y-m-d'));
    }

    public function test_編集しても記録者は変わらない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $originalRecorder = User::factory()->create();
        $log = RequestLog::factory()->create([
            'request_id' => $request->id,
            'recorded_by' => $originalRecorder->id,
        ]);

        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => '2026-07-26',
            'content' => '別の職員が訂正した内容。',
        ]);

        $log->refresh();
        $this->assertSame($originalRecorder->id, $log->recorded_by);
    }

    public function test_編集済みの履歴には編集済みの表示が出る(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id]);

        $this->travel(1)->hours();
        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => '2026-07-26',
            'content' => '訂正した内容。',
        ]);

        // 編集フォーム内の説明文にも「編集済み」という語は出るため、
        // タイムライン表示側のしるし（log-edited）で判定する。
        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('class="log-edited"', false);
    }

    public function test_未編集の履歴には編集済みのしるしが出ない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        RequestLog::factory()->create(['request_id' => $request->id, 'content' => '最初の記録。']);

        // 編集フォーム内の説明文に「編集済み」という語自体は常に出現するため、
        // タイムライン表示側のしるし（log-edited）で判定する。
        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('class="log-edited"', false);
    }

    public function test_対応履歴の編集でも親案件のupdated_atが更新される(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id]);

        $request->refresh();
        $before = $request->updated_at;
        $this->travel(1)->hours();

        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => '2026-07-26',
            'content' => '訂正した内容。',
        ]);

        $request->refresh();
        $this->assertTrue($request->updated_at->greaterThan($before));
    }

    public function test_他部署の職員は対応履歴を編集できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id, 'content' => '元の内容。']);

        $this->put(route('requests.logs.update', [$request, $log]), [
            'logged_on' => '2026-07-26',
            'content' => '書き換えられないはず。',
        ])->assertForbidden();

        $this->assertSame('元の内容。', $log->refresh()->content);
    }

    public function test_別案件のURLから他案件のログを編集すると404になる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $requestA = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $requestB = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $logOfB = RequestLog::factory()->create(['request_id' => $requestB->id]);

        // requestA のURLに requestB のログIDを渡す（scopeBindings により404になるべき）。
        $this->put(route('requests.logs.update', [$requestA, $logOfB]), [
            'logged_on' => '2026-07-26',
            'content' => '取り違えて編集できてしまうと問題。',
        ])->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    public function test_同部署の職員は対応履歴を削除できる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id]);

        $this->delete(route('requests.logs.destroy', [$request, $log]))
            ->assertRedirect(route('requests.show', $request));

        $this->assertSoftDeleted($log);
    }

    public function test_管理者は対応履歴を削除できる(): void
    {
        $office = Office::factory()->create();
        $request = Request::factory()->create(['office_id' => $office->id]);
        $log = RequestLog::factory()->create(['request_id' => $request->id]);
        $this->actingAsAdmin();

        $this->delete(route('requests.logs.destroy', [$request, $log]))
            ->assertRedirect(route('requests.show', $request));

        $this->assertSoftDeleted($log);
    }

    public function test_他部署の職員は対応履歴を削除できない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'river');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create(['request_id' => $request->id]);

        $this->delete(route('requests.logs.destroy', [$request, $log]))->assertForbidden();

        $this->assertDatabaseHas('request_logs', ['id' => $log->id, 'deleted_at' => null]);
    }

    public function test_別案件のURLから他案件のログを削除すると404になる(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $requestA = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $requestB = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $logOfB = RequestLog::factory()->create(['request_id' => $requestB->id]);

        $this->delete(route('requests.logs.destroy', [$requestA, $logOfB]))->assertNotFound();
        $this->assertDatabaseHas('request_logs', ['id' => $logOfB->id, 'deleted_at' => null]);
    }

    public function test_論理削除したログはタイムラインに出ない(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $log = RequestLog::factory()->create([
            'request_id' => $request->id,
            'content' => '削除されるはずの記録。',
        ]);
        $log->delete();

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('削除されるはずの記録。');
    }

    // ------------------------------------------------------------------
    // 表示順
    // ------------------------------------------------------------------

    public function test_対応履歴は対応日の降順で表示される(): void
    {
        $office = Office::factory()->create();
        $this->actingAsStaff($office, 'road');
        $request = Request::factory()->create(['office_id' => $office->id, 'department' => 'road']);

        RequestLog::factory()->create(['request_id' => $request->id, 'logged_on' => '2026-07-19', 'content' => '最初の対応']);
        RequestLog::factory()->create(['request_id' => $request->id, 'logged_on' => '2026-08-05', 'content' => '最新の対応']);
        RequestLog::factory()->create(['request_id' => $request->id, 'logged_on' => '2026-07-25', 'content' => '中間の対応']);

        $response = $this->get(route('requests.show', $request));
        $response->assertOk();

        $content = $response->getContent();
        $posLatest = strpos($content, '最新の対応');
        $posMiddle = strpos($content, '中間の対応');
        $posFirst = strpos($content, '最初の対応');

        $this->assertNotFalse($posLatest);
        $this->assertNotFalse($posMiddle);
        $this->assertNotFalse($posFirst);
        $this->assertTrue($posLatest < $posMiddle);
        $this->assertTrue($posMiddle < $posFirst);
    }
}
