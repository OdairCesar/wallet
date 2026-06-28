<?php

namespace App\Infrastructure\Events;

use App\Contracts\EventHandlerInterface;
use App\Contracts\EventPublisherInterface;

final class InMemoryEventPublisher implements EventPublisherInterface
{
    /** @var list<DomainEventEnvelope> */
    private array $events = [];

    /** @var list<EventHandlerInterface> */
    private array $handlers = [];

    public function subscribe(EventHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function publish(DomainEventEnvelope $envelope, ?string $topic = null): void
    {
        $this->events[] = $envelope;

        foreach ($this->handlers as $handler) {
            if (in_array($envelope->eventType, $handler->subscribedEvents(), true)) {
                $handler->handle($envelope);
            }
        }
    }

    public function published(): array
    {
        return $this->events;
    }

    public function flush(): void
    {
        $this->events = [];
    }
}
