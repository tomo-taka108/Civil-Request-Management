<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequestRequest;
use App\Http\Requests\StoreRequestRequest;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

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

        $requests = Request::query()
            ->with('office')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters))
            ->orderByDesc('reception_date')
            ->orderByDesc('reception_time')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $filters,
        ]);
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
}
