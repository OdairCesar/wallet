<?php

namespace App\Contracts;

use App\Infrastructure\Events\DomainEventEnvelope;

interface EventHandlerInterface
{
    public function handle(DomainEventEnvelope $envelope): void;

    /**
     * @return list<string>
     */
    public function subscribedEvents(): array;
}
