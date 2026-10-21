<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
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

        // ログイン中のユーザーが分かった時点で、以降のログ行にユーザーID（users.id）を付ける
        // （ログ・監視設計書 4章）。セッションからの復元時とログイン時の両方で発火する。
        Event::listen(Authenticated::class, function (Authenticated $event): void {
            Context::add('user_id', $event->user->getAuthIdentifier());
        });
    }
}
