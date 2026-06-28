<?php

namespace App\OpenFinance\Security;

use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RsaSha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;

final class FapiJwtValidator
{
    public function __construct(
        private readonly JwksClient $jwksClient,
    ) {}

    public function validate(string $token): OpenFinanceContext
    {
        $jwksUri = config('open_finance.jwt.jwks_uri');
        $secret = config('open_finance.jwt.secret');

        if ($jwksUri !== null && $jwksUri !== '') {
            $parsed = $this->parseToken($token);
            $kid = $parsed->headers()->get('kid');
            $key = $this->jwksClient->resolveKey($jwksUri, is_string($kid) ? $kid : null);
            $config = Configuration::forAsymmetricSigner(
                new RsaSha256,
                InMemory::empty(),
                InMemory::plainText($key['pem']),
            );
            $this->assertValid($config, $parsed);
        } elseif ($secret !== null && $secret !== '') {
            $config = Configuration::forSymmetricSigner(
                new Sha256,
                InMemory::plainText($secret),
            );
            $parsed = $this->parseAndAssert($config, $token);
        } else {
            throw new OpenFinanceAuthException(
                'UNAUTHORIZED',
                'JWT não configurado (OF_JWT_SECRET ou OF_JWT_JWKS_URI).',
            );
        }

        return $this->buildContext($parsed);
    }

    private function parseToken(string $token): Plain
    {
        return $this->parseJwt($token, Configuration::forUnsecuredSigner());
    }

    private function parseAndAssert(Configuration $config, string $token): Plain
    {
        $parsed = $this->parseJwt($token, $config);
        $this->assertValid($config, $parsed);

        return $parsed;
    }

    private function parseJwt(string $token, Configuration $config): Plain
    {
        try {
            /** @var Plain $parsed */
            $parsed = $config->parser()->parse($token);

            return $parsed;
        } catch (\Throwable) {
            throw new OpenFinanceAuthException('UNAUTHORIZED', 'Token JWT inválido ou expirado.');
        }
    }

    private function assertValid(Configuration $config, Plain $parsed): void
    {
        $clock = new SystemClock(new \DateTimeZone('UTC'));

        $config->setValidationConstraints(
            new SignedWith($config->signer(), $config->verificationKey()),
            new ValidAt($clock, new \DateInterval('PT'.config('open_finance.jwt.clock_skew_seconds').'S')),
        );

        try {
            $config->validator()->assert($parsed, ...$config->validationConstraints());
        } catch (\Throwable) {
            throw new OpenFinanceAuthException('UNAUTHORIZED', 'Token JWT inválido ou expirado.');
        }
    }

    private function buildContext(Plain $parsed): OpenFinanceContext
    {
        $issuer = config('open_finance.jwt.issuer');
        $audience = config('open_finance.jwt.audience');

        if ($issuer !== '' && $parsed->claims()->get('iss') !== $issuer) {
            throw new OpenFinanceAuthException('UNAUTHORIZED', 'Issuer do token inválido.');
        }

        if ($audience !== '') {
            $tokenAud = $parsed->claims()->get('aud');
            $audiences = is_array($tokenAud) ? $tokenAud : [$tokenAud];
            if (! in_array($audience, $audiences, true)) {
                throw new OpenFinanceAuthException('UNAUTHORIZED', 'Audience do token inválido.');
            }
        }

        $clientId = (string) ($parsed->claims()->get('client_id') ?? $parsed->claims()->get('azp') ?? '');
        if ($clientId === '') {
            throw new OpenFinanceAuthException('UNAUTHORIZED', 'client_id ausente no token.');
        }

        $scopeClaim = $parsed->claims()->get('scope', '');
        $scopes = is_array($scopeClaim)
            ? $scopeClaim
            : array_filter(explode(' ', (string) $scopeClaim));

        $consentId = $parsed->claims()->has('consent_id')
            ? (string) $parsed->claims()->get('consent_id')
            : null;

        $loggedUserDocument = $parsed->claims()->has('logged_user_document')
            ? (string) $parsed->claims()->get('logged_user_document')
            : null;

        $accountIds = [];
        if ($parsed->claims()->has('account_ids')) {
            $raw = $parsed->claims()->get('account_ids');
            $accountIds = is_array($raw) ? $raw : [];
        }

        return new OpenFinanceContext(
            clientId: $clientId,
            scopes: $scopes,
            consentId: $consentId,
            loggedUserDocument: $loggedUserDocument,
            organisationId: config('open_finance.organisation_id'),
            accountIds: $accountIds,
        );
    }
}
