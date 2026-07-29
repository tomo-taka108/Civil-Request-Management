<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // システム管理者判定（画面設計書 3.3）。ユーザー管理系ルートを
        // ミドルウェア can:admin で保護するために使う。
        Gate::define('admin', fn (User $user): bool => $user->role === 'admin');
    }
}
