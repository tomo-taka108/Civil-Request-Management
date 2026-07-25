<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use Illuminate\Support\Facades\Route;

// 認証（未ログインユーザー向け）
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// 認証済みユーザー向け（初回ログイン時はパスワード変更を強制）
Route::middleware(['auth', 'force.password.change'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // パスワード変更（初回強制変更を含む）
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.update');

    // 案件・地図・ユーザー管理などの各画面は次フェーズで追加する。
    // 認証後のトップ（暫定）。requests.index 実装後に置き換える。
    Route::get('/', function () {
        return view('welcome');
    })->name('home');
});
