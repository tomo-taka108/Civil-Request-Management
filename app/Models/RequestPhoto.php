<?php

namespace App\Models;

use Database\Factories\RequestPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 現場写真。要件定義書 §2.8。
 *
 * すべての列がアプリの設定する値（保存パス・元ファイル名・登録者など）のため、
 * $fillable は空にして forceFill で設定する（Mass assignment 防御）。
 *
 * 実ファイルは非公開のディスク（config('filesystems.photo_disk')）に置き、
 * 表示は期限付きの署名付きURL（temporaryUrl）で行う。URLが漏れても
 * 期限が切れれば閲覧できない。
 *
 * @property string $path
 * @property string $original_name
 * @property int $size
 * @property int|null $request_log_id
 * @property-read Request $request
 * @property-read RequestLog|null $log
 * @property-read User $uploadedBy
 */
class RequestPhoto extends Model
{
    /** @use HasFactory<RequestPhotoFactory> */
    use HasFactory;

    /** 1件の対応履歴（または受付時）に登録できる枚数の上限。 */
    public const MAX_PER_GROUP = 5;

    /** 1ファイルの容量上限（KB。バリデーションの max に渡す単位）。 */
    public const MAX_SIZE_KB = 5120;

    /** 署名付きURLの有効期間（分）。画面を開いたまま閲覧する時間を見込む。 */
    public const URL_TTL_MINUTES = 30;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * 写真の追加・削除で親 Request の「最終更新日時」も更新する（対応履歴と同じ扱い）。
     *
     * @var list<string>
     */
    protected $touches = ['request'];

    public static function disk(): string
    {
        return (string) config('filesystems.photo_disk');
    }

    /**
     * 保存先ディレクトリ。案件ごとに分けて、ディスク上でも追いやすくする。
     */
    public static function directoryFor(Request $request): string
    {
        return 'request-photos/'.$request->id;
    }

    /**
     * 表示用の期限付きURL。
     */
    public function temporaryUrl(): string
    {
        return Storage::disk(self::disk())->temporaryUrl(
            $this->path,
            now()->addMinutes(self::URL_TTL_MINUTES),
        );
    }

    /**
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * @return BelongsTo<RequestLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(RequestLog::class, 'request_log_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
