<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequestFormRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 案件編集のバリデーション（画面設計書 4章・3.2、データベース設計書 2.3）。
 *
 * ルール本体は RequestFormRules トレイトに集約し、登録（StoreRequestRequest）と
 * 共通化する。権限はルート側の can:update ミドルウェアでも担保されるが、
 * FormRequest でも念のため update 権限を確認する（多層防御）。
 */
class UpdateRequestRequest extends FormRequest
{
    use RequestFormRules;

    /**
     * 編集権限（担当部署一致の一般職員、または管理者）。RequestPolicy::update で判定。
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('request'));
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
