<?php

namespace App\Models;

use Database\Factories\RequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 対応履歴（案件に対する対応内容・経過の記録）。要件定義書 §1.1・§2.6。
 *
 * request_id / recorded_by は「アプリが自動設定する信頼値」であり、ユーザー入力から
 * 偽装されないよう $fillable に含めない。コントローラ側で forceFill する
 * （Request モデルの採番系と同じ作法）。編集時も recorded_by は変更しない
 * （誰が記録したかの事実を保持するため。RequestLogController::update 参照）。
 *
 * $touches により、このモデルの作成・更新・削除（論理削除含む）で親 Request の
 * updated_at が自動的に更新される。これにより「最終更新日時」表示と、
 * ダッシュボードの放置案件判定（Feature A）が対応履歴の動きを反映する。
 *
 * office_id は持たない。事務所スコープは常に親 Request の OfficeScope 経由で
 * 効く（ルートモデルバインディングで Request を解決するため、他事務所の
 * ログへ直接アクセスすることはできない）。
 *
 * @property Carbon $logged_on
 * @property-read Request $request
 * @property-read User $recordedBy
 */
class RequestLog extends Model
{
    /** @use HasFactory<RequestLogFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * ユーザー入力を受け付ける項目のみ（request_id / recorded_by は含めない）。
     *
     * @var list<string>
     */
    protected $fillable = [
        'logged_on',
        'content',
    ];

    /**
     * このモデルの変更で親 Request の updated_at も更新する。
     *
     * @var list<string>
     */
    protected $touches = ['request'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
        ];
    }

    /**
     * 対応履歴が属する案件。
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * 記録者。
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * 登録後に内容が訂正されたかどうか（編集済みの表示判定に使う）。
     *
     * 記録の訂正を隠さず明示するため、作成時刻と更新時刻がずれていれば
     * 「編集済み」として扱う（screen-design.md 対応履歴タイムラインの仕様）。
     */
    public function wasEdited(): bool
    {
        if ($this->created_at === null || $this->updated_at === null) {
            return false;
        }

        return ! $this->created_at->equalTo($this->updated_at);
    }
}
