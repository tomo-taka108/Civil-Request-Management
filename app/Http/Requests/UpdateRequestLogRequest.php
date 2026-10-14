<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PhotoUploadRules;
use App\Http\Requests\Concerns\RequestLogFormRules;
use App\Models\RequestLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 対応履歴の編集のバリデーション（要件定義書 §2.6）。
 *
 * 内容が誤っていた場合に訂正できるよう編集機能を設ける（ユーザー判断。
 * 削除して再登録する運用では記録の作成日時が実際の記録時点とずれるため）。
 * recorded_by は編集対象に含めない（RequestLogController::update 参照）。
 *
 * 編集時にも写真を追加できる（§2.8）。枚数上限は登録済みの枚数を差し引いて判定する。
 */
class UpdateRequestLogRequest extends FormRequest
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
        return [...$this->requestLogRules(), ...$this->photoRules($this->registeredPhotoCount())];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...$this->requestLogMessages(), ...$this->photoMessages($this->registeredPhotoCount())];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->requestLogAttributes();
    }

    private function registeredPhotoCount(): int
    {
        /** @var RequestLog $log */
        $log = $this->route('log');

        return $log->photos()->count();
    }
}
