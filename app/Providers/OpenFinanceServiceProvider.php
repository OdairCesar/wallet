<?php

namespace App\Providers;

use App\OpenFinance\Security\OpenFinanceContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class OpenFinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDocsGate();
        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    protected function configureDocsGate(): void
    {
        Gate::define('viewApiDocs', function (): bool {
            $expected = config('open_finance.docs.access_token');

            if ($expected === null || $expected === '') {
                return false;
            }

            return request()->header('X-Docs-Access-Token') === $expected;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('open-finance', function (Request $request) {
            $context = $request->attributes->get('open_finance_context');
            $key = $context instanceof OpenFinanceContext
                ? $context->clientId
                : $request->ip();

            return Limit::perMinute(config('open_finance.rate_limit.per_minute'))->by($key);
        });
    }

    protected function configureApiDocumentation(): void
    {
        Scramble::configure()
            ->routes(function (Route $route): bool {
                return str_starts_with($route->uri(), 'api/open-banking');
            })
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                        ->as('fapiBearer')
                        ->setDescription('Token OAuth2/FAPI (escopo payments, accounts)')
                );
            });
    }
}
