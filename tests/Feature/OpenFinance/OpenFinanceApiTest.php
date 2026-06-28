<?php

use App\OpenFinance\Enums\ConsentStatus;
use App\Projections\Models\ConsentAccount;
use App\Wallet\Services\WalletCommandService;
use Tests\Support\OpenFinanceTestToken;

describe('Open Finance Consent API', function () {
    it('creates consent with OF-compliant output', function () {
        $response = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE', 'ACCOUNTS_READ'],
                'loggedUser' => [
                    'document' => ['identification' => '52998224725'],
                ],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'consentId',
                    'creationDateTime',
                    'status',
                    'permissions',
                ],
            ])
            ->assertJsonPath('data.status', ConsentStatus::AwaitingAuthorisation->value);

        expect($response->headers->get('x-fapi-interaction-id'))->not->toBeEmpty();
    });

    it('authorises consent via API with logged user document in token', function () {
        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => [
                    'document' => ['identification' => '52998224725'],
                ],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $consentId = $create->json('data.consentId');

        $account = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $accountId = $account->json('data.accountId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                consentId: $consentId,
                accountIds: [$accountId],
                loggedUserDocument: '52998224725',
            ),
        )->assertOk()
            ->assertJsonPath('data.status', ConsentStatus::Authorised->value);

        expect(ConsentAccount::query()->where('consent_id', $consentId)->where('account_id', $accountId)->exists())->toBeTrue();

        $response = $this->getJson(
            "/api/open-banking/consents/v3/consents/{$consentId}",
            OpenFinanceTestToken::authorizationHeader(consentId: $consentId, accountIds: [$accountId]),
        );

        $response->assertOk()
            ->assertJsonPath('data.status', ConsentStatus::Authorised->value);
    });
});

describe('Open Finance Accounts API', function () {
    it('creates account and returns balance output', function () {
        $create = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader());

        $create->assertCreated();
        $accountId = $create->json('data.accountId');

        $balance = $this->getJson(
            "/api/open-banking/accounts/v2/accounts/{$accountId}/balances",
            OpenFinanceTestToken::authorizationHeader(accountIds: [$accountId]),
        );

        $balance->assertOk()
            ->assertJsonPath('data.availableAmount.currency', 'BRL')
            ->assertJsonPath('data.availableAmount.amount', '0.00');
    });
});

describe('Open Finance PIX API', function () {
    it('rejects payment when consent is not authorised', function () {
        $consent = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ], OpenFinanceTestToken::authorizationHeader());

        $consentId = $consent->json('data.consentId');

        $debtor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $creditor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $response = $this->postJson('/api/open-banking/payments/v5/pix/payments', [
            'data' => [
                'consentId' => $consentId,
                'localInstrument' => 'DICT',
                'payment' => ['amount' => '10.00', 'currency' => 'BRL'],
                'creditorAccount' => ['accountId' => $creditor->json('data.accountId')],
                'debtorAccount' => ['accountId' => $debtor->json('data.accountId')],
            ],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => [['code', 'title', 'detail']]]);
    });

    it('completes pix payment with balanced transfer when consent is authorised', function () {
        $consentResponse = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $consentId = $consentResponse->json('data.consentId');

        $debtor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $creditor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $debtorId = $debtor->json('data.accountId');
        $creditorId = $creditor->json('data.accountId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                consentId: $consentId,
                accountIds: [$debtorId, $creditorId],
                loggedUserDocument: '52998224725',
            ),
        )->assertOk();

        app(WalletCommandService::class)->deposit($debtorId, 10000, 'test-seed');

        $response = $this->postJson('/api/open-banking/payments/v5/pix/payments', [
            'data' => [
                'consentId' => $consentId,
                'localInstrument' => 'DICT',
                'payment' => ['amount' => '10.00', 'currency' => 'BRL'],
                'creditorAccount' => ['accountId' => $creditorId],
                'debtorAccount' => ['accountId' => $debtorId],
            ],
        ], OpenFinanceTestToken::authorizationHeader(
            consentId: $consentId,
            accountIds: [$debtorId, $creditorId],
        ));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ACSC');
    });

    it('returns 201 with rejected status when payment is blocked by fraud rules', function () {
        config(['open_finance.fraud.max_amount_cents' => 100]);

        $consentResponse = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE'],
                'loggedUser' => ['document' => ['identification' => '52998224725']],
            ],
        ], OpenFinanceTestToken::authorizationHeader());

        $consentId = $consentResponse->json('data.consentId');

        $debtor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $creditor = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ], OpenFinanceTestToken::authorizationHeader(consentId: $consentId));

        $debtorId = $debtor->json('data.accountId');
        $creditorId = $creditor->json('data.accountId');

        $this->postJson(
            "/api/open-banking/consents/v3/consents/{$consentId}/authorise",
            [],
            OpenFinanceTestToken::writeHeaders(
                consentId: $consentId,
                accountIds: [$debtorId, $creditorId],
                loggedUserDocument: '52998224725',
            ),
        )->assertOk();

        app(WalletCommandService::class)->deposit($debtorId, 10000, 'test-seed');

        $response = $this->postJson('/api/open-banking/payments/v5/pix/payments', [
            'data' => [
                'consentId' => $consentId,
                'localInstrument' => 'DICT',
                'payment' => ['amount' => '10.00', 'currency' => 'BRL'],
                'creditorAccount' => ['accountId' => $creditorId],
                'debtorAccount' => ['accountId' => $debtorId],
            ],
        ], OpenFinanceTestToken::authorizationHeader(
            consentId: $consentId,
            accountIds: [$debtorId, $creditorId],
        ));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'RJCT');
    });
});
