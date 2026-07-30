<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ユーザー管理（UserController）のテスト。
 * 権限（管理者限定）・一意性・role別の必須/禁止・無効化・パスワード再発行を検証する。
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);

        return $admin;
    }

    private function actingAsStaff(): User
    {
        $user = User::factory()->create(['role' => 'staff', 'must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * 有効な登録フォームの入力値（一般職員）。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStaffPayload(array $overrides = []): array
    {
        $office = Office::factory()->create();

        return array_merge([
            'user_id' => 'yamada_t',
            'name' => '山田 太郎',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'staff',
            'office_id' => $office->id,
            'department' => 'road',
        ], $overrides);
    }

    // --- 権限 ---

    public function test_未ログインはユーザー一覧にアクセスできない(): void
    {
        $this->get(route('users.index'))->assertRedirect('/login');
    }

    public function test_一般職員はユーザー管理にアクセスできない(): void
    {
        $this->actingAsStaff();

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
    }

    public function test_管理者はユーザー一覧を表示できる(): void
    {
        $admin = $this->actingAsAdmin();
        $office = Office::factory()->create();
        $staff = User::factory()->create(['office_id' => $office->id, 'user_id' => 'staff_a']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('ユーザー一覧')
            ->assertSee($staff->user_id)
            ->assertSee($admin->user_id);
    }

    public function test_管理者は所属事務所で絞り込める(): void
    {
        $this->actingAsAdmin();
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $inA = User::factory()->create(['office_id' => $officeA->id, 'user_id' => 'in_a']);
        $inB = User::factory()->create(['office_id' => $officeB->id, 'user_id' => 'in_b']);

        $this->get(route('users.index', ['office_id' => $officeA->id]))
            ->assertOk()
            ->assertSee($inA->user_id)
            ->assertDontSee($inB->user_id);
    }

    // --- 登録 ---

    public function test_管理者は一般職員を登録できる(): void
    {
        $this->actingAsAdmin();

        $this->post(route('users.store'), $this->validStaffPayload())
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $user = User::where('user_id', 'yamada_t')->first();
        $this->assertNotNull($user);
        $this->assertSame('staff', $user->role);
        $this->assertSame('road', $user->department);
        // 初期パスワードは初回ログイン時に変更させる。
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->status === 'active');
        $this->assertTrue(Hash::check('password123', $user->password_hash));
    }

    public function test_管理者を登録すると事務所と部署はnullになる(): void
    {
        $this->actingAsAdmin();
        $office = Office::factory()->create();

        // 管理者なのに office/department を送っても NULL に矯正される。
        $this->post(route('users.store'), $this->validStaffPayload([
            'user_id' => 'admin_new',
            'role' => 'admin',
            'office_id' => $office->id,
            'department' => 'river',
        ]))->assertRedirect(route('users.index'));

        $user = User::where('user_id', 'admin_new')->first();
        $this->assertSame('admin', $user->role);
        $this->assertNull($user->office_id);
        $this->assertNull($user->department);
    }

    public function test_ユーザーIDが重複すると登録できない(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['user_id' => 'dup_id']);

        $this->post(route('users.store'), $this->validStaffPayload(['user_id' => 'dup_id']))
            ->assertSessionHasErrors('user_id');
    }

    public function test_一般職員は所属事務所と担当部署が必須(): void
    {
        $this->actingAsAdmin();

        $this->post(route('users.store'), $this->validStaffPayload([
            'office_id' => '',
            'department' => '',
        ]))->assertSessionHasErrors(['office_id', 'department']);
    }

    public function test_初期パスワードが確認と一致しないと登録できない(): void
    {
        $this->actingAsAdmin();

        $this->post(route('users.store'), $this->validStaffPayload([
            'password' => 'password123',
            'password_confirmation' => 'different999',
        ]))->assertSessionHasErrors('password');
    }

    // --- 更新 ---

    public function test_管理者はユーザーを更新できる(): void
    {
        $this->actingAsAdmin();
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id, 'user_id' => 'before', 'name' => '旧名']);

        $this->put(route('users.update', $user), [
            'user_id' => 'after',
            'name' => '新名',
            'role' => 'staff',
            'office_id' => $office->id,
            'department' => 'sabo',
        ])->assertRedirect(route('users.index'));

        $fresh = $user->fresh();
        $this->assertSame('after', $fresh->user_id);
        $this->assertSame('新名', $fresh->name);
        $this->assertSame('sabo', $fresh->department);
    }

    public function test_更新時に自分のユーザーIDは重複扱いにならない(): void
    {
        $admin = $this->actingAsAdmin();

        // 自分自身の user_id をそのままにして更新できる（自分を一意チェックから除外）。
        $this->put(route('users.update', $admin), [
            'user_id' => $admin->user_id,
            'name' => '更新後の管理者',
            'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $this->assertSame('更新後の管理者', $admin->fresh()->name);
    }

    // --- 無効化 ---

    public function test_管理者は他ユーザーを無効化できる(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['status' => 'active']);

        $this->put(route('users.deactivate', $user))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_自分自身は無効化できない(): void
    {
        $admin = $this->actingAsAdmin();

        $this->put(route('users.deactivate', $admin))
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHas('error');

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_無効化されたユーザーはログインできない(): void
    {
        $office = Office::factory()->create();
        User::factory()->create([
            'office_id' => $office->id,
            'user_id' => 'inactive_user',
            'password_hash' => Hash::make('password123'),
            'status' => 'inactive',
        ]);

        $this->post('/login', [
            'user_id' => 'inactive_user',
            'password' => 'password123',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    // --- パスワード再発行 ---

    public function test_管理者はパスワードを再発行できる(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create([
            'password_hash' => Hash::make('oldpassword'),
            'must_change_password' => false,
        ]);
        $oldHash = $user->password_hash;

        $this->put(route('users.reissue-password', $user))
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHas('status');

        $fresh = $user->fresh();
        // パスワードが変わり、初回変更フラグが立つ。
        $this->assertNotSame($oldHash, $fresh->password_hash);
        $this->assertTrue($fresh->must_change_password);
        $this->assertFalse(Hash::check('oldpassword', $fresh->password_hash));
    }

    public function test_一般職員は無効化や再発行を実行できない(): void
    {
        $this->actingAsStaff();
        $target = User::factory()->create();

        $this->put(route('users.deactivate', $target))->assertForbidden();
        $this->put(route('users.reissue-password', $target))->assertForbidden();
    }

    // --- 再有効化 ---

    public function test_管理者は無効化済みユーザーを再有効化できる(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->inactive()->create();

        $this->put(route('users.activate', $user))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_再有効化したユーザーはログインできる(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->inactive()->create([
            'office_id' => $office->id,
            'user_id' => 'revived',
            'password_hash' => Hash::make('password123'),
            'must_change_password' => false,
        ]);

        // 無効化中はログイン不可 → 再有効化 → ログイン可、を通しで確認する。
        $this->post('/login', ['user_id' => 'revived', 'password' => 'password123'])
            ->assertSessionHasErrors();
        $this->assertGuest();

        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $this->actingAs($admin);
        $this->put(route('users.activate', $user));
        $this->post('/logout');

        $this->post('/login', ['user_id' => 'revived', 'password' => 'password123']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_一般職員は再有効化を実行できない(): void
    {
        $this->actingAsStaff();
        $target = User::factory()->inactive()->create();

        $this->put(route('users.activate', $target))->assertForbidden();
    }

    // --- 物理削除 ---

    public function test_案件を持たないユーザーは物理削除できる(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['user_id' => 'orphan']);

        $this->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_案件を登録済みのユーザーは物理削除できない(): void
    {
        $this->actingAsAdmin();
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        Request::factory()->create(['office_id' => $office->id, 'registered_by' => $user->id]);

        $this->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_案件が論理削除済みでもユーザーは物理削除できない(): void
    {
        $this->actingAsAdmin();
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id, 'department' => 'road']);
        $request = Request::factory()->create(['office_id' => $office->id, 'registered_by' => $user->id]);
        // 案件を論理削除しても、登録者表示のため参照は残る → 削除は拒否。
        $request->delete();

        $this->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_自分自身は物理削除できない(): void
    {
        $admin = $this->actingAsAdmin();

        $this->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_一般職員は物理削除を実行できない(): void
    {
        $this->actingAsStaff();
        $target = User::factory()->create();

        $this->delete(route('users.destroy', $target))->assertForbidden();
    }
}
