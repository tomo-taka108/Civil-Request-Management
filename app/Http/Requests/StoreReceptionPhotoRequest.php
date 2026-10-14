<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PhotoUploadRules;
use App\Models\Request;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受付時の写真（特定の対応履歴に紐づかない写真）の追加。要件定義書 §2.8。
 *
 * 対応履歴の追加フォームも同じ photos 項目を持つため、エラー表示が
 * 混ざらないよう専用のエラーバッグに入れる。
 */
class StoreReceptionPhotoRequest extends FormRequest
{
    use PhotoUploadRules;

    protected $errorBag = 'receptionPhotos';

    public function authorize(): bool
    {
        return $this->user()->can('managePhoto', $this->route('request'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->photoRules($this->registeredPhotoCount());
        // このフォームは写真の登録だけが目的なので、未選択は不可。
        $rules['photos'][0] = 'required';

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->photoMessages($this->registeredPhotoCount()),
            'photos.required' => '登録する写真を選択してください。',
        ];
    }

    private function registeredPhotoCount(): int
    {
        /** @var Request $request */
        $request = $this->route('request');

        return $request->receptionPhotos()->count();
    }
}
