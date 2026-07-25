<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * パスワード変更（初回ログイン時の強制変更・任意タイミングでの変更を兼ねる）。
 *
 * 画面設計書 3.4（初回強制変更）・4章のコントローラ対応表に対応。
 */
class PasswordController extends Controller
{
    /**
     * パスワード変更後のデフォルト遷移先。
     * 案件一覧（requests.index）実装後にここを差し替える。
     */
    private const HOME_ROUTE = '/';

    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Auth::user();

        // 現在のパスワードが一致するか検証する。
        if (! Hash::check($validated['current_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => '現在のパスワードが正しくありません。',
            ]);
        }

        // 新しいパスワードを保存し、初回強制変更フラグを解除する。
        $user->password_hash = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        return redirect(self::HOME_ROUTE)->with('status', 'パスワードを変更しました。');
    }
}
