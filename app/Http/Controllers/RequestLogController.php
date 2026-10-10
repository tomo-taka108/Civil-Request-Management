<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequestLogRequest;
use App\Http\Requests\UpdateRequestLogRequest;
use App\Models\Request;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * 対応履歴（request_logs）。要件定義書 §2.6。
 *
 * 権限（対応部署一致の一般職員、または管理者）はルートの can:manageLog
 * ミドルウェアで担保済み。事務所スコープは親 Request のルートモデルバインディング
 * （OfficeScope）で担保され、{requestLog} の取り違えは scopeBindings() で防ぐ
 * （routes/web.php 参照）。
 *
 * ※ クラス名の衝突を避けるため、Illuminate\Http\Request は import せず、
 *    型ヒントには FormRequest（Store/UpdateRequestLogRequest）を使う
 *    （RequestController と同じ作法）。
 */
class RequestLogController extends Controller
{
    /**
     * 対応履歴の追加。
     *
     * recorded_by / request_id はユーザー入力を受け付けず、アプリ側から
     * forceFill する（Mass assignment 防御。RequestController::store と同じ作法）。
     * 保存により $touches が効き、親 request.updated_at が自動更新される。
     */
    public function store(StoreRequestLogRequest $form, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $form->user();

        $log = new RequestLog($form->validated());
        $log->forceFill([
            'request_id' => $request->id,
            'recorded_by' => $user->id,
        ])->save();

        return redirect()->route('requests.show', $request)
            ->with('status', '対応履歴を追加しました。');
    }

    /**
     * 対応履歴の編集。
     *
     * logged_on / content のみ更新する。recorded_by は変更しない
     * （誰が記録したかの事実を保持するため。RequestLog::wasEdited が
     * created_at と updated_at の差で「編集済み」を判定する）。
     */
    public function update(UpdateRequestLogRequest $form, Request $request, RequestLog $log): RedirectResponse
    {
        $log->update($form->validated());

        return redirect()->route('requests.show', $request)
            ->with('status', '対応履歴を更新しました。');
    }

    /**
     * 対応履歴の削除（論理削除）。
     */
    public function destroy(Request $request, RequestLog $log): RedirectResponse
    {
        $log->delete();

        return redirect()->route('requests.show', $request)
            ->with('status', '対応履歴を削除しました。');
    }
}
