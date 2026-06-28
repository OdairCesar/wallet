<?php

namespace Tests\Support;

use App\OpenFinance\Security\OpenFinanceContext;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

final class OpenFinanceTestToken
{
    public static function bearer(
        ?string $clientId = 'test-client',
        ?string $consentId = null,
        ?array $scopes = null,
        array $accountIds = [],
        ?string $loggedUserDocument = null,
    ): string {
        $scopes ??= OpenFinanceContext::allScopes();
        $secret = config('open_finance.jwt.secret') ?? 'test-jwt-secret-for-open-finance';

        $config = Configuration::forSymmetricSigner(
            new Sha256,
            InMemory::plainText($secret),
        );

        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->issuedBy(config('open_finance.jwt.issuer') ?: 'wallet-test')
            ->permittedFor(config('open_finance.jwt.audience') ?: 'wallet-api')
            ->identifiedBy('test-jti')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('client_id', $clientId)
            ->withClaim('scope', implode(' ', $scopes));

        if ($consentId !== null) {
            $builder = $builder->withClaim('consent_id', $consentId);
        }

        if ($accountIds !== []) {
            $builder = $builder->withClaim('account_ids', $accountIds);
        }

        if ($loggedUserDocument !== null) {
            $builder = $builder->withClaim('logged_user_document', $loggedUserDocument);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * @return array<string, string>
     */
    public static function authorizationHeader(
        ?string $clientId = 'test-client',
        ?string $consentId = null,
        ?array $scopes = null,
        array $accountIds = [],
        ?string $loggedUserDocument = null,
    ): array {
        if (config('open_finance.fapi.enabled')) {
            return ['Authorization' => 'Bearer '.self::bearer($clientId, $consentId, $scopes, $accountIds, $loggedUserDocument)];
        }

        $headers = [];

        if ($clientId !== null) {
            $headers['X-Open-Finance-Test-Client'] = $clientId;
        }

        if ($consentId !== null) {
            $headers['X-Open-Finance-Test-Consent'] = $consentId;
        }

        if ($accountIds !== []) {
            $headers['X-Open-Finance-Test-Accounts'] = implode(',', $accountIds);
        }

        if ($loggedUserDocument !== null) {
            $headers['X-Open-Finance-Test-Document'] = $loggedUserDocument;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public static function writeHeaders(
        ?string $clientId = 'test-client',
        ?string $consentId = null,
        ?array $scopes = null,
        array $accountIds = [],
        ?string $loggedUserDocument = null,
    ): array {
        $headers = self::authorizationHeader($clientId, $consentId, $scopes, $accountIds, $loggedUserDocument);

        if (config('open_finance.fapi.enabled')) {
            $headers['x-idempotency-key'] = (string) \Illuminate\Support\Str::uuid();
        }

        return $headers;
    }
}
