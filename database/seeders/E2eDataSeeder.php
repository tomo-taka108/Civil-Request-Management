<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * E2E テスト（Playwright）用のデータ（E2E テスト設計書 3章）。
 *
 * テストが「この画面にはピンが2本出るはず」のように結果を言い切れるよう、
 * 乱数を使わず、少ない件数を決め打ちで入れる。
 *
 * - 試験専用 DB（civil_request_e2e）の、空の状態でだけ動く。開発用・本番の DB に
 *   誤って流すと、試験用の職員と案件が混ざってしまうため（負荷試験用と同じ安全装置）。
 * - 職員は「同じ事務所の別部署」「別の事務所」を用意し、権限・事務所の範囲の違いを
 *   画面で確かめられるようにする。
 * - 文面・住所はすべて架空（CLAUDE.md 6章）。
 *
 * 使い方は e2e/README.md（e2e/run-e2e.sh が自動で実行する）。
 */
class E2eDataSeeder extends Seeder
{
    /** この Seeder が動いてよい唯一のデータベース名。 */
    public const DATABASE = 'civil_request_e2e';

    /** 試験用ユーザーの共通パスワード（試験専用 DB にしか存在しない）。 */
    public const PASSWORD = 'e2e-password';

    /**
     * 試験用ユーザー：[user_id, 氏名, 事務所の番号（1始まり。管理者は null）, 部署, 権限]。
     * e2e/fixtures/data.ts と対応させる。
     */
    private const USERS = [
        ['e2e_road', 'E2E 道路担当', 1, 'road', 'staff'],
        ['e2e_river', 'E2E 河川担当', 1, 'river', 'staff'],
        ['e2e_tsubaki', 'E2E つばき道路担当', 2, 'road', 'staff'],
        ['e2e_admin', 'E2E 管理者', null, null, 'admin'],
    ];

    /**
     * 案件：[事務所の番号, 部署, 緊急性, 対応状況, 緯度, 経度, 住所, 内容]。
     * つばき土木事務所の3件のうち1件は位置なし（地図にはピン2本だけが出る）。
     */
    private const REQUESTS = [
        [1, 'road', 'high', 'not_started', 34.812345, 135.531234, 'みどり市中町1丁目付近', '【E2E】交差点付近の舗装に大きな穴があり、通行の妨げになっている。'],
        [1, 'river', 'medium', 'in_progress', 34.823456, 135.552345, 'みどり市川端町2丁目付近', '【E2E】護岸の一部が崩れかけている。'],
        [1, 'sabo', 'low', 'completed', 34.834567, 135.573456, 'みどり市山手町3丁目付近', '【E2E】砂防えん堤の管理用通路に倒木がある。'],
        [2, 'road', 'high', 'not_started', 34.671234, 135.601234, 'つばき市本町1丁目付近', '【E2E】カーブミラーが倒れている。'],
        [2, 'road', 'low', 'in_progress', 34.682345, 135.622345, 'つばき市東町2丁目付近', '【E2E】歩道の段差でつまずきやすい。'],
        [2, 'river', 'medium', 'not_started', null, null, 'つばき市西町3丁目付近', '【E2E】水路に土砂がたまっている（位置未登録）。'],
        [3, 'road', 'medium', 'not_started', 34.501234, 135.441234, 'こはく市南町1丁目付近', '【E2E】道路標識の文字が読めない。'],
    ];

    public function run(): void
    {
        $this->guard();

        $now = Carbon::now()->startOfMinute();
        $officeIds = $this->seedOffices($now);
        $userIds = $this->seedUsers($officeIds, $now);

        DB::transaction(function () use ($officeIds, $userIds, $now) {
            $seq = [];
            foreach (self::REQUESTS as $index => [$office, $department, $urgency, $status, $lat, $lng, $address, $content]) {
                $officeId = $officeIds[$office - 1];
                $seqNo = $seq[$officeId] = ($seq[$officeId] ?? 0) + 1;
                // 登録者は同じ事務所・同じ部署の職員（いなければ管理者）。
                $registeredBy = $userIds[$officeId.'|'.$department] ?? $userIds['admin'];
                $receptionAt = $now->copy()->subDays(10 - $index)->setTime(9, 0);

                $id = DB::table('requests')->insertGetId([
                    'office_id' => $officeId,
                    'reception_number' => sprintf('%d-%04d', $receptionAt->year, $seqNo),
                    'reception_year' => $receptionAt->year,
                    'reception_seq' => $seqNo,
                    'reception_date' => $receptionAt->toDateString(),
                    'reception_time' => $receptionAt->format('H:i:s'),
                    'reception_method' => 'phone',
                    'registered_by' => $registeredBy,
                    'requester_category' => 'anonymous',
                    'requester_name' => null,
                    'department' => $department,
                    'content' => $content,
                    'request_type' => 'request',
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'address' => $address,
                    'response_necessity' => 'yes',
                    'urgency' => $urgency,
                    'response_policy' => null,
                    'response_status' => $status,
                    'response_completed_date' => $status === 'completed' ? $receptionAt->copy()->addDay()->toDateString() : null,
                    'created_at' => $receptionAt,
                    'updated_at' => $receptionAt,
                ]);

                if ($status !== 'not_started') {
                    DB::table('request_logs')->insert([
                        'request_id' => $id,
                        'logged_on' => $receptionAt->copy()->addDay()->toDateString(),
                        'content' => '【E2E】現地を確認した。',
                        'recorded_by' => $registeredBy,
                        'created_at' => $receptionAt->copy()->addDay(),
                        'updated_at' => $receptionAt->copy()->addDay(),
                    ]);
                }
            }
        });

        $this->command->info(sprintf(
            'E2E テスト用データを投入しました（事務所%d・ユーザー%d人・案件%d件）。',
            count($officeIds), count(self::USERS), count(self::REQUESTS)
        ));
    }

    /**
     * 試験専用 DB の、空の状態でだけ動かす。
     */
    private function guard(): void
    {
        $database = DB::connection()->getDatabaseName();
        if ($database !== self::DATABASE) {
            throw new RuntimeException('E2E テスト用データは試験専用 DB（'.self::DATABASE."）にしか投入できません（接続先：{$database}）。");
        }
        if (DB::table('requests')->exists() || DB::table('users')->exists()) {
            throw new RuntimeException('試験専用 DB が空ではありません。e2e/run-e2e.sh で環境を作り直してください。');
        }
    }

    /**
     * 事務所は体験用サンプルと同じ3つ。
     *
     * @return list<int> 事務所 ID（REQUESTS・USERS の事務所の番号 - 1 の順）
     */
    private function seedOffices(Carbon $now): array
    {
        $ids = [];
        foreach (array_keys(SampleRequestSeeder::OFFICE_AREAS) as $name) {
            $ids[] = DB::table('offices')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $officeIds
     * @return array<string, int> "事務所ID|部署" または "admin" => ユーザーの ID
     */
    private function seedUsers(array $officeIds, Carbon $now): array
    {
        $hash = Hash::make(self::PASSWORD);
        $ids = [];
        foreach (self::USERS as [$userId, $name, $office, $department, $role]) {
            $officeId = $office === null ? null : $officeIds[$office - 1];
            $id = DB::table('users')->insertGetId([
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
            ]);
            $ids[$role === 'admin' ? 'admin' : $officeId.'|'.$department] ??= $id;
        }

        return $ids;
    }
}
