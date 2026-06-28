<?php

namespace App\OpenFinance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class FapiHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('x-fapi-interaction-id')) {
            $request->headers->set('x-fapi-interaction-id', (string) Str::uuid());
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('x-fapi-interaction-id', $request->header('x-fapi-interaction-id'));

        return $response;
    }
}
