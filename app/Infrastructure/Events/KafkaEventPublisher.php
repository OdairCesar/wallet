<?php

namespace App\Infrastructure\Events;

use App\Contracts\EventPublisherInterface;
use Illuminate\Support\Facades\Log;

/**
 * Stub para produção na VM.
 * Instale mateusjunges/laravel-kafka e ext-rdkafka, depois implemente publish().
 */
final class KafkaEventPublisher implements EventPublisherInterface
{
    /** @var list<DomainEventEnvelope> */
    private array $buffer = [];

    public function publish(DomainEventEnvelope $envelope, ?string $topic = null): void
    {
        $topic ??= config('event_bus.topics.wallet_events');

        Log::info('kafka.publish.stub', [
            'topic' => $topic,
            'event_type' => $envelope->eventType,
            'aggregate_id' => $envelope->aggregateId,
        ]);

        $this->buffer[] = $envelope;
    }

    public function published(): array
    {
        return $this->buffer;
    }
}
