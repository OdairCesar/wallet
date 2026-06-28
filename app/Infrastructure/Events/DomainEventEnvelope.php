<?php

namespace App\Infrastructure\Events;

use Illuminate\Support\Str;

final readonly class DomainEventEnvelope
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $eventVersion,
        public string $aggregateId,
        public string $aggregateType,
        public string $occurredAt,
        public ?string $correlationId,
        public ?string $causationId,
        public array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function create(
        string $eventType,
        string $aggregateId,
        string $aggregateType,
        array $payload,
        ?string $correlationId = null,
        ?string $causationId = null,
        int $eventVersion = 1,
    ): self {
        return new self(
            eventId: (string) Str::uuid(),
            eventType: $eventType,
            eventVersion: $eventVersion,
            aggregateId: $aggregateId,
            aggregateType: $aggregateType,
            occurredAt: now()->utc()->toIso8601String(),
            correlationId: $correlationId ?? (string) Str::uuid(),
            causationId: $causationId,
            payload: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'aggregate_id' => $this->aggregateId,
            'aggregate_type' => $this->aggregateType,
            'occurred_at' => $this->occurredAt,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: $data['event_id'],
            eventType: $data['event_type'],
            eventVersion: $data['event_version'],
            aggregateId: $data['aggregate_id'],
            aggregateType: $data['aggregate_type'],
            occurredAt: $data['occurred_at'],
            correlationId: $data['correlation_id'] ?? null,
            causationId: $data['causation_id'] ?? null,
            payload: $data['payload'],
        );
    }
}
