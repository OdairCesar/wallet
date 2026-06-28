<?php

namespace App\OpenFinance\Services;

use App\Contracts\EventPublisherInterface;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\Models\User;
use App\OpenFinance\Enums\ConsentEventType;
use App\OpenFinance\Enums\ConsentStatus;
use App\OpenFinance\Exceptions\OpenFinanceDomainException;
use App\Projections\Models\Consent;
use App\Projections\Models\ConsentAccount;
use App\Projections\Models\WalletAccount;
use Illuminate\Support\Str;

final class ConsentService
{
    public function __construct(
        private readonly EventPublisherInterface $publisher,
    ) {}

    /**
     * @param  list<string>  $permissions
     * @return array{consentId: string, correlationId: string}
     */
    public function create(array $permissions, ?string $loggedUserDocument = null, ?string $clientId = null): array
    {
        $consentId = 'urn:wallet:consent:'.Str::uuid();
        $correlationId = (string) Str::uuid();
        $now = now()->utc();

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: ConsentEventType::Requested,
            aggregateId: $consentId,
            aggregateType: 'consent',
            payload: [
                'consentId' => $consentId,
                'clientId' => $clientId,
                'status' => ConsentStatus::AwaitingAuthorisation->value,
                'permissions' => $permissions,
                'creationDateTime' => $now->toIso8601String(),
                'expirationDateTime' => $now->copy()->addDay()->toIso8601String(),
                'loggedUserDocument' => $loggedUserDocument,
            ],
            correlationId: $correlationId,
        ));

        return ['consentId' => $consentId, 'correlationId' => $correlationId];
    }

    /**
     * @param  list<string>  $accountIds
     */
    public function authoriseForUser(string $consentId, User $user, array $accountIds = []): void
    {
        if ($user->document === null) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_INVALIDO',
                'Usuário sem documento cadastrado.',
            );
        }

        $this->authoriseByDocument($consentId, $user->document, $accountIds, $user);
    }

    /**
     * @param  list<string>  $accountIds
     */
    public function authoriseByDocument(
        string $consentId,
        string $loggedUserDocument,
        array $accountIds = [],
        ?User $user = null,
    ): void {
        $consent = $this->findAwaitingConsent($consentId);
        $this->assertDocumentMatchesConsent($consent, $loggedUserDocument);

        $resolvedAccountIds = $this->resolveAccountIdsForAuthorisation($loggedUserDocument, $accountIds, $user);

        if ($resolvedAccountIds === []) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_INVALIDO',
                'Nenhuma conta vinculada à autorização.',
            );
        }

        $this->completeAuthorisation($consentId, $resolvedAccountIds);
    }

    public function revoke(string $consentId): void
    {
        if (! Consent::query()->where('consent_id', $consentId)->exists()) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_NAO_ENCONTRADO',
                'Consentimento não encontrado.',
                404,
            );
        }

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: ConsentEventType::Revoked,
            aggregateId: $consentId,
            aggregateType: 'consent',
            payload: ['consentId' => $consentId],
        ));
    }

    private function findAwaitingConsent(string $consentId): Consent
    {
        $consent = Consent::query()->where('consent_id', $consentId)->first();

        if ($consent === null) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_NAO_ENCONTRADO',
                'Consentimento não encontrado.',
                404,
            );
        }

        if ($consent->status !== ConsentStatus::AwaitingAuthorisation->value) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_INVALIDO',
                'Consentimento não está aguardando autorização.',
            );
        }

        if ($consent->isExpired()) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_INVALIDO',
                'Consentimento expirado.',
            );
        }

        return $consent;
    }

    private function assertDocumentMatchesConsent(Consent $consent, string $loggedUserDocument): void
    {
        if ($consent->logged_user_document === null) {
            return;
        }

        $consentDoc = $this->normalizeDocument($consent->logged_user_document);
        $userDoc = $this->normalizeDocument($loggedUserDocument);

        if ($consentDoc !== $userDoc) {
            throw new OpenFinanceDomainException(
                'CONSENTIMENTO_INVALIDO',
                'Documento do usuário não confere com o consentimento.',
            );
        }
    }

    /**
     * @param  list<string>  $accountIds
     */
    private function completeAuthorisation(string $consentId, array $accountIds): void
    {
        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: ConsentEventType::Authorised,
            aggregateId: $consentId,
            aggregateType: 'consent',
            payload: ['consentId' => $consentId],
        ));

        foreach ($accountIds as $accountId) {
            ConsentAccount::query()->firstOrCreate([
                'consent_id' => $consentId,
                'account_id' => $accountId,
            ]);
        }
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document) ?? '';
    }

    public function findOrCreateUserByDocument(string $document): User
    {
        $existing = $this->findUserByNormalizedDocument($document);

        if ($existing !== null) {
            return $existing;
        }

        $normalized = $this->normalizeDocument($document);

        return User::query()->create([
            'name' => 'Open Finance User',
            'email' => 'of+'.$normalized.'@wallet.local',
            'password' => Str::random(40),
            'document' => $document,
        ]);
    }

    private function findUserByNormalizedDocument(string $loggedUserDocument): ?User
    {
        $normalized = $this->normalizeDocument($loggedUserDocument);

        return User::query()
            ->whereNotNull('document')
            ->get()
            ->first(fn (User $user) => $this->normalizeDocument($user->document ?? '') === $normalized);
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    private function resolveAccountIdsForAuthorisation(
        string $loggedUserDocument,
        array $accountIds,
        ?User $user = null,
    ): array {
        $user ??= $this->findUserByNormalizedDocument($loggedUserDocument);

        if ($accountIds !== []) {
            if ($user === null) {
                throw new OpenFinanceDomainException(
                    'CONSENTIMENTO_INVALIDO',
                    'Usuário não encontrado para validar as contas da autorização.',
                );
            }

            $this->assertAccountsOwnedByUser($user, $accountIds);

            return $accountIds;
        }

        $user ??= $this->findOrCreateUserByDocument($loggedUserDocument);

        return array_values(
            WalletAccount::query()->where('user_id', $user->id)->pluck('id')->all(),
        );
    }

    /**
     * @param  list<string>  $accountIds
     */
    private function assertAccountsOwnedByUser(User $user, array $accountIds): void
    {
        $ownedIds = WalletAccount::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        $ownedLookup = array_flip($ownedIds);

        foreach ($accountIds as $accountId) {
            if (! isset($ownedLookup[$accountId])) {
                throw new OpenFinanceDomainException(
                    'CONSENTIMENTO_INVALIDO',
                    'Conta não pertence ao usuário da autorização.',
                );
            }
        }
    }
}
