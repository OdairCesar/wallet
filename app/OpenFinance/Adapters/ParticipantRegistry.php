<?php

namespace App\OpenFinance\Adapters;

use App\Projections\Models\Participant;
use InvalidArgumentException;

final class ParticipantRegistry
{
    public function __construct(
        private readonly MockParticipantAdapter $mockAdapter,
    ) {}

    public function resolve(string $organisationId): ParticipantAdapterInterface
    {
        $participant = Participant::query()
            ->where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->first();

        if ($participant === null) {
            throw new InvalidArgumentException("Participante não registrado: {$organisationId}");
        }

        return match ($participant->adapter) {
            'mock' => $this->mockAdapter,
            default => $this->mockAdapter,
        };
    }
}
