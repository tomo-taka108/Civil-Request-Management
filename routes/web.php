<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RequestController;
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

    // 案件（苦情・要望・異常箇所）。一覧・検索・新規登録。
    // 詳細・編集・削除は次フェーズで追加する（create を show より前に定義すること）。
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');

    // 地図・ユーザー管理などの各画面は次フェーズで追加する。
    // 認証後のトップは案件一覧にリダイレクトする。
    Route::get('/', fn () => redirect()->route('requests.index'))->name('home');
});
