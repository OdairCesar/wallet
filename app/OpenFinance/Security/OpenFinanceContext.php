<?php

namespace App\OpenFinance\Security;

use App\OpenFinance\Enums\OpenFinanceScope;

final readonly class OpenFinanceContext
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $accountIds
     */
    public function __construct(
        public string $clientId,
        public array $scopes,
        public ?string $consentId = null,
        public ?string $loggedUserDocument = null,
        public ?string $organisationId = null,
        public array $accountIds = [],
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * @return list<string>
     */
    public static function allScopes(): array
    {
        return array_map(
            fn (OpenFinanceScope $scope) => $scope->value,
            OpenFinanceScope::cases(),
        );
    }

    public static function devBypass(?string $clientId = null, ?string $consentId = null): self
    {
        return new self(
            clientId: $clientId ?? 'dev-client',
            scopes: self::allScopes(),
            consentId: $consentId,
            organisationId: config('open_finance.organisation_id'),
        );
    }
}
