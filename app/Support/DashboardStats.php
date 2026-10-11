<?php

namespace App\Support;

use App\Models\Office;
use App\Models\Request;
use Illuminate\Support\Collection;

/**
 * ダッシュボードの集計ロジック（要件定義書 §2.7 / mockup/dashboard.html）。
 *
 * すべて Request::query() から開始する（SoftDeletes・OfficeScope が自動適用される。
 * 一般職員は自事務所のみ、管理者は全事務所が対象になる）。
 * DB::table('requests') や withoutGlobalScope は使わない（両スコープをバイパスし、
 * 一般職員に他事務所の件数が混ざってしまうため）。
 */
class DashboardStats
{
    /** 「30日以上動きなし」の基準日数。SearchRequestRequest の stale フィルタと共通。 */
    public const STALE_DAYS = 30;

    /** 要対応案件リストの最大表示件数。 */
    private const ATTENTION_LIMIT = 10;

    /** 月別受付件数の対象期間（直近Nヶ月）。 */
    private const TREND_MONTHS = 12;

    public bool $isAdmin;

    public int $totalCount;

    /** @var array<string, int> 対応状況別の件数（not_started/in_progress/completed）。 */
    public array $byStatus;

    /** @var array<string, int> 緊急性別の件数（high/medium/low）。 */
    public array $byUrgency;

    /** @var array<string, array<string, int>> 部署×対応状況のマトリクス。$matrix[department][status] = n */
    public array $matrix;

    /** @var array<int, array<string, int>>|null 事務所別×対応状況のマトリクス（管理者のみ）。 */
    public ?array $officeMatrix = null;

    /** @var Collection<int, Office>|null 事務所一覧（管理者のみ、officeMatrix のラベル表示用）。 */
    public ?Collection $offices = null;

    public int $highNotStartedCount;

    public int $staleCount;

    /** @var \Illuminate\Database\Eloquent\Collection<int, Request> */
    public \Illuminate\Database\Eloquent\Collection $attentionList;

    /** @var list<array{ym:string, n:int}> */
    public array $trend;

    public function __construct(bool $isAdmin)
    {
        $this->isAdmin = $isAdmin;

        $this->loadBreakdown();
        $this->loadAttentionCounts();
        $this->loadAttentionList();
        $this->loadTrend();
    }

    /**
     * 部署×対応状況×緊急性×事務所の件数を1クエリで取得し、PHP側で畳み込む。
     * 個別に何本もCOUNTクエリを投げるより、互いに整合する数字が得られる。
     */
    private function loadBreakdown(): void
    {
        /** @var Collection<int, object{office_id:int,department:string,urgency:string,response_status:string,n:int}> $rows */
        $rows = Request::query()
            ->selectRaw('office_id, department, urgency, response_status, COUNT(*) AS n')
            ->groupBy('office_id', 'department', 'urgency', 'response_status')
            ->get();

        $this->totalCount = (int) $rows->sum('n');

        $this->byStatus = $this->sumBy($rows, 'response_status', array_keys(RequestLabels::STATUSES));
        $this->byUrgency = $this->sumBy($rows, 'urgency', array_keys(RequestLabels::URGENCIES));

        $this->matrix = [];
        foreach (array_keys(RequestLabels::DEPARTMENTS) as $department) {
            $this->matrix[$department] = $this->sumBy(
                $rows->where('department', $department),
                'response_status',
                array_keys(RequestLabels::STATUSES)
            );
        }

        if ($this->isAdmin) {
            $this->offices = Office::orderBy('id')->get();
            $this->officeMatrix = [];
            foreach ($this->offices as $office) {
                $this->officeMatrix[$office->id] = $this->sumBy(
                    $rows->where('office_id', $office->id),
                    'response_status',
                    array_keys(RequestLabels::STATUSES)
                );
            }
        }
    }

    /**
     * $rows を $column の値ごとに n を合計する。$keys の全キーを 0 埋めで含める
     * （該当件数が0件の区分も表示側で欠落させないため）。
     *
     * @param  Collection<int, object{office_id:int,department:string,urgency:string,response_status:string,n:int}>  $rows
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function sumBy(Collection $rows, string $column, array $keys): array
    {
        $result = array_fill_keys($keys, 0);

        foreach ($rows->groupBy($column) as $key => $group) {
            if (array_key_exists($key, $result)) {
                $result[$key] = (int) $group->sum('n');
            }
        }

        return $result;
    }

    /**
     * 要対応サマリーのカウント（未対応×緊急性高、30日以上動きなし）を条件付き集計で取得する。
     */
    private function loadAttentionCounts(): void
    {
        $row = Request::query()->selectRaw("
            SUM(CASE WHEN response_status = 'not_started' AND urgency = 'high' THEN 1 ELSE 0 END) AS high_not_started,
            SUM(CASE WHEN response_status <> 'completed' AND updated_at < ? THEN 1 ELSE 0 END) AS stale
        ", [now()->subDays(self::STALE_DAYS)])->first();

        $this->highNotStartedCount = (int) ($row->high_not_started ?? 0);
        $this->staleCount = (int) ($row->stale ?? 0);
    }

    /**
     * 要対応案件リスト（上位10件）。緊急性「高」かつ未対応、または30日以上
     * 更新のない未完了案件を、緊急性・最終更新日の順に表示する。
     */
    private function loadAttentionList(): void
    {
        $staleBefore = now()->subDays(self::STALE_DAYS);

        $this->attentionList = Request::query()
            ->where('response_status', '!=', 'completed')
            ->where(function ($query) use ($staleBefore) {
                $query->where(function ($q) {
                    $q->where('response_status', 'not_started')->where('urgency', 'high');
                })->orWhere('updated_at', '<', $staleBefore);
            })
            ->with('office')
            ->orderByRaw("urgency = 'high' DESC")
            ->orderBy('updated_at')
            ->limit(self::ATTENTION_LIMIT)
            ->get();
    }

    /**
     * 月別受付件数（直近12ヶ月）。テストDBがMySQLであることを確認済みのため
     * DATE_FORMAT をそのまま使う（SQLite互換は考慮しない）。
     */
    private function loadTrend(): void
    {
        $from = now()->subMonths(self::TREND_MONTHS - 1)->startOfMonth();

        $rows = Request::query()
            ->selectRaw("DATE_FORMAT(reception_date, '%Y-%m') AS ym, COUNT(*) AS n")
            ->where('reception_date', '>=', $from)
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $trend = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $ym = now()->subMonths($i)->format('Y-m');
            $trend[] = ['ym' => $ym, 'n' => (int) ($rows->get($ym)->n ?? 0)];
        }

        $this->trend = $trend;
    }
}
