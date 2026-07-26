<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 案件一覧の検索条件（RequestController::index）。要件定義書 2.3 / 画面設計書 2章。
 *
 * すべて任意項目（未指定なら絞り込まない）。複数選択（対応部署・対応状況・
 * 緊急性）は配列で受け取り、コントローラ側で whereIn する（項目内はOR、
 * 項目間はAND）。enum系は許可値のみ受け付け、不正値は弾く。
 */
class SearchRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reception_date_from' => ['nullable', 'date'],
            'reception_date_to' => ['nullable', 'date', 'after_or_equal:reception_date_from'],

            'department' => ['nullable', 'array'],
            'department.*' => [Rule::in(['road', 'river', 'sabo'])],

            'response_status' => ['nullable', 'array'],
            'response_status.*' => [Rule::in(['not_started', 'in_progress', 'completed'])],

            'urgency' => ['nullable', 'array'],
            'urgency.*' => [Rule::in(['high', 'medium', 'low'])],

            'address' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reception_date_from' => '受付日時（開始）',
            'reception_date_to' => '受付日時（終了）',
            'department' => '対応部署',
            'response_status' => '対応状況',
            'urgency' => '緊急性',
            'address' => '地区・場所',
            'keyword' => 'キーワード',
        ];
    }
}
