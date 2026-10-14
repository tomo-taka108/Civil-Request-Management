<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PhotoUploadRules;
use App\Http\Requests\Concerns\RequestLogFormRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 対応履歴の追加のバリデーション（要件定義書 §2.6）。
 * 対応時に撮影した現場写真（§2.8）も同時に登録できる。
 *
 * 権限はルート側の can:manageLog ミドルウェアで担保済みだが、FormRequest でも
 * 念のため確認する（多層防御。StoreRequestRequest 等と同じ作法）。
 */
class StoreRequestLogRequest extends FormRequest
{
    use PhotoUploadRules;
    use RequestLogFormRules;

    public function authorize(): bool
    {
        return $this->user()->can('manageLog', $this->route('request'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->requestLogRules(), ...$this->photoRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...$this->requestLogMessages(), ...$this->photoMessages()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->requestLogAttributes();
    }
}
