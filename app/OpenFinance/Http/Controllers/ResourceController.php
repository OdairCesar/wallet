<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Security\OpenFinanceAuthorizationService;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use App\Projections\Models\WalletAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResourceController
{
    public function __construct(
        private readonly OpenFinanceAuthorizationService $authorization,
        private readonly OpenFinanceContextResolver $contextResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ResourcesRead);
        $accounts = $this->authorization->accountsQueryForContext($context)->get(['id', 'account_type', 'status']);

        return OpenFinanceResponse::data(
            $accounts->map(fn (WalletAccount $a) => [
                'resourceId' => $a->id,
                'type' => 'ACCOUNT',
                'status' => $a->status === 'ACTIVE' ? 'AVAILABLE' : 'UNAVAILABLE',
            ])->values()->all(),
        );
    }
}
