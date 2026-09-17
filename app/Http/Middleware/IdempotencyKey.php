<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class IdempotencyKey
{
    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 5;

    public function __construct(private ExceptionHandler $exceptions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = mb_trim((string) $request->headers->get('Idempotency-Key', ''));

        if ($idempotencyKey === '') {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotencyKey) !== 1) {
            return new JsonResponse([
                'message' => __('http.idempotency.invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey);
        $requestHash = $this->requestHash($request);
        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return new JsonResponse([
                'message' => __('http.retry_soon'),
            ], Response::HTTP_SERVICE_UNAVAILABLE, [
                'Idempotency-Key' => $idempotencyKey,
                'Retry-After' => (string) self::LOCK_WAIT_SECONDS,
            ]);
        }

        try {
            return $this->handleLockedRequest($request, $next, $cacheKey, $requestHash, $idempotencyKey);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function handleLockedRequest(
        Request $request,
        Closure $next,
        string $cacheKey,
        string $requestHash,
        string $idempotencyKey,
    ): Response {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (($cached['request_hash'] ?? null) !== $requestHash) {
                return new JsonResponse([
                    'message' => __('http.idempotency.conflict'),
                ], Response::HTTP_CONFLICT);
            }

            /** @var array<string, mixed> $cached */
            return $this->replayResponse($cached, $idempotencyKey);
        }

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            report($throwable);
            $response = $this->exceptions->render($request, $throwable);
        }

        if ($this->shouldCacheResponse($response)) {
            $ttl = max(config()->integer('idempotency.ttl_minutes', 10), 1);

            Cache::put($cacheKey, [
                'request_hash' => $requestHash,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent() ?: '',
                'content_type' => $response->headers->get('Content-Type'),
            ], now()->addMinutes($ttl));
        }

        $response->headers->set('Idempotency-Key', $idempotencyKey);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function replayResponse(array $cached, string $idempotencyKey): Response
    {
        $body = is_string($cached['body'] ?? null) ? $cached['body'] : '';
        $status = is_int($cached['status'] ?? null) ? $cached['status'] : Response::HTTP_OK;
        $contentType = $cached['content_type'] ?? null;

        $response = new Response($body, $status);

        if (is_string($contentType) && $contentType !== '') {
            $response->headers->set('Content-Type', $contentType);
        }

        $response->headers->set('Idempotency-Key', $idempotencyKey);
        $response->headers->set('Idempotency-Replayed', 'true');

        return $response;
    }

    private function scope(Request $request): string
    {
        $authId = $request->user('api')?->getAuthIdentifier();

        $principal = is_int($authId) || is_string($authId)
            ? (string) $authId
            : (string) $request->ip();

        return 'principal:'.$principal;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $routeName = $request->route()?->getName() ?? $request->path();

        return 'idempotency:'.sha1(sprintf('%s|%s|%s', $this->scope($request), $routeName, $idempotencyKey));
    }

    private function requestHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            $request->getContent(),
        ]));
    }

    private function shouldCacheResponse(Response $response): bool
    {
        $status = $response->getStatusCode();

        // Server errors and throttling must never be replayed.
        return $status >= Response::HTTP_OK
            && $status < Response::HTTP_INTERNAL_SERVER_ERROR
            && $status !== Response::HTTP_TOO_MANY_REQUESTS;
    }
}
