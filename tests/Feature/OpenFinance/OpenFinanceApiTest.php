<?php

use App\OpenFinance\Enums\ConsentStatus;

describe('Open Finance Consent API', function () {
    it('creates consent with OF-compliant output', function () {
        $response = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => [
                'permissions' => ['PAYMENTS_INITIATE', 'ACCOUNTS_READ'],
                'loggedUser' => [
                    'document' => ['identification' => '12345678901'],
                ],
            ],
        ]);

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

    it('authorises consent and updates status output', function () {
        $create = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ]);

        $consentId = $create->json('data.consentId');

        $response = $this->postJson("/api/open-banking/consents/v3/consents/{$consentId}/authorise");

        $response->assertOk()
            ->assertJsonPath('data.status', ConsentStatus::Authorised->value);
    });
});

describe('Open Finance Accounts API', function () {
    it('creates account and returns balance output', function () {
        $create = $this->postJson('/api/open-banking/accounts/v2/accounts', [
            'data' => ['accountType' => 'PERSONAL'],
        ]);

        $create->assertCreated();
        $accountId = $create->json('data.accountId');

        $balance = $this->getJson("/api/open-banking/accounts/v2/accounts/{$accountId}/balances");

        $balance->assertOk()
            ->assertJsonPath('data.availableAmount.currency', 'BRL')
            ->assertJsonPath('data.availableAmount.amount', '0.00');
    });
});

describe('Open Finance PIX API', function () {
    it('rejects payment when consent is not authorised', function () {
        $consent = $this->postJson('/api/open-banking/consents/v3/consents', [
            'data' => ['permissions' => ['PAYMENTS_INITIATE']],
        ]);

        $response = $this->postJson('/api/open-banking/payments/v5/pix/payments', [
            'data' => [
                'consentId' => $consent->json('data.consentId'),
                'localInstrument' => 'DICT',
                'payment' => ['amount' => '10.00', 'currency' => 'BRL'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => [['code', 'title', 'detail']]]);
    });
});
