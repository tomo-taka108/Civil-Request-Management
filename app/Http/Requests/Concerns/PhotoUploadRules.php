<?php

namespace App\Http\Requests\Concerns;

use App\Models\RequestPhoto;

/**
 * 現場写真のアップロード（要件定義書 §2.8）で共通のバリデーション。
 *
 * mimes は拡張子ではなくファイルの中身から判定した形式で検査するため、
 * SVG 等を .png にリネームしても通らない（SVG はスクリプトを埋め込めるため不可）。
 */
trait PhotoUploadRules
{
    /**
     * @param  int  $alreadyRegistered  同じグループ（対応履歴 or 受付時）に登録済みの枚数
     * @return array<string, mixed>
     */
    protected function photoRules(int $alreadyRegistered = 0): array
    {
        $remaining = max(0, RequestPhoto::MAX_PER_GROUP - $alreadyRegistered);

        return [
            'photos' => ['nullable', 'array', 'max:'.$remaining],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:'.RequestPhoto::MAX_SIZE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function photoMessages(int $alreadyRegistered = 0): array
    {
        $max = RequestPhoto::MAX_PER_GROUP;
        $remaining = max(0, $max - $alreadyRegistered);
        $countMessage = $alreadyRegistered === 0
            ? "写真は最大{$max}枚まで登録できます。"
            : "写真は最大{$max}枚までです（登録済み{$alreadyRegistered}枚・あと{$remaining}枚追加できます）。";

        return [
            'photos.max' => $countMessage,
            'photos.*.mimes' => '写真は JPEG・PNG 形式のファイルを選択してください。',
            'photos.*.max' => '写真は1ファイル5MB以内にしてください。',
            'photos.*.file' => '写真のアップロードに失敗しました。もう一度選択してください。',
        ];
    }
}
