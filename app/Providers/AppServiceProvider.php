<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Observers\FlushPlansCache;
use App\Observers\FlushTenantDashboardCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        $this->configurePassport();
        $this->configureObservers();
        $this->configureUrls();
    }

    /**
     * Register cache-flushing observers for cached resources.
     */
    private function configureObservers(): void
    {
        User::observe(FlushTenantDashboardCache::class);
        Customer::observe(FlushTenantDashboardCache::class);
        Subscription::observe(FlushTenantDashboardCache::class);
        SubscriptionPlan::observe(FlushPlansCache::class);
    }

    private function configurePassport(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addDays(30));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->resolveLimiterKey($request)));

        RateLimiter::for('authenticated', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->resolveLimiterKey($request)));

        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(sprintf('auth|%s|%s', $request->ip(), (string) $request->input('email'))));
    }

    /**
     * Generate absolute URLs (pagination links, etc.) over https when forced.
     */
    private function configureUrls(): void
    {
        if (Config::boolean('security.force_https', false)) {
            URL::forceScheme('https');
        }
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
