<?php

namespace App\Console\Commands;

use App\Models\RequestPhoto;
use App\Models\User;
use Database\Seeders\SampleRequestSeeder;
use Database\Seeders\SampleStaffSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 体験用サンプル案件の入れ替え（docs/operations-start-stop.md「サンプルデータの入れ替え」）。
 *
 * SampleRequestSeeder は「投入済みなら何もしない」ため、サンプルの内容を更新しても
 * 既存の環境には反映されない。このコマンドは、サンプル職員が登録者の案件を
 * 対応履歴・現場写真ごと物理削除し、Seeder で入れ直す。
 *
 * 消えるもの：サンプル職員が登録した案件すべて（デモ用アカウントで後から登録された案件を含む）と、
 *             その対応履歴・現場写真（保存先ディスクのファイルも消す）。
 * 残るもの  ：事務所・職員アカウント（変更済みのパスワードを含む）・サンプル職員以外が登録した案件。
 */
class RefreshSampleData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'sample:refresh {--force : 本番環境でも確認せずに実行する}';

    protected $description = 'サンプル職員が登録した案件（対応履歴・現場写真を含む）を削除し、体験用サンプルを入れ直す';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $sampleStaffIds = User::query()
            ->whereIn('user_id', SampleStaffSeeder::sampleStaffUserIds())
            ->pluck('id');

        if ($sampleStaffIds->isEmpty()) {
            $this->error('サンプル職員がいません。先に SampleStaffSeeder を実行してください。');

            return self::FAILURE;
        }

        // 論理削除済みの案件・履歴も含めて物理削除するため、モデルではなくクエリビルダで消す
        // （SoftDeletes・事務所スコープを通さない）。外部キーの向きに合わせ、写真 → 履歴 → 案件の順。
        $requestIds = DB::table('requests')->whereIn('registered_by', $sampleStaffIds)->pluck('id');

        DB::transaction(function () use ($requestIds) {
            DB::table('request_photos')->whereIn('request_id', $requestIds)->delete();
            DB::table('request_logs')->whereIn('request_id', $requestIds)->delete();
            DB::table('requests')->whereIn('id', $requestIds)->delete();
        });

        // DB を消してからファイルを消す（ファイル削除に失敗しても、画面に壊れた写真は出ない）。
        // 案件ごとのフォルダを丸ごと消し、画面から追加された写真も残さない。
        $disk = Storage::disk(RequestPhoto::disk());
        foreach ($requestIds as $requestId) {
            $disk->deleteDirectory(RequestPhoto::directoryFor((int) $requestId));
        }

        $this->info("サンプル案件を削除しました（{$requestIds->count()}件）。");

        return $this->call('db:seed', ['--class' => SampleRequestSeeder::class, '--force' => true]);
    }
}
