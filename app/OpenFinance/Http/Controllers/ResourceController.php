<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\Projections\Models\WalletAccount;
use Illuminate\Http\JsonResponse;

final class ResourceController
{
    public function index(): JsonResponse
    {
        $accounts = WalletAccount::query()->get(['id', 'account_type', 'status']);

        return OpenFinanceResponse::data(
            $accounts->map(fn (WalletAccount $a) => [
                'resourceId' => $a->id,
                'type' => 'ACCOUNT',
                'status' => $a->status === 'ACTIVE' ? 'AVAILABLE' : 'UNAVAILABLE',
            ])->values()->all(),
        );
    }
}
