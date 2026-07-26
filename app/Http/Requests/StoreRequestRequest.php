<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequestFormRules;
use App\Models\Request;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 案件新規登録のバリデーション（画面設計書 4章、データベース設計書 2.3）。
 *
 * ルール本体は RequestFormRules トレイトに集約し、編集（UpdateRequestRequest）と
 * 共通化する。ここでは登録固有の権限（create）のみを定義する。
 */
class StoreRequestRequest extends FormRequest
{
    use RequestFormRules;

    /**
     * 登録権限（一般職員のみ。管理者は不可）。RequestPolicy::create で判定。
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Request::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->requestRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->requestMessages();
    }
}
