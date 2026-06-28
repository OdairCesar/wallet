<?php

namespace App\OpenFinance\Security;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use App\Projections\Models\Consent;
use App\Projections\Models\ConsentAccount;
use App\Projections\Models\Operation;
use App\Projections\Models\WalletAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OpenFinanceAuthorizationService
{
    public function assertScope(OpenFinanceContext $context, OpenFinanceScope $scope): void
    {
        if (! $context->hasScope($scope->value)) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                "Scope {$scope->value} não presente no token.",
                403,
            );
        }
    }

    public function assertConsentAccess(OpenFinanceContext $context, string $consentId): void
    {
        if ($this->isDevUnrestricted($context)) {
            return;
        }

        if ($context->consentId !== null && $context->consentId !== $consentId) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Consentimento não autorizado para este token.',
                403,
            );
        }

        if ($context->consentId === null && $this->requiresStrictBinding()) {
            $consent = Consent::query()->where('consent_id', $consentId)->first();

            if ($consent === null) {
                throw new OpenFinanceAuthException(
                    'FORBIDDEN',
                    'Consentimento não autorizado para este token.',
                    403,
                );
            }

            if ($consent->client_id === null || $consent->client_id !== $context->clientId) {
                throw new OpenFinanceAuthException(
                    'FORBIDDEN',
                    'Consentimento não pertence ao cliente do token.',
                    403,
                );
            }
        }
    }

    public function assertConsentPermission(string $consentId, string $permission): void
    {
        if (! $this->requiresStrictBinding()) {
            return;
        }

        $consent = Consent::query()->where('consent_id', $consentId)->first();

        if ($consent === null) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Consentimento não autorizado para este token.',
                403,
            );
        }

        $permissions = $consent->permissions ?? [];

        if (! in_array($permission, $permissions, true)) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                "Permissão {$permission} não autorizada no consentimento.",
                403,
            );
        }
    }

    public function assertAccountAccess(OpenFinanceContext $context, string $accountId): void
    {
        if ($this->isDevUnrestricted($context)) {
            return;
        }

        if ($this->accountIdsForContext($context)->contains($accountId)) {
            return;
        }

        throw new OpenFinanceAuthException(
            'FORBIDDEN',
            'Conta não autorizada para este token.',
            403,
        );
    }

    public function assertAccountInConsent(string $consentId, string $accountId): void
    {
        if (! $this->requiresStrictBinding()) {
            return;
        }

        if (! ConsentAccount::query()
            ->where('consent_id', $consentId)
            ->where('account_id', $accountId)
            ->exists()) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Conta não vinculada ao consentimento.',
                403,
            );
        }
    }

    public function assertPaymentConsentContext(OpenFinanceContext $context): void
    {
        if (! $this->requiresStrictBinding()) {
            return;
        }

        if ($context->consentId === null) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Token sem consentimento para operação de pagamento.',
                403,
            );
        }
    }

    public function assertOperationAccess(OpenFinanceContext $context, string $correlationId): void
    {
        if ($this->isDevUnrestricted($context)) {
            return;
        }

        $operation = Operation::query()->where('correlation_id', $correlationId)->first();

        if ($operation === null) {
            return;
        }

        $clientId = $operation->metadata['client_id'] ?? null;

        if ($clientId !== null && $clientId !== $context->clientId) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Operação não autorizada para este cliente.',
                403,
            );
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function accountIdsForContext(OpenFinanceContext $context): Collection
    {
        if ($context->accountIds !== []) {
            return collect($context->accountIds);
        }

        if ($context->consentId !== null) {
            $ids = ConsentAccount::query()
                ->where('consent_id', $context->consentId)
                ->pluck('account_id');

            if ($ids->isNotEmpty()) {
                return $ids;
            }
        }

        if ($context->loggedUserDocument !== null) {
            return WalletAccount::query()
                ->whereHas('user', fn ($q) => $q->where('document', $context->loggedUserDocument))
                ->pluck('id');
        }

        return collect();
    }

    /**
     * @return Builder<WalletAccount>
     */
    public function accountsQueryForContext(OpenFinanceContext $context): Builder
    {
        if ($this->isDevUnrestricted($context)) {
            return WalletAccount::query();
        }

        $ids = $this->accountIdsForContext($context);

        if ($ids->isEmpty()) {
            return WalletAccount::query()->whereRaw('1 = 0');
        }

        return WalletAccount::query()->whereIn('id', $ids);
    }

    private function isDevUnrestricted(OpenFinanceContext $context): bool
    {
        if ($this->requiresStrictBinding()) {
            return false;
        }

        return $context->accountIds === []
            && $context->consentId === null
            && $context->loggedUserDocument === null;
    }

    private function requiresStrictBinding(): bool
    {
        return config('open_finance.fapi.enabled') || app()->isProduction();
    }
}
