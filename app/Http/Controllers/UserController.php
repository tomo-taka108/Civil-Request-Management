<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ユーザー管理（画面設計書 画面#8-10・要件定義書 2.5）。
 *
 * システム管理者専用（ルートは can:admin で保護。StoreUserRequest 等でも二重に
 * authorize）。システム管理者は事務所に紐づかない全体管理ロールのため、
 * office_id によるスコープは適用せず全事務所のユーザーを対象にする（画面設計書 3.3）。
 */
class UserController extends Controller
{
    /** 一覧の1ページあたり表示件数。 */
    private const PER_PAGE = 20;

    /**
     * ユーザー一覧（画面#8）。所属事務所での絞り込みをオプションで受け付ける。
     */
    public function index(Request $request): View
    {
        // 所属事務所フィルタ（任意）。不正値は無視する。
        $officeId = $request->integer('office_id') ?: null;

        $users = User::query()
            ->with('office')
            ->when($officeId !== null, fn ($query) => $query->where('office_id', $officeId))
            // 管理者→事務所→ユーザーID の順で安定して並べる。
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderByRaw('office_id IS NULL')
            ->orderBy('office_id')
            ->orderBy('user_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'offices' => Office::orderBy('id')->get(),
            'selectedOfficeId' => $officeId,
        ]);
    }

    /**
     * ユーザー登録フォーム（画面#9）。
     */
    public function create(): View
    {
        return view('users.create', [
            'offices' => Office::orderBy('id')->get(),
        ]);
    }

    /**
     * ユーザー登録（画面#9）。初期パスワードを設定し、初回ログイン時に本人が
     * 変更する運用のため must_change_password=true で作成する。
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'user_id' => $validated['user_id'],
            'name' => $validated['name'],
            'password_hash' => Hash::make($validated['password']),
            'must_change_password' => true,
            'role' => $validated['role'],
            'office_id' => $validated['office_id'] ?? null,
            'department' => $validated['department'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('users.index')
            ->with('status', "ユーザー「{$validated['user_id']}」を登録しました。");
    }

    /**
     * ユーザー編集フォーム（画面#10）。
     */
    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'offices' => Office::orderBy('id')->get(),
        ]);
    }

    /**
     * ユーザー更新（画面#10）。氏名・ユーザーID・権限区分・所属事務所・担当部署を
     * 更新する。パスワードはこの画面では変更しない（再発行は別アクション）。
     *
     * 異動で所属事務所を変更しても、過去に登録・編集した案件は登録時点の事務所に
     * 残る（requests.office_id は変更しない。要件定義書 2.5）。
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'user_id' => $validated['user_id'],
            'name' => $validated['name'],
            'role' => $validated['role'],
            'office_id' => $validated['office_id'] ?? null,
            'department' => $validated['department'] ?? null,
        ]);

        return redirect()->route('users.index')
            ->with('status', "ユーザー「{$user->user_id}」を更新しました。");
    }

    /**
     * アカウント無効化（画面#10・要件定義書 2.5）。
     *
     * 物理削除せず status=inactive にする（登録済み案件の登録者としての表示は
     * 残す）。無効化ユーザーはログインできない（AuthController が status=active
     * のみ認証）。自分自身は無効化できない（管理者が自らを締め出す事故を防ぐ）。
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.edit', $user)
                ->with('error', '自分自身のアカウントは無効化できません。');
        }

        $user->update(['status' => 'inactive']);

        return redirect()->route('users.index')
            ->with('status', "ユーザー「{$user->user_id}」を無効化しました。");
    }

    /**
     * 初期パスワードの再発行（画面#10・画面設計書 6章）。
     *
     * 新しい初期パスワードを生成して設定し、must_change_password=true に戻す。
     * 通知はメール等ではなく画面表示（一度だけ）を想定するため、生成した平文を
     * フラッシュメッセージで返す（DBには保存しない）。
     */
    public function reissuePassword(Request $request, User $user): RedirectResponse
    {
        $newPassword = Str::password(12, symbols: false);

        $user->update([
            'password_hash' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        return redirect()->route('users.edit', $user)
            ->with('status', "初期パスワードを再発行しました。次のパスワードを本人に伝えてください（この画面を離れると再表示できません）：{$newPassword}");
    }
}
