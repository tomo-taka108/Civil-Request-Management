<?php

namespace App\Support;

/**
 * 案件（Request）の enum カラム値に対応する日本語ラベル辞書。
 *
 * データベース設計書 2.3 の enum 定義と一致させること。CSV出力・画面表示など
 * 複数箇所でラベル変換が必要になるため、ここに一元化する（View 側の重複定義は
 * 将来この辞書へ寄せる想定）。
 */
class RequestLabels
{
    /** 受付方法。 @var array<string, string> */
    public const RECEPTION_METHODS = [
        'window' => '窓口',
        'phone' => '電話',
        'email' => 'メール',
        'letter' => '要望書',
        'fax' => 'FAX',
        'patrol' => '職員パトロール',
        'other' => 'その他',
    ];

    /** 区分（要望者の属性）。 @var array<string, string> */
    public const REQUESTER_CATEGORIES = [
        'individual' => '個人',
        'neighborhood_association' => '自治会',
        'municipality' => '市町村',
        'council_member' => '議員',
        'anonymous' => '匿名',
        'staff_patrol' => '職員パトロール',
        'other' => 'その他',
    ];

    /** 対応部署。 @var array<string, string> */
    public const DEPARTMENTS = [
        'road' => '道路',
        'river' => '河川',
        'sabo' => '砂防',
    ];

    /** 種別。 @var array<string, string> */
    public const REQUEST_TYPES = [
        'complaint' => '苦情',
        'request' => '要望',
        'anomaly' => '異常発見',
    ];

    /** 対応の必要性。 @var array<string, string> */
    public const NECESSITIES = [
        'yes' => 'あり',
        'no' => 'なし',
        'unknown' => '不明',
    ];

    /** 緊急性。 @var array<string, string> */
    public const URGENCIES = [
        'high' => '高',
        'medium' => '中',
        'low' => '低',
    ];

    /** 対応状況。 @var array<string, string> */
    public const STATUSES = [
        'not_started' => '未対応',
        'in_progress' => '対応中',
        'completed' => '対応完了',
    ];

    /**
     * 辞書からラベルを引く。未知の値はそのまま返す（データ不整合時も欠損させない）。
     *
     * @param  array<string, string>  $dictionary
     */
    public static function label(array $dictionary, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $dictionary[$value] ?? $value;
    }
}
