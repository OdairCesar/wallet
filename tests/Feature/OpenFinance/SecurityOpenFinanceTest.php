<?php

use App\OpenFinance\Enums\OpenFinanceScope;
use Tests\Support\OpenFinanceTestToken;

describe('Open Finance Security', function () {
    it('returns 401 when FAPI is enabled without token', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $this->getJson('/api/open-banking/accounts/v2/accounts')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'UNAUTHORIZED');
    });

    it('returns 401 when FAPI is enabled with invalid jwt', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $this->getJson('/api/open-banking/accounts/v2/accounts', [
            'Authorization' => 'Bearer not-a-valid-jwt',
        ])->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'UNAUTHORIZED');
    });

    it('returns 403 when token lacks required scope', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $this->getJson(
            '/api/open-banking/accounts/v2/accounts',
            OpenFinanceTestToken::authorizationHeader(scopes: [OpenFinanceScope::ConsentsRead->value]),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });

    it('rejects consent authorise without logged user document in token', function () {
        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ], OpenFinanceTestToken::writeHeaders());

        $create->assertCreated();
        $consentId = $create->json('data.consentId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(consentId: $consentId),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });

    it('rejects invalid fapi interaction id', function () {
        $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ], array_merge(
            OpenFinanceTestToken::writeHeaders(),
            ['x-fapi-interaction-id' => 'not-a-uuid'],
        ))->assertStatus(400)
            ->assertJsonPath('errors.0.code', 'INVALID_FAPI_HEADER');
    });

    it('blocks access to consent of another client when FAPI is enabled', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'client-a'));

        $create->assertCreated();
        $consentId = $create->json('data.consentId');

        $this->getJson(
            "/api/open-banking/consents/v3/consents/{$consentId}",
            OpenFinanceTestToken::authorizationHeader(clientId: 'client-b'),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });

    it('blocks pix with debtor account outside consent when FAPI is enabled', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $consentResponse = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'pix-client'));

        $consentResponse->assertCreated();
        $consentId = $consentResponse->json('data.consentId');

        $debtor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'pix-client', consentId: $consentId));

        $creditor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'pix-client', consentId: $consentId));

        $outside = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'other-client'));

        $debtorId = $debtor->json('data.accountId');
        $creditorId = $creditor->json('data.accountId');
        $outsideId = $outside->json('data.accountId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                clientId: 'pix-client',
                consentId: $consentId,
                accountIds: [$debtorId, $creditorId],
                loggedUserDocument: '52998224725',
            ),
        )->assertOk();

        $this->postJson('/api/open-banking/payments/v5/pix/payments', [
            'data' => [
                'consentId' => $consentId,
                'localInstrument' => 'DICT',
                'payment' => ['amount' => '10.00', 'currency' => 'BRL'],
                'creditorAccount' => ['accountId' => $creditorId],
                'debtorAccount' => ['accountId' => $outsideId],
            ],
        ], OpenFinanceTestToken::writeHeaders(
            clientId: 'pix-client',
            consentId: $consentId,
            accountIds: [$debtorId, $creditorId],
        ))->assertForbidden();
    });

    it('denies consent authorise API when document does not match consent', function () {
        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $create->assertCreated();
        $consentId = $create->json('data.consentId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                consentId: $consentId,
                loggedUserDocument: '39053344705',
            ),
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'CONSENTIMENTO_INVALIDO');
    });

    it('rejects consent authorise when no accounts are linked', function () {
        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $create->assertCreated();
        $consentId = $create->json('data.consentId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                consentId: $consentId,
                loggedUserDocument: '52998224725',
            ),
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'CONSENTIMENTO_INVALIDO');
    });

    it('rejects consent authorise when account_ids in token do not belong to user', function () {
        $victimConsent = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '39053344705']],
            ],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'victim-client'));

        $victimConsent->assertCreated();
        $victimConsentId = $victimConsent->json('data.consentId');

        $victimAccount = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'victim-client', consentId: $victimConsentId));

        $victimAccountId = $victimAccount->json('data.accountId');

        $attackerConsent = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'attacker-client'));

        $attackerConsent->assertCreated();
        $attackerConsentId = $attackerConsent->json('data.consentId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$attackerConsentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                clientId: 'attacker-client',
                consentId: $attackerConsentId,
                accountIds: [$victimAccountId],
                loggedUserDocument: '52998224725',
            ),
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'CONSENTIMENTO_INVALIDO');
    });

    it('blocks transfer without consent in token when FAPI is enabled', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $account = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'transfer-client'));

        $account->assertCreated();
        $accountId = $account->json('data.accountId');

        $other = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::writeHeaders(clientId: 'transfer-client'));

        $otherId = $other->json('data.accountId');

        $this->postJson(
            "/api/open-banking/accounts/v2/accounts/{$accountId}/transfers",
            ['data' => ['toAccountId' => $otherId, 'amount' => '1.00']],
            OpenFinanceTestToken::writeHeaders(
                clientId: 'transfer-client',
                accountIds: [$accountId, $otherId],
            ),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });

    it('blocks operations endpoint when token lacks consents read scope', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $this->getJson(
            '/api/open-banking/operations/v1/operations/00000000-0000-4000-8000-000000000001',
            OpenFinanceTestToken::authorizationHeader(scopes: [OpenFinanceScope::PaymentsRead->value]),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });

    it('blocks access to non-existent consent when FAPI is enabled', function () {
        config(['open_finance.fapi.enabled' => true]);
        config(['open_finance.jwt.secret' => 'test-secret']);

        $this->getJson(
            '/api/open-banking/consents/v3/consents/urn:wallet:consent:non-existent',
            OpenFinanceTestToken::authorizationHeader(),
        )->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    });
})->group('security');
