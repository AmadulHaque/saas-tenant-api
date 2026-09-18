<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * Optionally force HTTPS, then stamp defense-in-depth response headers.
     * HSTS is emitted only when the connection is (or must be) secure; the
     * CSP applies to API responses only so the Scramble docs UI keeps working.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = Config::boolean('security.force_https', false);

        if ($forceHttps && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');

        if ($request->is('api/*')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }

        if (($request->secure() || $forceHttps) && Config::boolean('security.hsts.enabled', true)) {
            $headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        return $response;
    }

    private function hstsValue(): string
    {
        $value = sprintf('max-age=%d', Config::integer('security.hsts.max_age', 31536000));

        if (Config::boolean('security.hsts.include_subdomains', true)) {
            $value .= '; includeSubDomains';
        }

        if (Config::boolean('security.hsts.preload', false)) {
            $value .= '; preload';
        }

        return $value;
    }
}
