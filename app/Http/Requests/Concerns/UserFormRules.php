<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * ユーザーフォーム（登録 StoreUserRequest / 編集 UpdateUserRequest）で共通の
 * バリデーションルール・メッセージ・role 別フィールドの正規化。
 *
 * 要件定義書 1.3・3.1：
 * - 一般職員（role=staff）は所属事務所・担当部署が必須
 * - システム管理者（role=admin）は事務所に紐づかないため、所属事務所・担当部署は
 *   持たない（送られてきても NULL に矯正し、値があればエラーにする）
 *
 * user_id / password は登録・編集で扱いが異なるため、各 Request 側で定義する。
 */
trait UserFormRules
{
    /**
     * user_id / password を除く共通ルール。
     *
     * @return array<string, mixed>
     */
    protected function userRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            // 一般職員のみ必須。管理者は禁止（prepareForValidation で NULL 化済み）。
            'office_id' => ['nullable', 'required_if:role,staff', 'prohibited_if:role,admin', 'exists:offices,id'],
            'department' => ['nullable', 'required_if:role,staff', 'prohibited_if:role,admin', Rule::in(['road', 'river', 'sabo'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userMessages(): array
    {
        return [
            'office_id.required_if' => '一般職員の場合は、所属事務所を選択してください。',
            'department.required_if' => '一般職員の場合は、担当部署を選択してください。',
            'office_id.prohibited_if' => 'システム管理者は所属事務所を持ちません。',
            'department.prohibited_if' => 'システム管理者は担当部署を持ちません。',
            'user_id.unique' => 'このユーザーIDは既に使われています。別のIDを指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userAttributes(): array
    {
        return [
            'user_id' => 'ユーザーID',
            'name' => '氏名',
            'password' => '初期パスワード',
            'role' => '権限区分',
            'office_id' => '所属事務所',
            'department' => '担当部署',
        ];
    }

    /**
     * システム管理者は事務所・部署を持たないため、role=admin のときは
     * office_id / department を明示的に NULL 化してから検証する。
     * （画面のJSで隠しても送信されうるため、サーバ側でも矯正する）
     */
    protected function normalizeRoleScopedFields(): void
    {
        if ($this->input('role') === 'admin') {
            $this->merge([
                'office_id' => null,
                'department' => null,
            ]);
        }

        // ログインIDは大文字小文字を区別しない運用（AuthController が小文字化して
        // 認証する）。保存時も小文字化し、一意チェックも小文字で行う。
        if (is_string($this->input('user_id'))) {
            $this->merge([
                'user_id' => mb_strtolower(trim($this->input('user_id'))),
            ]);
        }
    }
}
