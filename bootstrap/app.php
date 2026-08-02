<?php

use App\Http\Middleware\ForcePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ALB(HTTPS) 配下で動かすための必須設定（インフラ設計書 6.1）。
        // ALB が HTTPS を終端し EC2 へは HTTP で転送するため、この設定がないと
        // Laravel は自分を HTTP 接続だと誤認し、url()/route() が http:// を生成する・
        // secure cookie が送出されない・https リダイレクトループが起きる等の不具合になる。
        // at: '*' は「ALB のみが対象ポートに到達できる」ことを SG（設計書 3.5）で
        // 担保している前提。ローカル開発では X-Forwarded-* が無いため無害。
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );

        // 初回ログイン時のパスワード強制変更（画面設計書 3.4）
        $middleware->alias([
            'force.password.change' => ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
