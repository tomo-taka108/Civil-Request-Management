<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * 負荷試験（k6）用のデータ（パフォーマンステスト設計書 3章）。
 *
 * 要件定義書 4.2 の想定（年間最大1,000件・保存3年・同時利用30人）に合わせ、
 * 3年分・3,000件の案件と対応履歴、試験用の職員30人を投入する。
 *
 * - 試験専用 DB（civil_request_perf）以外では動かない。開発用・本番の DB に
 *   誤って流すと、試験用の職員と大量の案件が混ざってしまうため。
 * - 乱数のシードを固定し、何度流しても同じデータになるようにする（前回と比べるため）。
 *   日付は「投入日から何日前か」で決めるため、30日以上動きなし等の判定結果も毎回同じになる。
 * - 案件の文面・住所・要望者は体験用サンプル（database/seeders/data/）を使い回す
 *   （すべて架空。CLAUDE.md 6章）。画面の重さを実際の文章量に近づけるため。
 * - Eloquent を通さず、まとめて INSERT する（3,000件を1件ずつ保存すると遅いため）。
 *   ID も明示して振るので、空の DB にだけ流せる。
 * - 現場写真は作らない（保存先のファイルが必要になり、試験の準備が重くなるため）。
 *
 * 使い方は perf/README.md（perf/run-perf.sh が自動で実行する）。
 */
class PerfDataSeeder extends Seeder
{
    /** この Seeder が動いてよい唯一のデータベース名。 */
    public const DATABASE = 'civil_request_perf';

    /** 試験用職員の共通パスワード（試験専用 DB にしか存在しない）。 */
    public const PASSWORD = 'perf-password';

    /** 試験用職員の人数（要件の想定同時利用者数）。 */
    public const STAFF_COUNT = 30;

    /** 事務所ごとの案件数（3事務所 × 1,000件 ＝ 年間1,000件 × 3年）。 */
    private const REQUESTS_PER_OFFICE = 1000;

    /** 受付日を散らす範囲（日）。保存期間3年。 */
    private const SPAN_DAYS = 365 * 3;

    private const RANDOM_SEED = 114;

    private const CHUNK = 500;

    private const DEPARTMENTS = ['road', 'river', 'sabo'];

    public function run(): void
    {
        $this->guard();

        mt_srand(self::RANDOM_SEED);
        $now = Carbon::now();
        $today = Carbon::today();

        $offices = $this->seedOffices($now);
        $staff = $this->seedUsers($offices, $now);
        $samples = $this->loadSamples();

        // (1) 受付日時を決めて古い順に並べる（古い案件ほど採番が若くなるように）。
        $items = [];
        foreach ($offices as $officeName => $officeId) {
            for ($i = 0; $i < self::REQUESTS_PER_OFFICE; $i++) {
                $receptionAt = $today->copy()
                    ->subDays(mt_rand(0, self::SPAN_DAYS - 1))
                    ->setTime(mt_rand(8, 17), mt_rand(0, 59));
                $items[] = [$officeName, $officeId, $receptionAt];
            }
        }
        usort($items, fn ($a, $b) => $a[2]->getTimestamp() <=> $b[2]->getTimestamp());

        // (2) 案件と対応履歴の行を組み立てる。
        $requests = [];
        $logs = [];
        $seq = [];
        foreach ($items as $index => [$officeName, $officeId, $receptionAt]) {
            $id = $index + 1;
            $sample = $samples[mt_rand(0, count($samples) - 1)];
            $department = self::DEPARTMENTS[mt_rand(0, 2)];
            $year = (int) $receptionAt->format('Y');
            $seqNo = $seq[$officeId.'|'.$year] = ($seq[$officeId.'|'.$year] ?? 0) + 1;
            $daysAgo = (int) $receptionAt->copy()->startOfDay()->diffInDays($today);
            $status = $this->pickStatus($daysAgo);
            $members = $staff[$officeId.'|'.$department];
            $staffId = $members[mt_rand(0, count($members) - 1)];

            // 対応履歴：未対応は無し。対応中は1〜3件、対応完了は1〜4件（最後の日が完了日）。
            $logCount = match ($status) {
                'not_started' => 0,
                'in_progress' => mt_rand(1, 3),
                default => mt_rand(1, 4), // completed
            };
            $loggedOn = $receptionAt->copy()->startOfDay();
            $lastRecordedAt = null;
            for ($n = 0; $n < $logCount; $n++) {
                $loggedOn = $loggedOn->copy()->addDays(mt_rand(1, 20))->min($today);
                $lastRecordedAt = $loggedOn->copy()->setTime(17, 0)->max($receptionAt->copy()->addMinutes(30))->min($now);
                $logs[] = [
                    'request_id' => $id,
                    'logged_on' => $loggedOn->toDateString(),
                    'content' => $this->logContent($n, $logCount, $status),
                    'recorded_by' => $staffId,
                    'created_at' => $lastRecordedAt,
                    'updated_at' => $lastRecordedAt,
                    'deleted_at' => null,
                ];
            }

            $anonymous = in_array($sample['requester_category'], ['anonymous', 'staff_patrol'], true);
            [$minLat, $maxLat, $minLng, $maxLng] = SampleRequestSeeder::OFFICE_AREAS[$officeName];

            $requests[] = [
                'id' => $id,
                'office_id' => $officeId,
                'reception_number' => sprintf('%d-%04d', $year, $seqNo),
                'reception_year' => $year,
                'reception_seq' => $seqNo,
                'reception_date' => $receptionAt->toDateString(),
                'reception_time' => $receptionAt->format('H:i:s'),
                'reception_method' => $sample['reception_method'],
                'reception_method_other' => null,
                'registered_by' => $staffId,
                'requester_category' => $sample['requester_category'],
                'requester_name' => $anonymous ? null : $sample['requester_name'],
                'department' => $department,
                'content' => $sample['content'],
                'request_type' => $sample['request_type'],
                'latitude' => round($minLat + ($maxLat - $minLat) * mt_rand() / mt_getrandmax(), 6),
                'longitude' => round($minLng + ($maxLng - $minLng) * mt_rand() / mt_getrandmax(), 6),
                'address' => $sample['address'],
                'response_necessity' => $sample['necessity'],
                'urgency' => ['high', 'medium', 'low'][mt_rand(0, 2)],
                'response_policy' => $status === 'not_started' ? null : $sample['policy'],
                'response_status' => $status,
                'response_completed_date' => $status === 'completed' ? $loggedOn->toDateString() : null,
                'created_at' => $receptionAt,
                'updated_at' => $lastRecordedAt ?? $receptionAt,
                // 1% は論理削除済み（一覧・集計から除かれることも含めて測る）。
                'deleted_at' => mt_rand(1, 100) === 1 ? $now : null,
            ];
        }

        DB::transaction(function () use ($requests, $logs) {
            foreach (array_chunk($requests, self::CHUNK) as $chunk) {
                DB::table('requests')->insert($chunk);
            }
            foreach (array_chunk($logs, self::CHUNK) as $chunk) {
                DB::table('request_logs')->insert($chunk);
            }
        });

        // MySQL が「どの順に調べると速いか」を決める統計情報を、いま入れたデータで作り直す。
        // 一括投入の直後は統計が古いままで、ダッシュボードの「30日以上動きなし」の集計が
        // 索引を使わず約0.6秒かかる（更新後は約0.006秒）。本番のようにデータが少しずつ増える
        // 運用では統計は自動で追いつくため、その「ふだんの状態」から測る（設計書 5.3）。
        DB::statement('ANALYZE TABLE offices, users, requests, request_logs');

        $this->command->info(sprintf(
            '負荷試験用データを投入しました（事務所%d・職員%d人・案件%d件・対応履歴%d件）。',
            count($offices), self::STAFF_COUNT, count($requests), count($logs)
        ));
    }

    /**
     * 試験専用 DB の、空の状態でだけ動かす。
     */
    private function guard(): void
    {
        $database = DB::connection()->getDatabaseName();
        if ($database !== self::DATABASE) {
            throw new RuntimeException('負荷試験用データは試験専用 DB（'.self::DATABASE."）にしか投入できません（接続先：{$database}）。");
        }
        if (DB::table('requests')->exists() || DB::table('users')->exists()) {
            throw new RuntimeException('試験専用 DB が空ではありません。perf/run-perf.sh で環境を作り直してください。');
        }
    }

    /**
     * @return array<string, int> 事務所名 => ID
     */
    private function seedOffices(Carbon $now): array
    {
        $offices = [];
        foreach (array_keys(SampleRequestSeeder::OFFICE_AREAS) as $index => $name) {
            $offices[$name] = $index + 1;
            DB::table('offices')->insert(['id' => $index + 1, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $offices;
    }

    /**
     * 職員30人（各事務所10人。部署は道路・河川・砂防の順に割り当て）と管理者1人。
     * user_id は perf01〜perf30。k6 の仮想ユーザー番号と対応させる。
     *
     * @param  array<string, int>  $offices
     * @return array<string, list<int>> "事務所ID|部署" => その職員の ID
     */
    private function seedUsers(array $offices, Carbon $now): array
    {
        $hash = Hash::make(self::PASSWORD); // 全員で共有（1回だけ計算する）
        $officeIds = array_values($offices);
        $perOffice = intdiv(self::STAFF_COUNT, count($officeIds));
        $rows = [];
        $staff = [];

        for ($n = 1; $n <= self::STAFF_COUNT; $n++) {
            $officeId = $officeIds[intdiv($n - 1, $perOffice)];
            $department = self::DEPARTMENTS[($n - 1) % $perOffice % 3];
            $rows[] = $this->userRow($n, sprintf('perf%02d', $n), sprintf('負荷試験 職員%02d', $n), $officeId, $department, 'staff', $hash, $now);
            $staff[$officeId.'|'.$department][] = $n;
        }
        $rows[] = $this->userRow(self::STAFF_COUNT + 1, 'perfadmin', '負荷試験 管理者', null, null, 'admin', $hash, $now);

        DB::table('users')->insert($rows);

        return $staff;
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(int $id, string $userId, string $name, ?int $officeId, ?string $department, string $role, string $hash, Carbon $now): array
    {
        return [
            'id' => $id,
            'office_id' => $officeId,
            'user_id' => $userId,
            'name' => $name,
            'password_hash' => $hash,
            'must_change_password' => false,
            'department' => $department,
            'role' => $role,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * 体験用サンプルの全件（文面・住所・要望者などを使い回す）。
     *
     * @return list<array<string, mixed>>
     */
    private function loadSamples(): array
    {
        $samples = [];
        foreach (SampleRequestSeeder::DATA_FILES as $file) {
            array_push($samples, ...require __DIR__.'/data/'.$file);
        }

        return $samples;
    }

    /**
     * 受付からの経過日数に応じて対応状況を決める（古い案件ほど完了している）。
     */
    private function pickStatus(int $daysAgo): string
    {
        [$notStarted, $inProgress] = match (true) {
            $daysAgo < 30 => [40, 40],
            $daysAgo < 180 => [10, 30],
            default => [2, 8],
        };
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= $notStarted => 'not_started',
            $roll <= $notStarted + $inProgress => 'in_progress',
            default => 'completed',
        };
    }

    private function logContent(int $index, int $count, string $status): string
    {
        if ($status === 'completed' && $index === $count - 1) {
            return '対応が完了した。現地で仕上がりを確認し、要望者へ結果を連絡した。';
        }

        return [
            '現地を確認した。状況を写真で記録し、対応方法を検討する。',
            '業者に見積もりを依頼した。回答を待って工程を決める。',
            '関係課と協議した。来月の作業に組み込む方向で調整中。',
            '要望者へ途中経過を連絡した。',
        ][$index % 4];
    }
}
