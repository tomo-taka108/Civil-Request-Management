<?php

namespace App\Models\Scopes;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * 事務所スコープ（画面設計書 3.1 / データベース設計書 4章）。
 *
 * 一般職員（role === 'staff'）のクエリには自事務所（office_id）の条件を
 * 自動付与し、他事務所の案件を取得できないようにする。コントローラ側で
 * where('office_id', ...) を書き漏らすリスクをモデル層で一元的に排除する。
 *
 * システム管理者（role === 'admin'）は office_id を持たない全体管理ロールの
 * ため、スコープを適用せず全事務所の案件を取得できる。
 *
 * 未認証時（コンソール実行・Seeder・ログイン前）は Auth::user() が null に
 * なるため、その場合もスコープを適用しない（認証必須ルートは auth ミドル
 * ウェアで別途担保される）。
 */
class OfficeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null || ! $this->isStaff($user)) {
            return;
        }

        $builder->where($model->getTable().'.office_id', $user->office_id);
    }

    /**
     * 一般職員（事務所スコープの対象）かどうか。
     */
    private function isStaff(Authenticatable $user): bool
    {
        return ($user->role ?? null) === 'staff';
    }
}
