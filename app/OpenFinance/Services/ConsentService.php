<?php

namespace App\OpenFinance\Services;

use App\Contracts\EventPublisherInterface;
use App\Infrastructure\Events\DomainEventEnvelope;
use App\OpenFinance\Enums\ConsentEventType;
use App\OpenFinance\Enums\ConsentStatus;
use App\Projections\Models\Consent;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ConsentService
{
    public function __construct(
        private readonly EventPublisherInterface $publisher,
    ) {}

    /**
     * @param  list<string>  $permissions
     * @return array{consentId: string, correlationId: string}
     */
    public function create(array $permissions, ?string $loggedUserDocument = null): array
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

    public function authorise(string $consentId): void
    {
        $this->assertConsentExists($consentId);

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: ConsentEventType::Authorised,
            aggregateId: $consentId,
            aggregateType: 'consent',
            payload: ['consentId' => $consentId],
        ));
    }

    public function revoke(string $consentId): void
    {
        $this->assertConsentExists($consentId);

        $this->publisher->publish(DomainEventEnvelope::create(
            eventType: ConsentEventType::Revoked,
            aggregateId: $consentId,
            aggregateType: 'consent',
            payload: ['consentId' => $consentId],
        ));
    }

    private function assertConsentExists(string $consentId): void
    {
        if (! Consent::query()->where('consent_id', $consentId)->exists()) {
            throw new InvalidArgumentException('Consentimento não encontrado.');
        }
    }
}
