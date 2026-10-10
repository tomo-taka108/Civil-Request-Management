<?php

namespace App\Http\Requests\Concerns;

/**
 * 対応履歴フォーム（追加 StoreRequestLogRequest / 編集 UpdateRequestLogRequest）で
 * 共通のバリデーションルールとメッセージ。
 *
 * request_id / recorded_by は入力を受け付けない
 * （コントローラでアプリ側から強制設定する。RequestLog モデルの $fillable も同様）。
 */
trait RequestLogFormRules
{
    /**
     * @return array<string, mixed>
     */
    protected function requestLogRules(): array
    {
        return [
            // 未来日の対応は記録できない（実際に対応した日のみを記録する運用）。
            'logged_on' => ['required', 'date', 'before_or_equal:today'],
            'content' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function requestLogMessages(): array
    {
        return [
            'logged_on.before_or_equal' => '対応日は本日以前の日付を入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function requestLogAttributes(): array
    {
        return [
            'logged_on' => '対応日',
            'content' => '対応内容',
        ];
    }
}
