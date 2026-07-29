<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequestRequest;
use App\Http\Requests\StoreRequestRequest;
use App\Http\Requests\UpdateRequestRequest;
use App\Models\Office;
use App\Models\Request;
use App\Models\User;
use App\Support\RequestLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 案件（苦情・要望・異常箇所）。画面設計書 2章・4章・5章。
 *
 * ※ クラス名の衝突（App\Models\Request と Illuminate\Http\Request）を避けるため、
 *    アクションの型ヒントには FormRequest（StoreRequestRequest）を使い、
 *    Illuminate\Http\Request を import しない。モデルは Request のまま扱う。
 */
class RequestController extends Controller
{
    /** 一覧の1ページあたり表示件数（mockup/list.html の「1〜20件」に合わせる）。 */
    private const PER_PAGE = 20;

    /**
     * 案件一覧・検索（画面設計書 画面#3・要件定義書 2.3）。
     *
     * 事務所スコープ（一般職員は自事務所のみ・管理者は全事務所）は Request モデルの
     * OfficeScope が自動適用する。ここでは検索条件（任意）による絞り込みのみ行う。
     * 検索条件はページ送りでも維持されるよう withQueryString で引き継ぐ。
     */
    public function index(SearchRequestRequest $request): View
    {
        $filters = $request->validated();

        // 管理者は全事務所の案件が混在するため、一覧を事務所ごとにグループ表示し、
        // 事務所での絞り込みも可能にする。一般職員は自事務所のみ（グループ・
        // 事務所フィルタとも不要）。
        $isAdmin = ($request->user()->role ?? null) === 'admin';

        $requests = $this->searchQuery($filters, $isAdmin)
            ->with('office')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'groupByOffice' => $isAdmin,
            // 管理者のみ事務所での絞り込みを提供する。一般職員には空配列を渡す。
            'offices' => $isAdmin ? Office::orderBy('id')->get() : collect(),
        ]);
    }

    /**
     * 一覧・CSV出力で共通の検索クエリ（絞り込み＋並び順）を構築する。
     *
     * 事務所スコープは OfficeScope が自動適用する。検索条件・並び順を一覧と
     * CSVで完全に一致させるため、両者はこのメソッドを起点にする。
     *
     * $groupByOffice が true のときは、事務所ごとにまとめて（事務所内は受付日時の
     * 降順）並べる。管理者の一覧で事務所ごとのグループ表示がページをまたいでも
     * 崩れないようにするための並び順。
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Request>
     */
    private function searchQuery(array $filters, bool $groupByOffice = false): Builder
    {
        $query = Request::query()
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters));

        if ($groupByOffice) {
            $query->orderBy('office_id');
        }

        return $query
            ->orderByDesc('reception_date')
            ->orderByDesc('reception_time');
    }

    /**
     * 検索条件をクエリに適用する（項目間はAND、複数選択項目内はOR）。
     *
     * @param  Builder<Request>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        // 受付日（期間指定）
        if (! empty($filters['reception_date_from'])) {
            $query->where('reception_date', '>=', $filters['reception_date_from']);
        }
        if (! empty($filters['reception_date_to'])) {
            $query->where('reception_date', '<=', $filters['reception_date_to']);
        }

        // 事務所（複数選択＝OR。管理者のみ画面に表示。一般職員は事務所スコープで
        // 自事務所に限定されるため、指定されても実質的な影響はない）。
        if (! empty($filters['office'])) {
            $query->whereIn($query->getModel()->getTable().'.office_id', $filters['office']);
        }

        // 対応部署・対応状況・緊急性（複数選択＝OR。whereIn）
        if (! empty($filters['department'])) {
            $query->whereIn('department', $filters['department']);
        }
        if (! empty($filters['response_status'])) {
            $query->whereIn('response_status', $filters['response_status']);
        }
        if (! empty($filters['urgency'])) {
            $query->whereIn('urgency', $filters['urgency']);
        }

        // 地区・場所（住所の部分一致）。LIKE のワイルドカードはエスケープする。
        if (! empty($filters['address'])) {
            $query->where('address', 'like', '%'.$this->escapeLike($filters['address']).'%');
        }

        // キーワード（要望内容の部分一致）。FULLTEXT精度検証は今後の課題のため
        // まずは LIKE で確実に部分一致させる（database-design.md 5章）。
        if (! empty($filters['keyword'])) {
            $query->where('content', 'like', '%'.$this->escapeLike($filters['keyword']).'%');
        }
    }

    /**
     * LIKE 検索用に特殊文字（\ % _）をエスケープする。
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * 案件詳細（画面設計書 画面#4）。
     *
     * ルートモデルバインディングで解決する。Request モデルには OfficeScope が
     * 適用されるため、一般職員が他事務所の案件IDを直接指定した場合はバインディング
     * 段階で見つからず 404 になる（screen-design.md 3.1）。管理者はスコープ対象外。
     */
    public function show(Request $request): View
    {
        $request->load(['office', 'registeredBy']);

        return view('requests.show', ['request' => $request]);
    }

    /**
     * 案件検索結果のCSV出力（画面設計書 2章・要件定義書 2.3）。
     *
     * 一覧（index）と同一の検索条件・並び順・事務所スコープを適用する。
     * Excel での文字化けを避けるため BOM 付き UTF-8 で出力し、件数が多くても
     * メモリを圧迫しないよう streamDownload で1件ずつ書き出す（chunk）。
     * enum 値は日本語ラベルに変換する（RequestLabels）。
     */
    public function exportCsv(SearchRequestRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $query = $this->searchQuery($filters)->with(['office', 'registeredBy']);

        $filename = 'requests_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM（UTF-8）。Excel で開いた際の文字化け対策。
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $this->csvHeader());

            $query->chunk(500, function ($requests) use ($handle) {
                foreach ($requests as $request) {
                    fputcsv($handle, $this->csvRow($request));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CSVのヘッダ行（詳細画面の項目に事務所名を加えたもの）。
     *
     * @return list<string>
     */
    private function csvHeader(): array
    {
        return [
            '受付番号', '事務所', '受付日', '受付時刻', '受付方法', '受付方法（その他）',
            '区分', '要望者', '対応部署', '種別', '要望の内容', '要望箇所（住所）',
            '緯度', '経度', '対応の必要性', '緊急性', '対応方針', '対応状況',
            '対応完了日', '登録者', '最終更新日時',
        ];
    }

    /**
     * 1案件をCSVの1行（ヘッダ順）に変換する。enum は日本語ラベルに変換する。
     *
     * @return list<string>
     */
    private function csvRow(Request $request): array
    {
        return [
            (string) $request->reception_number,
            (string) $request->office->name,
            $request->reception_date->format('Y-m-d'),
            Str::substr((string) $request->reception_time, 0, 5),
            RequestLabels::label(RequestLabels::RECEPTION_METHODS, $request->reception_method),
            (string) ($request->reception_method_other ?? ''),
            RequestLabels::label(RequestLabels::REQUESTER_CATEGORIES, $request->requester_category),
            (string) ($request->requester_name ?? ''),
            RequestLabels::label(RequestLabels::DEPARTMENTS, $request->department),
            RequestLabels::label(RequestLabels::REQUEST_TYPES, $request->request_type),
            (string) $request->content,
            (string) ($request->address ?? ''),
            $request->latitude !== null ? (string) $request->latitude : '',
            $request->longitude !== null ? (string) $request->longitude : '',
            RequestLabels::label(RequestLabels::NECESSITIES, $request->response_necessity),
            RequestLabels::label(RequestLabels::URGENCIES, $request->urgency),
            (string) ($request->response_policy ?? ''),
            RequestLabels::label(RequestLabels::STATUSES, $request->response_status),
            $request->response_completed_date?->format('Y-m-d') ?? '',
            (string) $request->registeredBy->name,
            $request->updated_at?->format('Y-m-d H:i') ?? '',
        ];
    }

    public function create(): View
    {
        // 登録は一般職員のみ（管理者は不可）。RequestPolicy::create で判定し、不許可なら403。
        Gate::authorize('create', Request::class);

        return view('requests.create');
    }

    /**
     * 新規登録。受付番号の採番を含む（データベース設計書3章・画面設計書5章）。
     */
    public function store(StoreRequestRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $officeId = $user->office_id;
        $year = (int) now()->format('Y');

        $created = DB::transaction(function () use ($request, $user, $officeId, $year) {
            // (office_id, year) の最大連番を行ロック付きで取得し、+1して採番する。
            // 同時登録の最終防御は UNIQUE(office_id, reception_year, reception_seq)。
            $maxSeq = Request::query()
                ->where('office_id', $officeId)
                ->where('reception_year', $year)
                ->lockForUpdate()
                ->max('reception_seq');

            $seq = ($maxSeq ?? 0) + 1;

            // ユーザー入力は fillable 経由、採番系・登録者・事務所は forceFill で
            // アプリ側から強制設定する（ユーザーによる偽装を防ぎ、事務所スコープを担保）。
            $model = new Request($request->validated());
            $model->forceFill([
                'office_id' => $officeId,
                'reception_year' => $year,
                'reception_seq' => $seq,
                'reception_number' => sprintf('%d-%04d', $year, $seq),
                'registered_by' => $user->id,
            ])->save();

            return $model;
        });

        // 登録後は一覧へ遷移する（案件詳細 requests.show 実装後に詳細へ差し替え予定）。
        return redirect()->route('requests.index')
            ->with('status', "受付番号 {$created->reception_number} で登録しました。");
    }

    /**
     * 編集フォーム（画面設計書 画面#6）。
     *
     * 権限はルートの can:update ミドルウェアで担保済み（担当部署一致の一般職員
     * または管理者）。事務所スコープにより他事務所案件はバインディング段階で404。
     */
    public function edit(Request $request): View
    {
        return view('requests.edit', ['request' => $request]);
    }

    /**
     * 更新（画面設計書 画面#6）。
     *
     * 採番系・登録者・事務所（office_id / reception_* / registered_by）は
     * 更新対象外（fillable に含まれないため validated() にも現れない）。
     * 登録時点の値が維持される。
     */
    public function update(UpdateRequestRequest $form, Request $request): RedirectResponse
    {
        $request->update($form->validated());

        return redirect()->route('requests.show', $request)
            ->with('status', "受付番号 {$request->reception_number} を更新しました。");
    }

    /**
     * 削除（論理削除。要件定義書 2.1 / データベース設計書 2.3 SoftDeletes）。
     *
     * 権限はルートの can:delete ミドルウェアで担保済み。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $number = $request->reception_number;
        $request->delete();

        return redirect()->route('requests.index')
            ->with('status', "受付番号 {$number} を削除しました。");
    }
}
