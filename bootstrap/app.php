<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\TraceRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // リクエストIDの付与とアクセスログ（ログ・監視設計書 4章）。
        // 全ルート共通。セッションや認証より前で例外が起きても ID が付くよう、グローバルに置く。
        $middleware->append(TraceRequest::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // DB エラーの例外メッセージには、SQL に埋め込まれた値（要望者名・要望内容など）が
        // そのまま入る。ログに個人に関わる情報を残さないため、値を「?」のままにした SQL と
        // エラーコード・発生箇所だけを記録し、標準のログ出力は止める（ログ・監視設計書 6章）。
        $exceptions->report(function (QueryException $e) {
            Log::error('データベースの処理に失敗しました', [
                'sql' => $e->getSql(),
                'error_code' => $e->errorInfo[1] ?? $e->getCode(),
                'trace' => collect($e->getTrace())
                    ->filter(fn (array $frame) => isset($frame['file']))
                    ->take(15)
                    ->map(fn (array $frame) => $frame['file'].':'.($frame['line'] ?? '?'))
                    ->values()
                    ->all(),
            ]);

            return false;
        });
    })->create();
