<?php

namespace App\Policies;

use App\Models\Request;
use App\Models\User;

/**
 * 案件の操作権限（画面設計書 3.2）。
 */
class RequestPolicy
{
    /**
     * 新規登録。
     *
     * 登録は事務所所属の一般職員の行為とする（要件定義書 1.3）。
     * 管理者（role=admin、office_id=NULL）は登録できない
     * （requests.office_id は NOT NULL であり、事務所に紐づかない管理者は
     * 登録主体になれない。管理者の主務は是正のための閲覧・編集・削除）。
     *
     * ※画面設計書 3.2 は「create は常に許可」とするが、これは一般職員内で
     *   担当部署を問わない意図であり、管理者の office_id NULL 問題は別論点。
     *   詳細は docs/screen-design.md「今後検討」に記録。
     */
    public function create(User $user): bool
    {
        return $user->role === 'staff' && $user->office_id !== null;
    }

    /**
     * 編集。管理者は全案件、一般職員は担当部署が一致する案件のみ（画面設計書 3.2）。
     * ※編集機能は次フェーズ。ルート側の can:update で使うため先に定義しておく。
     */
    public function update(User $user, Request $request): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->department === $request->department;
    }

    /**
     * 削除（論理削除）。編集と同じ判定。
     */
    public function delete(User $user, Request $request): bool
    {
        return $this->update($user, $request);
    }
}
