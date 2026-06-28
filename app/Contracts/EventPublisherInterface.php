<?php

namespace App\Contracts;

use App\Infrastructure\Events\DomainEventEnvelope;

interface EventPublisherInterface
{
    public function publish(DomainEventEnvelope $envelope, ?string $topic = null): void;

    public function subscribe(EventHandlerInterface $handler): void;

    /**
     * @return list<DomainEventEnvelope>
     */
    public function published(): array;
}
