<?php

namespace App\OpenFinance\Http\Middleware;

use App\OpenFinance\Http\OpenFinanceResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class FapiHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $interactionId = $request->header('x-fapi-interaction-id');

        if ($interactionId !== null && $interactionId !== '') {
            if (! Str::isUuid($interactionId)) {
                return OpenFinanceResponse::singleError(
                    'INVALID_FAPI_HEADER',
                    'Header inválido',
                    'x-fapi-interaction-id deve ser um UUID válido.',
                    400,
                );
            }
        } else {
            $request->headers->set('x-fapi-interaction-id', (string) Str::uuid());
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('x-fapi-interaction-id', $request->header('x-fapi-interaction-id'));

        return $response;
    }
}
