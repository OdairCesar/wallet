<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Requests\StoreConsentRequest;
use App\OpenFinance\Http\Resources\ConsentResource;
use App\OpenFinance\Services\ConsentService;
use App\Projections\Models\Consent;
use Illuminate\Http\JsonResponse;

final class ConsentController
{
    public function __construct(
        private readonly ConsentService $consents,
    ) {}

    /**
     * Criar consentimento Open Finance v3.
     */
    public function store(StoreConsentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->consents->create(
            permissions: $validated['data']['permissions'],
            loggedUserDocument: $validated['data']['loggedUser']['document']['identification'] ?? null,
        );

        $consent = Consent::query()->where('consent_id', $result['consentId'])->firstOrFail();

        return OpenFinanceResponse::data(
            ConsentResource::fromModel($consent),
            201,
        );
    }

    /**
     * Consultar consentimento por ID.
     */
    public function show(string $consentId): JsonResponse
    {
        $consent = Consent::query()->where('consent_id', $consentId)->first();

        if ($consent === null) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONSENTIMENTO_NAO_ENCONTRADO',
                    'title' => 'Consentimento não encontrado',
                    'detail' => 'O consentId informado não existe.',
                ],
            ], 404);
        }

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }

    /**
     * Autorizar consentimento (simula fluxo do usuário na detentora).
     */
    public function authorise(string $consentId): JsonResponse
    {
        try {
            $this->consents->authorise($consentId);
        } catch (\InvalidArgumentException) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONSENTIMENTO_NAO_ENCONTRADO',
                    'title' => 'Consentimento não encontrado',
                    'detail' => 'O consentId informado não existe.',
                ],
            ], 404);
        }

        $consent = Consent::query()->where('consent_id', $consentId)->firstOrFail();

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }

    /**
     * Revogar consentimento.
     */
    public function revoke(string $consentId): JsonResponse
    {
        try {
            $this->consents->revoke($consentId);
        } catch (\InvalidArgumentException) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONSENTIMENTO_NAO_ENCONTRADO',
                    'title' => 'Consentimento não encontrado',
                    'detail' => 'O consentId informado não existe.',
                ],
            ], 404);
        }

        $consent = Consent::query()->where('consent_id', $consentId)->firstOrFail();

        return OpenFinanceResponse::data(ConsentResource::fromModel($consent));
    }
}
