<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequestRequest;
use App\Models\Request;
use App\Models\User;
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

        // 暫定遷移先。案件詳細（requests.show）実装後に差し替える。
        return redirect()->route('requests.create')
            ->with('status', "受付番号 {$created->reception_number} で登録しました。");
    }
}
