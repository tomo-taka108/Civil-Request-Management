<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 認証（ログイン・ログアウト）。
 *
 * 画面設計書 2章のルーティング・4章のコントローラ対応表に対応。
 */
class AuthController extends Controller
{
    /**
     * ログイン成功後のデフォルト遷移先。
     * 案件一覧（requests.index）は次フェーズで実装するため、暫定でトップに返す。
     * requests.index 実装後にここを差し替える。
     */
    private const HOME_ROUTE = '/';

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'user_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // ログインIDは大文字小文字を区別しない（レビュー指摘③）。
        // 登録時も同様に小文字化して保存する運用とする。
        $userId = mb_strtolower($credentials['user_id']);

        // 有効なアカウント（status=active）のみ認証対象とする。
        $attempt = Auth::attempt([
            'user_id' => $userId,
            'password' => $credentials['password'],
            'status' => 'active',
        ]);

        if (! $attempt) {
            throw ValidationException::withMessages([
                'user_id' => 'ユーザーIDまたはパスワードが正しくありません。',
            ]);
        }

        // セッション固定攻撃対策
        $request->session()->regenerate();

        // 初回（初期パスワードのまま）ログイン時はパスワード変更を強制する。
        // 実際のリダイレクトは ForcePasswordChange ミドルウェアが担うため、
        // ここでは通常の遷移先に返せばよい。
        return redirect()->intended(self::HOME_ROUTE);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
