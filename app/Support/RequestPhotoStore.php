<?php

namespace App\Support;

use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * 現場写真の保存と削除（要件定義書 §2.8）。対応履歴・受付時の両方から使う。
 *
 * ファイル（ディスク）とレコード（DB）は1つのトランザクションにできないため、
 * 片方だけが残らないよう順序と後始末で整合を取る。
 */
class RequestPhotoStore
{
    /**
     * 写真を保存する。ファイル名は利用者の付けた名前を使わず乱数（hashName）にし、
     * 拡張子もファイルの中身から判定したものにする。
     *
     * 途中で失敗した場合は、保存済みのファイルを消してから例外を投げ直す
     * （DB に行が無いファイルを残さない）。
     *
     * @param  array<int, UploadedFile>  $files
     */
    public static function store(Request $request, ?RequestLog $log, array $files, User $user): void
    {
        if ($files === []) {
            return;
        }

        $disk = RequestPhoto::disk();
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $log, $files, $user, $disk, &$storedPaths) {
                foreach ($files as $file) {
                    $path = $file->store(RequestPhoto::directoryFor($request), $disk);
                    if ($path === false) {
                        throw new RuntimeException('写真ファイルの保存に失敗しました。');
                    }
                    $storedPaths[] = $path;

                    (new RequestPhoto)->forceFill([
                        'request_id' => $request->id,
                        'request_log_id' => $log?->id,
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'size' => $file->getSize(),
                        'uploaded_by' => $user->id,
                    ])->save();
                }
            });
        } catch (Throwable $e) {
            // S3 の障害とアプリの不具合を見分けられるよう、写真の保存で失敗したことを残す。
            // 後始末より先に書く（後始末も失敗した場合に記録が残らなくなるため）。
            // 例外そのもの（スタックトレース付き）は、投げ直した先の例外ハンドラが記録する。
            Log::error('写真の保存に失敗しました', [
                'case_id' => $request->id,
                'log_id' => $log?->id,
                'disk' => $disk,
                'exception_class' => $e::class,
            ]);

            if ($storedPaths !== []) {
                Storage::disk($disk)->delete($storedPaths);
            }

            throw $e;
        }

        // ファイル名は出さない（利用者が名前や住所を含む名前を付けることがあるため。ログ・監視設計書 6章）。
        Log::info('写真を保存しました', [
            'case_id' => $request->id,
            'log_id' => $log?->id,
            'count' => count($files),
        ]);
    }

    /**
     * 写真を削除する。レコードを先に消してからファイルを消す
     * （ファイル削除に失敗しても、画面に壊れた写真が出ることはない）。
     */
    public static function delete(RequestPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(RequestPhoto::disk())->delete($photo->path);

        Log::info('写真を削除しました', [
            'case_id' => $photo->request_id,
            'photo_id' => $photo->id,
        ]);
    }
}
