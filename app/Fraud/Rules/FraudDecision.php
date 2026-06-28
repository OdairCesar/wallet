<?php

namespace App\Fraud\Rules;

final readonly class FraudDecision
{
    public function __construct(
        public bool $approved,
        public ?string $ruleId = null,
        public ?string $reason = null,
    ) {}

    public static function approved(): self
    {
        return new self(approved: true);
    }

    public static function blocked(string $ruleId, string $reason): self
    {
        return new self(approved: false, ruleId: $ruleId, reason: $reason);
    }
}
