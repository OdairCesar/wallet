<?php

namespace App\OpenFinance\Http\Middleware;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PATCH'], true)) {
            return $next($request);
        }

        if (config('open_finance.fapi.enabled') && ! $request->headers->has('x-idempotency-key')) {
            return OpenFinanceResponse::singleError(
                'IDEMPOTENCY_KEY_REQUIRED',
                'Header obrigatório',
                'x-idempotency-key é obrigatório para operações de escrita.',
                400,
            );
        }

        if (! $request->headers->has('x-idempotency-key')) {
            return $next($request);
        }

        return $this->idempotency->resolve($request, fn () => $next($request));
    }
}
