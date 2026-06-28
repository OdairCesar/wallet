<?php

namespace App\Infrastructure\Events;

use App\Contracts\EventHandlerInterface;
use App\Contracts\EventPublisherInterface;
use Illuminate\Support\Facades\Log;

final class KafkaEventPublisher implements EventPublisherInterface
{
    /** @var list<DomainEventEnvelope> */
    private array $buffer = [];

    /** @var list<EventHandlerInterface> */
    private array $handlers = [];

    public function subscribe(EventHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function publish(DomainEventEnvelope $envelope, ?string $topic = null): void
    {
        $topic ??= config('event_bus.topics.wallet_events');

        Log::info('kafka.publish.stub', [
            'topic' => $topic,
            'event_type' => $envelope->eventType,
            'aggregate_id' => $envelope->aggregateId,
        ]);

        $this->buffer[] = $envelope;

        foreach ($this->handlers as $handler) {
            if (in_array($envelope->eventType, $handler->subscribedEvents(), true)) {
                $handler->handle($envelope);
            }
        }
    }

    public function published(): array
    {
        return $this->buffer;
    }
}
