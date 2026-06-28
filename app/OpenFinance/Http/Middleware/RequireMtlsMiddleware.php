<?php

namespace App\OpenFinance\Http\Middleware;

use App\OpenFinance\Http\OpenFinanceResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireMtlsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('open_finance.fapi.mtls_enabled') && app()->isProduction()) {
            return OpenFinanceResponse::singleError(
                'MTLS_REQUIRED',
                'mTLS obrigatório',
                'MTLS_ENABLED deve ser true em produção.',
                503,
            );
        }

        if (! config('open_finance.fapi.mtls_enabled')) {
            return $next($request);
        }

        $verify = $request->header('X-SSL-Client-Verify')
            ?? $request->server('SSL_CLIENT_VERIFY');

        if ($verify !== 'SUCCESS') {
            return OpenFinanceResponse::singleError(
                'MTLS_REQUIRED',
                'mTLS obrigatório',
                'Certificado de cliente inválido ou ausente.',
                403,
            );
        }

        return $next($request);
    }
}
