<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTrustedHost
{
    /**
     * Reject requests whose Host header is not on the allow-list. An empty
     * list disables the check (local development default).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = Config::array('security.trusted_hosts', []);

        if ($allowed !== [] && ! in_array($request->getHost(), $allowed, true)) {
            abort(400, 'Untrusted Host header.');
        }

        return $next($request);
    }
}
