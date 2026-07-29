<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\UserFormRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * ユーザー新規登録のバリデーション（画面設計書 画面#9 / 要件定義書 2.5・3.1）。
 *
 * ルール本体は UserFormRules トレイトに集約し、編集（UpdateUserRequest）と
 * role 別の分岐（管理者は事務所・部署を持たない）を共通化する。登録固有なのは
 * user_id の一意制約（除外なし）と初期パスワード必須の2点。
 */
class StoreUserRequest extends FormRequest
{
    use UserFormRules;

    /**
     * ユーザー管理はシステム管理者のみ（画面設計書 3.3）。
     * ルートの can:admin でも担保されるが、二重に保証する。
     */
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->userRules() + [
            'user_id' => ['required', 'string', 'max:50', 'unique:users,user_id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->userMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->userAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeRoleScopedFields();
    }
}
