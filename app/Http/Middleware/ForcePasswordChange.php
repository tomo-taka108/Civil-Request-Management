<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 初回ログイン時のパスワード強制変更（画面設計書 3.4）。
 *
 * users.must_change_password = true のユーザーは、パスワード変更・ログアウト
 * 以外のページにアクセスできず、パスワード変更画面へリダイレクトされる。
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
