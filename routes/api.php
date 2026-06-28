<?php

use App\OpenFinance\Http\Controllers\AccountController;
use App\OpenFinance\Http\Controllers\ConsentController;
use App\OpenFinance\Http\Controllers\OperationController;
use App\OpenFinance\Http\Controllers\PixPaymentController;
use App\OpenFinance\Http\Controllers\ResourceController;
use App\OpenFinance\Http\Middleware\FapiHeadersMiddleware;
use App\OpenFinance\Http\Middleware\IdempotencyMiddleware;
use App\OpenFinance\Http\Middleware\RequireMtlsMiddleware;
use App\OpenFinance\Http\Middleware\ValidateFapiJwtMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([
    RequireMtlsMiddleware::class,
    ValidateFapiJwtMiddleware::class,
    FapiHeadersMiddleware::class,
    IdempotencyMiddleware::class,
    'throttle:open-finance',
])
    ->prefix('open-banking')
    ->group(function () {
        Route::post('consents/v3/consents', [ConsentController::class, 'store']);
        Route::get('consents/v3/consents/{consentId}', [ConsentController::class, 'show'])->where('consentId', 'urn:wallet:consent:[^/]+');
        Route::post('consents/v3/consents/{consentId}/authorise', [ConsentController::class, 'authorise'])->where('consentId', 'urn:wallet:consent:[^/]+');
        Route::patch('consents/v3/consents/{consentId}', [ConsentController::class, 'revoke'])->where('consentId', 'urn:wallet:consent:[^/]+');

        Route::get('accounts/v2/accounts', [AccountController::class, 'index']);
        Route::post('accounts/v2/accounts', [AccountController::class, 'store']);
        Route::get('accounts/v2/accounts/{accountId}', [AccountController::class, 'show']);
        Route::get('accounts/v2/accounts/{accountId}/balances', [AccountController::class, 'balances']);
        Route::get('accounts/v2/accounts/{accountId}/transactions', [AccountController::class, 'transactions']);
        Route::post('accounts/v2/accounts/{accountId}/transfers', [AccountController::class, 'transfer']);

        Route::post('payments/v5/pix/payments', [PixPaymentController::class, 'store']);
        Route::get('payments/v5/pix/payments/{paymentId}', [PixPaymentController::class, 'show']);
        Route::patch('payments/v5/pix/payments/{paymentId}', [PixPaymentController::class, 'cancel']);

        Route::get('resources/v3/resources', [ResourceController::class, 'index']);

        Route::get('operations/v1/operations/{correlationId}', [OperationController::class, 'show']);
    });
