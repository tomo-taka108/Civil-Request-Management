<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
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

    // 案件（苦情・要望・異常箇所）。一覧・検索・新規登録・詳細・編集・削除。
    // create / edit は {request} の show より前に定義すること。
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    // CSV出力は一覧検索と同じ条件を引き継ぐ（画面設計書 2章の設計メモ）。
    Route::get('/requests-export', [RequestController::class, 'exportCsv'])->name('requests.export');
    Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{request}', [RequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{request}/edit', [RequestController::class, 'edit'])
        ->middleware('can:update,request')->name('requests.edit');
    Route::put('/requests/{request}', [RequestController::class, 'update'])
        ->middleware('can:update,request')->name('requests.update');
    Route::delete('/requests/{request}', [RequestController::class, 'destroy'])
        ->middleware('can:delete,request')->name('requests.destroy');

    // 地図表示（画面設計書 画面#7）。pins は非同期でピン（GeoJSON）を返す。
    Route::get('/map', [MapController::class, 'index'])->name('map.index');
    Route::get('/map/pins', [MapController::class, 'pins'])->name('map.pins');

    // ユーザー管理（システム管理者限定。画面設計書 3.3）。
    Route::middleware('can:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        // 再有効化（無効化の逆操作）。
        Route::put('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        // 物理削除（案件を持たない誤登録アカウントの掃除用）。
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        // パスワード再発行（画面#10。設計書6章の残課題を実装）。
        Route::put('/users/{user}/reissue-password', [UserController::class, 'reissuePassword'])->name('users.reissue-password');
    });

    // 認証後のトップは案件一覧にリダイレクトする。
    Route::get('/', fn () => redirect()->route('requests.index'))->name('home');
});
