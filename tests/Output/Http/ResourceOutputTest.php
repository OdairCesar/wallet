<?php

use App\OpenFinance\Http\Resources\ConsentResource;
use App\OpenFinance\Http\Resources\AccountResource;
use App\OpenFinance\Http\Resources\BalanceResource;
use App\Projections\Models\AccountBalance;
use App\Projections\Models\Consent;
use App\Projections\Models\WalletAccount;

use Tests\Support\SchemaValidator;

describe('Open Finance HTTP resource outputs', function () {
    it('formats consent response matching fixture structure', function () {
        $consent = new Consent([
            'consent_id' => 'urn:wallet:consent:00000000-0000-4000-8000-000000000001',
            'status' => 'AWAITING_AUTHORISATION',
            'permissions' => ['ACCOUNTS_READ', 'ACCOUNTS_BALANCES_READ'],
            'creation_date_time' => now()->utc(),
            'expiration_date_time' => now()->utc()->addDay(),
        ]);
        $consent->updated_at = now()->utc();

        $output = ['data' => ConsentResource::fromModel($consent)];
        $expected = loadContractFixture('consents/created-awaiting-authorisation.json');

        assertResponseStructure($output, $expected, [
            'creationDateTime',
            'statusUpdateDateTime',
            'expirationDateTime',
            'consentId',
        ]);
        expect($output['data']['permissions'])->toBe($expected['data']['permissions']);
    });

    it('formats account response matching fixture structure', function () {
        $account = new WalletAccount([
            'account_type' => 'PERSONAL',
            'brand_name' => 'Wallet',
            'compe_code' => '001',
            'branch_code' => '0001',
            'account_number' => '12345678',
        ]);
        $account->id = '00000000-0000-4000-8000-000000000002';

        $output = ['data' => AccountResource::fromModel($account)];
        $expected = loadContractFixture('accounts/account-detail.json');

        assertResponseStructure($output, $expected);
    });

    it('formats balance response matching fixture structure', function () {
        $balance = new AccountBalance([
            'account_id' => '00000000-0000-4000-8000-000000000002',
            'available_amount_cents' => 10000,
            'blocked_amount_cents' => 0,
            'currency' => 'BRL',
        ]);

        $output = ['data' => BalanceResource::fromModel($balance)];
        $expected = loadContractFixture('accounts/balance.json');

        assertResponseStructure($output, $expected);
    });
});
