<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\UserFormRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ユーザー編集のバリデーション（画面設計書 画面#10 / 要件定義書 2.5）。
 *
 * user_id は「システム全体で一意」だが、更新対象自身は除外する。パスワードは
 * この画面では変更せず（再発行は別アクション deactivate/再発行で扱う）ため
 * ルールに含めない。role 別の分岐は UserFormRules に集約。
 */
class UpdateUserRequest extends FormRequest
{
    use UserFormRules;

    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->userRules() + [
            'user_id' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'user_id')->ignore($target->id),
            ],
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
