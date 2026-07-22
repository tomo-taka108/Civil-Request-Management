<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * ユーザーアカウント（データベース設計書 2.2 / 要件定義書 3.1）。
 *
 * 認証まわりの本格実装（getAuthPassword() で password_hash を返すオーバーライド、
 * ForcePasswordChange 連携、Policy 等）は次フェーズで実装する。
 * 本フェーズでは Seeder / マイグレーションが動く最小限の属性定義に留める。
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'office_id',
        'user_id',
        'name',
        'password_hash',
        'must_change_password',
        'department',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Laravel の認証は既定で 'password' カラムを参照するため、
     * password_hash を使う本システムでは getAuthPassword() をオーバーライドする。
     * （認証の有効化は次フェーズだが、カラム名の差異を明示するため先に定義しておく）
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
