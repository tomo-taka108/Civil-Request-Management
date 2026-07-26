<?php

namespace App\Models;

use App\Models\Scopes\OfficeScope;
use Database\Factories\RequestFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 案件（苦情・要望・異常箇所）。データベース設計書 2.3 / 要件定義書 3章。
 *
 * 採番系（office_id / reception_number / reception_year / reception_seq /
 * registered_by）は「アプリが自動設定する信頼値」であり、ユーザー入力から
 * 偽装されないよう $fillable に含めない。コントローラ側で forceFill する
 * （RequestController::store 参照）。
 *
 * enum カラムは PHP Enum にはキャストせず DB の文字列のまま保持する
 * （既存の User モデルが role/status/department を string 扱いにしているのと一貫）。
 *
 * 事務所スコープ（OfficeScope）をグローバルスコープとして適用し、一般職員の
 * 全クエリに自事務所の条件を自動付与する（画面設計書 3.1）。管理者・未認証時は
 * 適用されない。スコープを外して全事務所を対象にしたい場合は withoutGlobalScope。
 */
#[ScopedBy(OfficeScope::class)]
class Request extends Model
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * ユーザー入力を受け付ける項目のみ（採番系は含めない）。
     *
     * @var list<string>
     */
    protected $fillable = [
        'reception_date',
        'reception_time',
        'reception_method',
        'reception_method_other',
        'requester_category',
        'requester_name',
        'department',
        'content',
        'request_type',
        'latitude',
        'longitude',
        'address',
        'response_necessity',
        'urgency',
        'response_policy',
        'response_status',
        'response_completed_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reception_date' => 'date',
            'response_completed_date' => 'date',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    /**
     * 案件が属する事務所（データ分離用。登録時点で固定）。
     *
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * 登録者（受付職員）。
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
