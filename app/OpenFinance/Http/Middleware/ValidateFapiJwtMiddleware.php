<?php

namespace App\OpenFinance\Http\Middleware;

use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Security\FapiJwtValidator;
use App\OpenFinance\Security\OpenFinanceContext;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateFapiJwtMiddleware
{
    public function __construct(
        private readonly FapiJwtValidator $jwtValidator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('open_finance.fapi.enabled')) {
            if (app()->isProduction()) {
                return OpenFinanceResponse::singleError(
                    'FAPI_REQUIRED',
                    'FAPI obrigatório',
                    'FAPI_ENABLED deve ser true em produção.',
                    503,
                );
            }

            $context = OpenFinanceContext::devBypass(
                $request->header('X-Open-Finance-Test-Client'),
                $request->header('X-Open-Finance-Test-Consent'),
            );

            $document = $request->header('X-Open-Finance-Test-Document');
            $accountsHeader = $request->header('X-Open-Finance-Test-Accounts');

            if ($document !== null || $accountsHeader !== null) {
                $context = new OpenFinanceContext(
                    clientId: $context->clientId,
                    scopes: $context->scopes,
                    consentId: $context->consentId,
                    loggedUserDocument: $document,
                    organisationId: $context->organisationId,
                    accountIds: $accountsHeader !== null
                        ? array_values(array_filter(
                            explode(',', $accountsHeader),
                            static fn (string $id): bool => $id !== '',
                        ))
                        : [],
                );
            }

            $request->attributes->set(OpenFinanceContextResolver::ATTRIBUTE_KEY, $context);

            return $next($request);
        }

        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return OpenFinanceResponse::singleError(
                'UNAUTHORIZED',
                'Não autorizado',
                'Bearer token obrigatório.',
                401,
            );
        }

        try {
            $token = trim(substr($header, 7));
            $context = $this->jwtValidator->validate($token);
            $request->attributes->set(OpenFinanceContextResolver::ATTRIBUTE_KEY, $context);
        } catch (OpenFinanceAuthException $e) {
            return OpenFinanceResponse::fromAuthException($e);
        }

        return $next($request);
    }
}
