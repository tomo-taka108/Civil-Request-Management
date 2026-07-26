<?php

namespace App\Http\Requests;

use App\Models\Request;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 案件新規登録のバリデーション（画面設計書 4章、データベース設計書 2.3）。
 *
 * office_id / reception_* / registered_by は入力を受け付けない
 * （コントローラでログインユーザーから自動設定する＝事務所スコープ強制）。
 */
class StoreRequestRequest extends FormRequest
{
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
        return [
            'reception_date' => ['required', 'date'],
            'reception_time' => ['required', 'date_format:H:i'],
            'reception_method' => ['required', Rule::in(['window', 'phone', 'email', 'letter', 'fax', 'patrol', 'other'])],
            'reception_method_other' => ['nullable', 'string', 'max:255', 'required_if:reception_method,other'],
            'requester_category' => ['required', Rule::in(['individual', 'neighborhood_association', 'municipality', 'council_member', 'anonymous', 'staff_patrol', 'other'])],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'department' => ['required', Rule::in(['road', 'river', 'sabo'])],
            'content' => ['required', 'string'],
            'request_type' => ['required', Rule::in(['complaint', 'request', 'anomaly'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'response_necessity' => ['required', Rule::in(['yes', 'no', 'unknown'])],
            'urgency' => ['required', Rule::in(['high', 'medium', 'low'])],
            'response_policy' => ['nullable', 'string'],
            'response_status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
            'response_completed_date' => ['nullable', 'date'],
        ];
    }

    /**
     * 項目固有のエラーメッセージ。
     *
     * required_if の標準メッセージは条件値（other 等の内部値）がそのまま出て
     * 不自然になるため、対象項目は専用メッセージで上書きする。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reception_method_other.required_if' => '受付方法で「その他」を選択した場合は、具体的な内容を入力してください。',
        ];
    }
}
