<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Requests\StoreConsentRequest;
use App\OpenFinance\Http\Resources\ConsentResource;
use App\OpenFinance\Security\OpenFinanceAuthorizationService;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use App\OpenFinance\Services\ConsentService;
use App\Projections\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly OpenFinanceAuthorizationService $authorization,
        private readonly OpenFinanceContextResolver $contextResolver,
    ) {}

    public function store(StoreConsentRequest $request): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ConsentsWrite);

        $validated = $request->validated();

        $result = $this->consents->create(
            permissions: $validated['data']['permissions'],
            loggedUserDocument: $validated['data']['loggedUser']['document']['identification'] ?? null,
            clientId: $context->clientId,
        );

        $consent = Consent::query()->where('consent_id', $result['consentId'])->firstOrFail();

        return OpenFinanceResponse::data(
            ConsentResource::fromModel($consent),
            201,
        );
    }

    public function show(Request $request, string $consentId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ConsentsRead);
        $this->authorization->assertConsentAccess($context, $consentId);

        $consent = Consent::query()->where('consent_id', $consentId)->first();

        if ($consent === null) {
            return OpenFinanceResponse::notFound(
                'CONSENTIMENTO_NAO_ENCONTRADO',
                'Consentimento não encontrado',
                'O consentId informado não existe.',
            );
        }

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }

    public function revoke(Request $request, string $consentId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ConsentsWrite);
        $this->authorization->assertConsentAccess($context, $consentId);

        $this->consents->revoke($consentId);

        $consent = Consent::query()->where('consent_id', $consentId)->firstOrFail();

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }

    public function authorise(Request $request, string $consentId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ConsentsWrite);
        $this->authorization->assertConsentAccess($context, $consentId);

        if ($context->loggedUserDocument === null) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Token sem documento do usuário (logged_user_document).',
                403,
            );
        }

        $this->consents->authoriseByDocument(
            $consentId,
            $context->loggedUserDocument,
            $context->accountIds,
        );

        $consent = Consent::query()->where('consent_id', $consentId)->firstOrFail();

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }
}
