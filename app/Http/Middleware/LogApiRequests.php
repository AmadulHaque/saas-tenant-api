<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class LogApiRequests
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        if (config()->boolean('logging.log_api_requests', false)) {
            Log::info('api.request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'user_agent' => (string) $request->userAgent(),
            ]);
        }

        $response->headers->set('X-Response-Time', $durationMs . 'ms');

        return $response;
    }
}
