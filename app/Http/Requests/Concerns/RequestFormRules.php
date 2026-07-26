<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * 案件フォーム（新規登録 StoreRequestRequest / 編集 UpdateRequestRequest）で
 * 共通のバリデーションルールとメッセージ。
 *
 * office_id / reception_* / registered_by は入力を受け付けない
 * （コントローラでアプリ側から強制設定する＝事務所スコープ強制）。
 */
trait RequestFormRules
{
    /**
     * @return array<string, mixed>
     */
    protected function requestRules(): array
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
            // 対応完了日は対応状況と双方向で整合させる（要件定義書 3章）。
            // ・対応状況が「完了」なら完了日は必須
            // ・対応状況が「完了」以外なら完了日は入力不可（矛盾データを防ぐ）
            'response_completed_date' => ['nullable', 'date', 'required_if:response_status,completed', 'prohibited_unless:response_status,completed'],
        ];
    }

    /**
     * 項目固有のエラーメッセージ。
     *
     * required_if / prohibited_unless の標準メッセージは条件値（completed 等の
     * 内部値）がそのまま出て不自然になるため、専用メッセージで上書きする。
     *
     * @return array<string, string>
     */
    protected function requestMessages(): array
    {
        return [
            'reception_method_other.required_if' => '受付方法で「その他」を選択した場合は、具体的な内容を入力してください。',
            'response_completed_date.required_if' => '対応状況が「対応完了」の場合は、対応完了日を入力してください。',
            'response_completed_date.prohibited_unless' => '対応完了日は、対応状況が「対応完了」の場合のみ入力できます。',
        ];
    }
}
