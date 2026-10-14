<?php

namespace App\Support;

use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
            Storage::disk($disk)->delete($storedPaths);
            throw $e;
        }
    }

    /**
     * 写真を削除する。レコードを先に消してからファイルを消す
     * （ファイル削除に失敗しても、画面に壊れた写真が出ることはない）。
     */
    public static function delete(RequestPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(RequestPhoto::disk())->delete($photo->path);
    }
}
