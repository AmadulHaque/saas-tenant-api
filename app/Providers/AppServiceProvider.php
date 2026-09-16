<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureTrustedProxies();
        $this->configureRateLimiting();
    }
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn(Request $request): Limit => Limit::perMinute(60)
            ->by($this->resolveLimiterKey($request)));
    }

    private function configureTrustedProxies(): void
    {
        $trustedProxies = Config::string('security.trusted_proxies', '*');

        if ($trustedProxies === '') {
            return;
        }

        TrustProxies::at($trustedProxies);
    }

    private function resolveLimiterKey(Request $request): string
    {
        return $this->stringifyAuthId($request->user()?->getAuthIdentifier())
            ?? (string) $request->ip();
    }

    private function stringifyAuthId(mixed $authId): ?string
    {
        if (is_int($authId) || is_string($authId)) {
            return (string) $authId;
        }

        return null;
    }
}
