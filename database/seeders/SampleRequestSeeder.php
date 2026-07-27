<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 体験用サンプル案件（ローカル・本番共用。ポートフォリオ公開時の初期データ）。
 *
 * 一覧・検索・CSV出力・（将来の）地図表示が「システムらしく」見えるよう、
 * 各事務所 × 各部署（道路/河川/砂防）に複数件を投入する。対応状況・緊急性・
 * 受付方法・区分・登録者・受付日時をばらけさせる。
 *
 * 方針（Issue #39）:
 * - 氏名・団体名・地名は実在を指さないダミー（CLAUDE.md 6章）。架空地名ベース。
 * - 採番は「その事務所・その年の現在の最大 reception_seq + 1」から連番。既存の
 *   手動登録案件があっても衝突しない（追加投入。既存データは消さない）。
 * - 一部の案件に緯度経度を付与（将来の地図表示 #7 の確認用）。
 * - 冪等化：この Seeder が投入したサンプル職員が登録者の案件が既にあればスキップ。
 *
 * 依存：SampleStaffSeeder（登録者となるサンプル職員）が先に実行されていること。
 */
class SampleRequestSeeder extends Seeder
{
    /**
     * サンプル案件のテンプレート。
     * office: 事務所名 / department: 対応部署 / 以降は案件の主要項目。
     *
     * @var list<array<string, mixed>>
     */
    private const SAMPLES = [
        // --- サンプル第一土木事務所 ---
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'road',
            'requester_category' => 'individual', 'requester_name' => '山田 太郎',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => 'みどり台3丁目付近の市道で、路面に大きなポットホール（穴ぼこ）ができており、通行時に危険です。早めに補修してほしい。',
            'address' => 'さくら県みどり市みどり台3丁目付近',
            'lat' => 35.681200, 'lng' => 139.767100,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'in_progress',
            'policy' => '現地確認済み。常温合材による応急補修を実施予定。',
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'road',
            'requester_category' => 'neighborhood_association', 'requester_name' => 'ひばりヶ丘自治会',
            'reception_method' => 'window', 'request_type' => 'request',
            'content' => 'ひばりヶ丘の通学路にカーブミラーを設置してほしい。見通しが悪く、児童の飛び出しが心配との声が多い。',
            'address' => 'さくら県みどり市ひばりヶ丘2丁目',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'river',
            'requester_category' => 'municipality', 'requester_name' => 'みどり市役所 河川課',
            'reception_method' => 'email', 'request_type' => 'request',
            'content' => 'あおば川のさくら橋下流で土砂の堆積が進んでいる。増水期の前に浚渫を検討してほしい。',
            'address' => 'さくら県みどり市あおば町 さくら橋下流',
            'lat' => 35.690500, 'lng' => 139.700300,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'not_started',
            'policy' => '次年度の河川維持工事で対応を検討。',
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'river',
            'requester_category' => 'anonymous', 'requester_name' => null,
            'reception_method' => 'phone', 'request_type' => 'anomaly',
            'content' => 'あおば川の護岸の一部が崩れかけているように見える。確認してほしい。',
            'address' => 'さくら県みどり市あおば町4丁目',
            'lat' => null, 'lng' => null,
            'necessity' => 'unknown', 'urgency' => 'high', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'sabo',
            'requester_category' => 'staff_patrol', 'requester_name' => null,
            'reception_method' => 'patrol', 'request_type' => 'anomaly',
            'content' => '定期パトロールにて、やまぶき地区の斜面で小規模な落石を確認。落石防止網の点検が必要。',
            'address' => 'さくら県みどり市やまぶき地区',
            'lat' => 35.702000, 'lng' => 139.650000,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'completed',
            'policy' => '落石防止網を点検し、破損箇所を補修済み。',
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'road',
            'requester_category' => 'council_member', 'requester_name' => 'みどり市議会 佐々木議員',
            'reception_method' => 'letter', 'request_type' => 'request',
            'content' => '駅前通りの街路樹の枝が信号を隠しているとの住民からの声。剪定を検討してほしい。',
            'address' => 'さくら県みどり市駅前1丁目',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'completed',
            'policy' => '街路樹の剪定を実施済み。',
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'road',
            'requester_category' => 'individual', 'requester_name' => '井上 恵子',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => '雨天時にみどり大通りの一部で水たまりがひどく、歩行者に水はねがかかる。排水の改善をお願いしたい。',
            'address' => 'さくら県みどり市みどり大通り沿い',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'in_progress',
            'policy' => '排水ますの詰まりを調査中。',
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'river',
            'requester_category' => 'individual', 'requester_name' => '木村 誠',
            'reception_method' => 'window', 'request_type' => 'complaint',
            'content' => 'あおば川沿いの遊歩道の柵が一部壊れている。子どもが川に近づけてしまい危険。',
            'address' => 'さくら県みどり市あおば町 遊歩道',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'sabo',
            'requester_category' => 'municipality', 'requester_name' => 'みどり市役所 防災課',
            'reception_method' => 'email', 'request_type' => 'request',
            'content' => 'やまぶき地区の砂防堰堤について、堆積状況の点検と今後の対応方針を共有してほしい。',
            'address' => 'さくら県みどり市やまぶき地区 砂防堰堤',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第一土木事務所', 'department' => 'road',
            'requester_category' => 'other', 'requester_name' => '運送会社（匿名希望）',
            'reception_method' => 'fax', 'request_type' => 'request',
            'content' => '市道の橋梁の重量制限標識が色あせて見えにくい。表示の更新をお願いしたい。',
            'address' => 'さくら県みどり市かえで町 かえで橋',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'completed',
            'policy' => '重量制限標識を更新済み。',
        ],

        // --- サンプル第二土木事務所 ---
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'road',
            'requester_category' => 'individual', 'requester_name' => '田中 健太',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => 'つばき町の交差点で信号のない横断歩道が消えかかっている。塗り直しをお願いしたい。',
            'address' => 'すみれ県つばき市つばき町2丁目',
            'lat' => 34.702500, 'lng' => 135.495900,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'in_progress',
            'policy' => '区画線の再塗装を発注済み。',
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'road',
            'requester_category' => 'neighborhood_association', 'requester_name' => 'すずらん町内会',
            'reception_method' => 'window', 'request_type' => 'request',
            'content' => 'すずらん通りの街灯が数本切れており、夜間が暗く不安。早期の交換を希望する。',
            'address' => 'すみれ県つばき市すずらん通り',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'in_progress',
            'policy' => '切れている街灯を調査し順次交換中。',
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'river',
            'requester_category' => 'anonymous', 'requester_name' => null,
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => 'こもれび川に不法投棄と思われるゴミが溜まっている。景観と水質が心配。',
            'address' => 'すみれ県つばき市こもれび川 中流域',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'river',
            'requester_category' => 'municipality', 'requester_name' => 'つばき市役所 環境課',
            'reception_method' => 'email', 'request_type' => 'request',
            'content' => 'こもれび川の増水時の水位観測について、看板の設置場所を相談したい。',
            'address' => 'すみれ県つばき市こもれび川 河口付近',
            'lat' => null, 'lng' => null,
            'necessity' => 'unknown', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'sabo',
            'requester_category' => 'staff_patrol', 'requester_name' => null,
            'reception_method' => 'patrol', 'request_type' => 'anomaly',
            'content' => 'パトロール中、いろは山の登山道脇で土砂の流出跡を確認。降雨後の再点検が必要。',
            'address' => 'すみれ県つばき市いろは山 登山道',
            'lat' => 34.710000, 'lng' => 135.500000,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'road',
            'requester_category' => 'individual', 'requester_name' => '高橋 美咲',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => '歩道の点字ブロックが一部剥がれている。視覚障害のある方が利用するので早めに直してほしい。',
            'address' => 'すみれ県つばき市つばき駅前',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'completed',
            'policy' => '点字ブロックを補修済み。',
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'river',
            'requester_category' => 'council_member', 'requester_name' => 'つばき市議会 山口議員',
            'reception_method' => 'letter', 'request_type' => 'request',
            'content' => 'こもれび川の橋のたもとに、増水時の注意を促す看板を設置してほしいとの地域要望。',
            'address' => 'すみれ県つばき市こもれび橋',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'sabo',
            'requester_category' => 'individual', 'requester_name' => '松本 隆',
            'reception_method' => 'window', 'request_type' => 'request',
            'content' => 'いろは山のふもとに住んでいる。大雨のたびに不安なので、砂防設備の状況を教えてほしい。',
            'address' => 'すみれ県つばき市いろは山 ふもと地区',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'in_progress',
            'policy' => '砂防堰堤の点検結果を取りまとめ中。',
        ],
        [
            'office' => 'サンプル第二土木事務所', 'department' => 'road',
            'requester_category' => 'other', 'requester_name' => 'バス事業者',
            'reception_method' => 'fax', 'request_type' => 'complaint',
            'content' => 'バス路線上の道路で、路肩の崩れによりバスの通行に支障が出ている。応急対応をお願いしたい。',
            'address' => 'すみれ県つばき市あじさい町',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'completed',
            'policy' => '路肩を応急復旧し、通行を確保済み。',
        ],

        // --- サンプル第三土木事務所 ---
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'road',
            'requester_category' => 'individual', 'requester_name' => '渡辺 千尋',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => 'こはく町の生活道路で、側溝のふたがガタついて騒音がする。通るたびに気になる。',
            'address' => 'こはく県こはく市こはく町1丁目',
            'lat' => 43.062000, 'lng' => 141.354400,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'road',
            'requester_category' => 'neighborhood_association', 'requester_name' => 'あかね台自治会',
            'reception_method' => 'window', 'request_type' => 'request',
            'content' => 'あかね台入口の坂道が冬季に凍結して危険。融雪剤の配置か対策をお願いしたい。',
            'address' => 'こはく県こはく市あかね台',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'in_progress',
            'policy' => '凍結対策として融雪剤ボックスの設置を検討中。',
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'river',
            'requester_category' => 'anonymous', 'requester_name' => null,
            'reception_method' => 'phone', 'request_type' => 'anomaly',
            'content' => 'しらかば川の水門付近から異音がする。故障していないか確認してほしい。',
            'address' => 'こはく県こはく市しらかば川 水門付近',
            'lat' => null, 'lng' => null,
            'necessity' => 'unknown', 'urgency' => 'high', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'river',
            'requester_category' => 'municipality', 'requester_name' => 'こはく市役所 建設課',
            'reception_method' => 'email', 'request_type' => 'request',
            'content' => 'しらかば川の堤防の草刈りについて、実施時期を調整したい。',
            'address' => 'こはく県こはく市しらかば川 左岸',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'completed',
            'policy' => '堤防の草刈りを実施済み。',
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'sabo',
            'requester_category' => 'staff_patrol', 'requester_name' => null,
            'reception_method' => 'patrol', 'request_type' => 'anomaly',
            'content' => 'パトロールにて、かえで沢の砂防堰堤上流に流木が集積しているのを確認。撤去の検討が必要。',
            'address' => 'こはく県こはく市かえで沢',
            'lat' => 43.070000, 'lng' => 141.340000,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'in_progress',
            'policy' => '流木の撤去について業者と調整中。',
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'road',
            'requester_category' => 'council_member', 'requester_name' => 'こはく市議会 大野議員',
            'reception_method' => 'letter', 'request_type' => 'request',
            'content' => '通学路のグリーンベルト（路側帯のカラー舗装）が薄くなっている区間の再塗装を要望。',
            'address' => 'こはく県こはく市みずほ町',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'river',
            'requester_category' => 'individual', 'requester_name' => '林 直子',
            'reception_method' => 'phone', 'request_type' => 'complaint',
            'content' => 'しらかば川の河川敷の雑草が伸びすぎて、散歩コースが通りにくい。草刈りをお願いしたい。',
            'address' => 'こはく県こはく市しらかば川 河川敷',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'sabo',
            'requester_category' => 'municipality', 'requester_name' => 'こはく市役所 防災課',
            'reception_method' => 'email', 'request_type' => 'request',
            'content' => '土砂災害警戒区域の住民向け説明会に向け、かえで沢の砂防設備の資料を提供してほしい。',
            'address' => 'こはく県こはく市かえで沢 周辺',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'low', 'status' => 'completed',
            'policy' => '砂防設備の資料を市防災課へ提供済み。',
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'road',
            'requester_category' => 'other', 'requester_name' => '近隣工場',
            'reception_method' => 'fax', 'request_type' => 'complaint',
            'content' => '大型車の通行で道路のひび割れが進行している。舗装の打ち替えを検討してほしい。',
            'address' => 'こはく県こはく市工業団地',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'medium', 'status' => 'not_started',
            'policy' => null,
        ],
        [
            'office' => 'サンプル第三土木事務所', 'department' => 'river',
            'requester_category' => 'individual', 'requester_name' => '清水 洋平',
            'reception_method' => 'window', 'request_type' => 'anomaly',
            'content' => 'しらかば川にかかる歩道橋の一部で、床板が浮いているように感じる。点検をお願いしたい。',
            'address' => 'こはく県こはく市しらかば川 歩道橋',
            'lat' => null, 'lng' => null,
            'necessity' => 'yes', 'urgency' => 'high', 'status' => 'in_progress',
            'policy' => '歩道橋の点検を実施し、補修方法を検討中。',
        ],
    ];

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

        // 事務所名・部署 => その事務所の同部署に所属するサンプル職員（登録者候補）。
        $staffByOfficeDept = User::query()
            ->whereIn('user_id', $sampleStaffUserIds)
            ->get()
            ->groupBy(fn (User $u) => $u->office_id.'|'.$u->department);

        // 事務所名 => Office
        $offices = Office::query()->pluck('id', 'name');

        // 事務所・年ごとの採番カウンタ（既存の最大 reception_seq から続ける）。
        $seqCounters = [];

        // 受付日時をばらけさせる基準日（今日）から過去へずらしていく。
        $baseDate = Carbon::today();
        $index = 0;

        foreach (self::SAMPLES as $sample) {
            $officeId = $offices[$sample['office']] ?? null;
            if ($officeId === null) {
                continue; // 事務所が無ければスキップ（OfficeSeeder 未実行など）
            }

            // 受付日時：数日おきに過去へずらし、時刻もばらけさせる。
            $receptionAt = $baseDate->copy()
                ->subDays($index * 3 + random_int(0, 2))
                ->setTime(random_int(8, 17), random_int(0, 59));
            $year = (int) $receptionAt->format('Y');

            // 採番：その事務所・その年の現在の最大 seq を起点に +1 していく。
            $key = $officeId.'|'.$year;
            if (! isset($seqCounters[$key])) {
                $seqCounters[$key] = (int) Request::withoutGlobalScopes()
                    ->where('office_id', $officeId)
                    ->where('reception_year', $year)
                    ->max('reception_seq');
            }
            $seq = ++$seqCounters[$key];

            // 登録者：同事務所・同部署のサンプル職員からランダムに選ぶ。
            $deptStaff = $staffByOfficeDept->get($officeId.'|'.$sample['department']);
            $registeredBy = $deptStaff?->random()?->id;
            if ($registeredBy === null) {
                continue; // 該当部署の職員がいなければスキップ
            }

            $completedDate = $sample['status'] === 'completed'
                ? $receptionAt->copy()->addDays(random_int(3, 20))->format('Y-m-d')
                : null;

            $model = new Request([
                'reception_date' => $receptionAt->format('Y-m-d'),
                'reception_time' => $receptionAt->format('H:i:s'),
                'reception_method' => $sample['reception_method'],
                'reception_method_other' => null,
                'requester_category' => $sample['requester_category'],
                'requester_name' => $sample['requester_name'],
                'department' => $sample['department'],
                'content' => $sample['content'],
                'request_type' => $sample['request_type'],
                'latitude' => $sample['lat'],
                'longitude' => $sample['lng'],
                'address' => $sample['address'],
                'response_necessity' => $sample['necessity'],
                'urgency' => $sample['urgency'],
                'response_policy' => $sample['policy'],
                'response_status' => $sample['status'],
                'response_completed_date' => $completedDate,
            ]);

            // 採番系・登録者・事務所はアプリが設定する信頼値（コントローラの store と同じ扱い）。
            $model->forceFill([
                'office_id' => $officeId,
                'reception_year' => $year,
                'reception_seq' => $seq,
                'reception_number' => sprintf('%d-%04d', $year, $seq),
                'registered_by' => $registeredBy,
            ])->save();

            $index++;
        }

        $this->command->info('サンプル案件を投入しました（'.count(self::SAMPLES).'件）。');
    }
}
