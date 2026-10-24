<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\RequestPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * 体験用サンプル案件と対応履歴（ローカル・本番共用。デモ環境の初期データ）。
 *
 * 一覧・検索・地図・ダッシュボードが「実際に使われているシステム」らしく見えるよう、
 * 各事務所に20〜30件の案件と、0〜3件の対応履歴を投入する。案件データは事務所ごとに
 * database/seeders/data/ に分けて持つ（件数が多く、Seeder 本体が読みにくくなるため）。
 *
 * 方針（Issue #39, #47, #97）:
 * - 氏名・団体名・地名は実在を指さないダミー（CLAUDE.md 6章）。住所文字は架空地名。
 * - 受付日は「投入日の何日前か」で固定値を持ち、過去1年に分散させる。内容は季節に
 *   合わせている（秋の落ち葉・冬の凍結・梅雨の増水・夏の草刈り・台風後の倒木など）。
 *   投入するたびに結果が変わらないよう、座標の乱数もシードを固定する。
 * - 対応履歴は対応状況と矛盾させない（未対応は履歴なし。対応完了は最後の履歴の日を
 *   対応完了日とする）。
 * - 採番は「その事務所・その年の現在の最大 reception_seq + 1」から連番。既存の
 *   手動登録案件があっても衝突しない（追加投入。既存データは消さない）。
 * - 地図表示向けに全件へ緯度経度を付与する。大阪府内の各事務所エリア
 *   （みどり=北部 / つばき=東部 / こはく=南部）内に配置する（#47）。
 * - 冪等化：この Seeder が投入したサンプル職員が登録者の案件が既にあればスキップ。
 *   投入済みのサンプルを入れ替えるときは `php artisan sample:refresh` を使う。
 * - 一部の案件には現場写真（受付時・対応履歴ごと）を付ける（#103）。写真は
 *   database/seeders/photos/ の縮小済み JPEG を、写真の保存先ディスク（ローカルは
 *   storage、本番は S3）へ保存する。どの案件にどの写真を付けるかは docs/sample-photos.md。
 *
 * 依存：SampleStaffSeeder（登録者・記録者となるサンプル職員）が先に実行されていること。
 */
class SampleRequestSeeder extends Seeder
{
    /**
     * 事務所名 => サンプル案件のデータファイル（database/seeders/data/ 配下）。
     *
     * @var array<string, string>
     */
    public const DATA_FILES = [
        'みどり土木事務所' => 'sample_requests_midori.php',
        'つばき土木事務所' => 'sample_requests_tsubaki.php',
        'こはく土木事務所' => 'sample_requests_kohaku.php',
    ];

    /**
     * 事務所ごとの地図配置エリア（大阪府内）。1つの自治体で使う想定に合わせ、
     * みどり=北部 / つばき=東部 / こはく=南部 の緯度経度レンジ内にピンを落とす。
     * 住所文字は架空のまま（CLAUDE.md 6章）で、座標のみ実在エリア内に配置する。
     *
     * 各値は [最小緯度, 最大緯度, 最小経度, 最大経度]。おおよそ次のエリアを覆う:
     * - 北部：高槻・茨木・吹田あたり
     * - 東部：東大阪・八尾あたり
     * - 南部：堺・岸和田あたり
     *
     * @var array<string, array{float, float, float, float}>
     */
    public const OFFICE_AREAS = [
        'みどり土木事務所' => [34.780, 34.850, 135.490, 135.590], // 大阪府北部
        'つばき土木事務所' => [34.650, 34.700, 135.580, 135.640], // 大阪府東部
        'こはく土木事務所' => [34.430, 34.560, 135.400, 135.480], // 大阪府南部
    ];

    /** 座標の乱数のシード（投入のたびにピンの位置が変わらないよう固定する）。 */
    private const RANDOM_SEED = 97;

    /** 対応履歴の記録時刻（その日の業務の終わりに記録した想定）。 */
    private const LOG_RECORDED_TIME = '17:00';

    /** サンプル写真（縮小済み JPEG）の置き場所。 */
    private const PHOTO_DIR = __DIR__.'/photos';

    /**
     * 保存先ディスクに書き込んだ写真のパス。途中で失敗したとき、DB はロールバック
     * されてもファイルは残るため、これを使って後始末する。
     *
     * @var list<string>
     */
    private array $storedPhotoPaths = [];

    private int $photoCount = 0;

    public function run(): void
    {
        // このSeeder専用のサンプル職員（user_id を厳密に限定）。手動作成された
        // 別の職員（例：staff01）を巻き込まないようにする。
        $sampleStaffUserIds = SampleStaffSeeder::sampleStaffUserIds();

        $sampleStaffIds = User::query()
            ->whereIn('user_id', $sampleStaffUserIds)
            ->pluck('id');

        // 冪等化：サンプル職員が登録した案件が既にあれば、投入済みとみなしスキップ。
        if ($sampleStaffIds->isNotEmpty()
            && Request::withoutGlobalScopes()->whereIn('registered_by', $sampleStaffIds)->exists()) {
            $this->command->info('サンプル案件は既に投入済みのためスキップしました。');

            return;
        }

        // 事務所ID・部署 => その事務所の同部署のサンプル職員（登録者・記録者）。
        $staffByOfficeDept = User::query()
            ->whereIn('user_id', $sampleStaffUserIds)
            ->orderBy('user_id')
            ->get()
            ->groupBy(fn (User $u) => $u->office_id.'|'.$u->department);

        $offices = Office::query()->pluck('id', 'name');
        $today = Carbon::today();
        mt_srand(self::RANDOM_SEED);

        // (1) 全事務所のサンプルに受付日時を割り当てる。
        $items = [];
        foreach (self::DATA_FILES as $officeName => $file) {
            $officeId = $offices[$officeName] ?? null;
            if ($officeId === null) {
                continue; // 事務所が無ければスキップ（OfficeSeeder 未実行など）
            }

            /** @var list<array<string, mixed>> $samples */
            $samples = require __DIR__.'/data/'.$file;
            foreach ($samples as $sample) {
                [$hour, $minute] = array_map('intval', explode(':', $sample['time']));
                $items[] = [
                    'sample' => $sample,
                    'officeName' => $officeName,
                    'officeId' => $officeId,
                    'receptionAt' => $today->copy()->subDays($sample['days_ago'])->setTime($hour, $minute),
                ];
            }
        }

        // (2) 受付日時の昇順に並べる。古い案件から採番するため、各事務所内でも
        //     「受付が古い案件ほど採番（0001…）が若い」という自然な対応になる。
        usort($items, fn ($a, $b) => $a['receptionAt']->getTimestamp() <=> $b['receptionAt']->getTimestamp());

        // (3) 採番して保存する。
        //     RequestLog は $touches で親案件の updated_at を「今」に更新してしまうため、
        //     withoutTouching で止め、案件・履歴の日時は下で明示的に設定する
        //     （止めないと全案件の最終更新日時が投入日になる）。
        $seqCounters = [];
        $logCount = 0;

        try {
            DB::transaction(function () use ($items, $staffByOfficeDept, &$seqCounters, &$logCount) {
                Model::withoutTouching(function () use ($items, $staffByOfficeDept, &$seqCounters, &$logCount) {
                    foreach ($items as $item) {
                        $staff = $staffByOfficeDept->get($item['officeId'].'|'.$item['sample']['department'])?->first();
                        if ($staff === null) {
                            continue; // 該当部署の職員がいなければスキップ
                        }

                        $logCount += $this->createRequestWithLogs($item, $staff, $seqCounters);
                    }
                });
            });
        } catch (Throwable $e) {
            Storage::disk(RequestPhoto::disk())->delete($this->storedPhotoPaths);
            throw $e;
        }

        $this->command->info('サンプル案件を投入しました（'.count($items).'件・対応履歴'.$logCount.'件・写真'.$this->photoCount.'枚）。');
    }

    /**
     * 案件1件と、その対応履歴を保存する。保存した対応履歴の件数を返す。
     *
     * @param  array{sample: array<string, mixed>, officeName: string, officeId: int, receptionAt: Carbon}  $item
     * @param  array<string, int>  $seqCounters  事務所・年ごとの採番カウンタ（呼び出し間で引き継ぐ）
     */
    private function createRequestWithLogs(array $item, User $staff, array &$seqCounters): int
    {
        $sample = $item['sample'];
        $receptionAt = $item['receptionAt'];
        $year = (int) $receptionAt->format('Y');

        // 対応履歴の日時。履歴は業務の終わり（17:00）に記録した想定とするが、
        // 受付当日の夕方以降に受け付けた案件は受付の30分後、未来にはならないよう今で頭打ちにする。
        $logs = [];
        foreach ($sample['logs'] as $logDef) {
            [$daysAfter, $content] = $logDef;
            $loggedOn = $receptionAt->copy()->addDays($daysAfter)->startOfDay();
            if ($loggedOn->greaterThan(Carbon::today())) {
                throw new RuntimeException("サンプル案件の対応日が未来になっています：{$sample['content']}");
            }
            $recordedAt = $loggedOn->copy()->setTimeFromTimeString(self::LOG_RECORDED_TIME);
            if ($recordedAt->lessThanOrEqualTo($receptionAt)) {
                $recordedAt = $receptionAt->copy()->addMinutes(30);
            }
            $logs[] = [
                'loggedOn' => $loggedOn,
                'recordedAt' => $recordedAt->min(Carbon::now()),
                'content' => $content,
                'photos' => $logDef[2] ?? [],
            ];
        }

        if ($sample['status'] === 'completed' && $logs === []) {
            throw new RuntimeException("対応完了のサンプル案件に対応履歴がありません：{$sample['content']}");
        }
        $lastLog = $logs === [] ? null : $logs[array_key_last($logs)];

        // 採番：その事務所・その年の現在の最大 seq を起点に +1 していく
        // （withoutGlobalScopes で論理削除済みの案件も数え、採番の重複を防ぐ）。
        $key = $item['officeId'].'|'.$year;
        $seqCounters[$key] ??= (int) Request::withoutGlobalScopes()
            ->where('office_id', $item['officeId'])
            ->where('reception_year', $year)
            ->max('reception_seq');
        $seq = ++$seqCounters[$key];

        [$lat, $lng] = $this->randomCoordinate($item['officeName']);

        $request = new Request([
            'reception_date' => $receptionAt->format('Y-m-d'),
            'reception_time' => $receptionAt->format('H:i:s'),
            'reception_method' => $sample['reception_method'],
            'reception_method_other' => null,
            'requester_category' => $sample['requester_category'],
            'requester_name' => $sample['requester_name'],
            'department' => $sample['department'],
            'content' => $sample['content'],
            'request_type' => $sample['request_type'],
            'latitude' => $lat,
            'longitude' => $lng,
            'address' => $sample['address'],
            'response_necessity' => $sample['necessity'],
            'urgency' => $sample['urgency'],
            'response_policy' => $sample['policy'],
            'response_status' => $sample['status'],
            // 対応完了日は、完了の記録となる最後の対応履歴の日とする。
            'response_completed_date' => $sample['status'] === 'completed' ? $lastLog['loggedOn']->format('Y-m-d') : null,
        ]);

        // 採番系・登録者・事務所・日時はアプリが設定する信頼値（コントローラの store と同じ扱い）。
        // 最終更新日時は、最後に対応履歴を記録した日時（履歴が無ければ受付日時）とする。
        $request->forceFill([
            'office_id' => $item['officeId'],
            'reception_year' => $year,
            'reception_seq' => $seq,
            'reception_number' => sprintf('%d-%04d', $year, $seq),
            'registered_by' => $staff->id,
            'created_at' => $receptionAt,
            'updated_at' => $lastLog['recordedAt'] ?? $receptionAt,
        ])->save();

        $this->storePhotos($request, null, $sample['reception_photos'] ?? [], $staff, $receptionAt);

        foreach ($logs as $log) {
            // created_at と updated_at を揃え、「編集済み」と表示されないようにする。
            $requestLog = (new RequestLog(['logged_on' => $log['loggedOn']->format('Y-m-d'), 'content' => $log['content']]))
                ->forceFill([
                    'request_id' => $request->id,
                    'recorded_by' => $staff->id,
                    'created_at' => $log['recordedAt'],
                    'updated_at' => $log['recordedAt'],
                ]);
            $requestLog->save();

            $this->storePhotos($request, $requestLog, $log['photos'], $staff, $log['recordedAt']);
        }

        return count($logs);
    }

    /**
     * サンプル写真を保存先ディスクに置き、写真レコードを作る。$log が null なら受付時の写真。
     *
     * @param  list<string>  $photoIds  database/seeders/photos/ のファイル名（拡張子なし）
     */
    private function storePhotos(Request $request, ?RequestLog $log, array $photoIds, User $staff, Carbon $uploadedAt): void
    {
        foreach ($photoIds as $photoId) {
            $source = self::PHOTO_DIR.'/'.$photoId.'.jpg';
            if (! is_file($source)) {
                throw new RuntimeException("サンプル写真がありません：{$source}");
            }

            // ファイル名はアプリからの登録と同じく乱数（hashName）にする。
            $path = Storage::disk(RequestPhoto::disk())->putFile(RequestPhoto::directoryFor($request), new File($source));
            if ($path === false) {
                throw new RuntimeException("サンプル写真の保存に失敗しました：{$source}");
            }
            $this->storedPhotoPaths[] = $path;

            (new RequestPhoto)->forceFill([
                'request_id' => $request->id,
                'request_log_id' => $log?->id,
                'path' => $path,
                // 画面に出る元ファイル名。スマートフォンで撮った写真らしい名前にする（投入のたびに同じ名前）。
                'original_name' => sprintf('IMG_%04d.jpg', crc32($request->reception_number.$photoId) % 9000 + 1000),
                'size' => (int) filesize($source),
                'uploaded_by' => $staff->id,
                'created_at' => $uploadedAt,
                'updated_at' => $uploadedAt,
            ])->save();

            $this->photoCount++;
        }
    }

    /**
     * 指定した事務所のエリア（大阪府内）内でランダムな緯度経度を返す。
     * decimal:6 に合わせて小数6桁に丸める。エリア未定義なら大阪市付近にフォールバック。
     *
     * @return array{float, float} [緯度, 経度]
     */
    private function randomCoordinate(string $officeName): array
    {
        [$minLat, $maxLat, $minLng, $maxLng] = self::OFFICE_AREAS[$officeName]
            ?? [34.686, 34.700, 135.500, 135.520];

        $lat = round($minLat + (mt_rand() / mt_getrandmax()) * ($maxLat - $minLat), 6);
        $lng = round($minLng + (mt_rand() / mt_getrandmax()) * ($maxLng - $minLng), 6);

        return [$lat, $lng];
    }
}
