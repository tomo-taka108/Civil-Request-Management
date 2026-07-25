<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * users テーブルの現行スキーマ（user_id / password_hash / role / status 等）に対応。
 * 既定は「一般職員（staff）」。管理者は admin() ステートを使う。
 */
class UserFactory extends Factory
{
    /**
     * ファクトリで共有する初期パスワードのハッシュ。
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'user_id' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'department' => fake()->randomElement(['road', 'river', 'sabo']),
            'role' => 'staff',
            'status' => 'active',
        ];
    }

    /**
     * システム管理者（事務所・担当部署を持たない）。
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'office_id' => null,
            'department' => null,
            'role' => 'admin',
        ]);
    }

    /**
     * 初期パスワードのまま（初回ログイン時の強制変更対象）。
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * 無効化されたアカウント。
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
