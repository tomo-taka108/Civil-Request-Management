<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 認証機能（ログイン・ログアウト・初回強制パスワード変更）のテスト。
 * PR#24 で実装した挙動が将来のリファクタで壊れないよう固定する。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン画面が表示される(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('CivilTrack');
    }

    public function test_未ログインでトップにアクセスするとログイン画面へリダイレクトされる(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_正しい資格情報でログインできる(): void
    {
        $user = User::factory()->create([
            'user_id' => 'yamada',
            'password_hash' => Hash::make('password'),
            'must_change_password' => false,
        ]);

        $this->post('/login', [
            'user_id' => 'yamada',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_ログインIDは大文字小文字を区別しない(): void
    {
        User::factory()->create([
            'user_id' => 'yamada',
            'password_hash' => Hash::make('password'),
            'must_change_password' => false,
        ]);

        // 大文字で入力してもログインできる（③ 小文字化）
        $this->post('/login', [
            'user_id' => 'YAMADA',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_誤ったパスワードではログインできない(): void
    {
        User::factory()->create([
            'user_id' => 'yamada',
            'password_hash' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'user_id' => 'yamada',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('user_id');

        $this->assertGuest();
    }

    public function test_無効化されたアカウントはログインできない(): void
    {
        User::factory()->inactive()->create([
            'user_id' => 'taro',
            'password_hash' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'user_id' => 'taro',
            'password' => 'password',
        ])->assertSessionHasErrors('user_id');

        $this->assertGuest();
    }

    public function test_初回強制変更ユーザーは変更画面以外にアクセスできない(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        // ログイン後、トップにアクセスするとパスワード変更画面へ飛ばされる
        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('password.edit'));
    }

    public function test_パスワード変更で強制変更フラグが解除される(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password_hash' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)->put('/password/change', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/');

        // フラグが解除され、以降はトップにアクセスできる
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_現在のパスワードが誤っていると変更できない(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password_hash' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)->put('/password/change', [
            'current_password' => 'wrong-current',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_ログアウトできる(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_バリデーションメッセージが日本語で表示される(): void
    {
        // 属性名（user_id→ユーザーID）とメッセージ（required→〜は必須です。）の
        // 両方が日本語化されていること（lang/ja/validation.php）を確認する。
        $this->post('/login', ['user_id' => '', 'password' => ''])
            ->assertSessionHasErrors(['user_id' => 'ユーザーIDは必須です。']);
    }
}
