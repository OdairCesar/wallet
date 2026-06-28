<?php

namespace App\OpenFinance\Http\Resources;

use App\Projections\Models\WalletAccount;

final class AccountResource
{
    /** @return array<string, mixed> */
    public static function fromModel(WalletAccount $account): array
    {
        return [
            'brandName' => $account->brand_name,
            'companyCnpj' => config('open_finance.organisation_id'),
            'type' => $account->account_type,
            'compeCode' => $account->compe_code,
            'branchCode' => $account->branch_code,
            'number' => $account->account_number,
            'checkDigit' => '0',
            'accountId' => $account->id,
        ];
    }
}
