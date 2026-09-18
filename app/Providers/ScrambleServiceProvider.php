<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureAccess();
        $this->configureDocument();
    }

    private function configureAccess(): void
    {
        Gate::define('viewApiDocs', fn (?object $user = null): bool => $this->app->environment('local', 'testing'));
    }

    private function configureDocument(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(static function (OpenApi $openApi): void {
                /** @var SecurityScheme $bearer */
                $bearer = SecurityScheme::http('bearer');
                $bearer->setDescription('Passport personal access token from `/auth/login` or `/auth/register`. Send as `Authorization: Bearer <token>`.');

                $openApi->secure($bearer);
            });
    }
}
