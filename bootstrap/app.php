<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use App\OpenFinance\Exceptions\OpenFinanceDomainException;
use App\OpenFinance\Http\OpenFinanceResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (OpenFinanceAuthException $e, Request $request) {
            if ($request->is('api/open-banking/*')) {
                return OpenFinanceResponse::fromAuthException($e);
            }
        });

        $exceptions->render(function (OpenFinanceDomainException $e, Request $request) {
            if ($request->is('api/open-banking/*')) {
                return OpenFinanceResponse::singleError(
                    $e->errorCode,
                    $e->title(),
                    $e->getMessage(),
                    $e->httpStatus,
                );
            }
        });

        $exceptions->render(function (\InvalidArgumentException $e, Request $request) {
            if ($request->is('api/open-banking/*') && ! $e instanceof OpenFinanceDomainException) {
                return OpenFinanceResponse::singleError(
                    'OPERACAO_INVALIDA',
                    'Operação inválida',
                    $e->getMessage(),
                    422,
                );
            }
        });
    })->create();
