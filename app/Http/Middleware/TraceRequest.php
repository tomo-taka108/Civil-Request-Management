<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * リクエストIDの付与とアクセスログ（ログ・監視設計書 4章・5章）。
 *
 * - リクエストごとに ID を決め、Context に入れる。以降このリクエストで出る
 *   ログ行には、Laravel が自動で request_id を付ける（ユーザーIDは
 *   AppServiceProvider でログイン状態が分かった時点で付ける）。
 * - 同じ ID をレスポンスヘッダ X-Request-Id とエラー画面に出し、
 *   利用者の報告とログを突き合わせられるようにする。
 * - 処理の最後にアクセスログを1行出す。クエリ文字列は出さない
 *   （検索語に要望者名が入りうる・署名付きURLの署名を含むため）。
 */
class TraceRequest
{
    public const HEADER = 'X-Request-Id';

    /** 受け付ける X-Request-Id の形式。改行などを含む値でログ行を偽造されないよう、英数字とハイフンに限る。 */
    private const VALID_ID = '/\A[A-Za-z0-9-]{1,64}\z/';

    /** アクセスログを出さないパス。ALB が30秒ごとに叩く死活確認で、記録しても調査に使わないため。 */
    private const SILENT_PATHS = ['health', 'up'];

    private const STARTED_AT = 'trace_request_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        Context::add('request_id', $requestId);
        $request->attributes->set(self::STARTED_AT, hrtime(true));

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is(...self::SILENT_PATHS)) {
            return;
        }

        $startedAt = $request->attributes->get(self::STARTED_AT);

        Log::info('access', [
            'method' => $request->method(),
            // path() はクエリ文字列を含まない。
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => is_int($startedAt) ? intdiv(hrtime(true) - $startedAt, 1_000_000) : null,
        ]);
    }

    private function resolveRequestId(Request $request): string
    {
        $given = $request->headers->get(self::HEADER);

        return is_string($given) && preg_match(self::VALID_ID, $given) === 1
            ? $given
            : (string) Str::uuid();
    }
}
