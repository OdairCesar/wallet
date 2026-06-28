<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Requests\StoreAccountRequest;
use App\OpenFinance\Http\Requests\TransferAccountRequest;
use App\OpenFinance\Http\Resources\AccountResource;
use App\OpenFinance\Http\Resources\BalanceResource;
use App\OpenFinance\Http\Resources\TransactionResource;
use App\OpenFinance\Security\OpenFinanceAuthorizationService;
use App\OpenFinance\Security\OpenFinanceContext;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use App\OpenFinance\Support\Money;
use App\Projections\Models\AccountBalance;
use App\Projections\Models\ConsentAccount;
use App\Projections\Models\WalletAccount;
use App\Projections\Models\WalletTransaction;
use App\Wallet\Enums\AccountType;
use App\Wallet\Services\WalletCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AccountController
{
    public function __construct(
        private readonly WalletCommandService $wallet,
        private readonly OpenFinanceAuthorizationService $authorization,
        private readonly OpenFinanceContextResolver $contextResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::AccountsRead);
        $accounts = $this->authorization->accountsQueryForContext($context)->with('balance')->get();

        return OpenFinanceResponse::data(
            $accounts->map(fn (WalletAccount $a) => AccountResource::fromModel($a))->values()->all(),
        );
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::AccountsWrite);

        $validated = $request->validated();

        $type = AccountType::tryFrom($validated['data']['accountType'] ?? '') ?? AccountType::Personal;
        $correlationId = (string) Str::uuid();

        $accountId = $this->wallet->createAccount(null, $type, $correlationId);
        $account = WalletAccount::query()->findOrFail($accountId);

        if ($context->consentId !== null) {
            ConsentAccount::query()->firstOrCreate([
                'consent_id' => $context->consentId,
                'account_id' => $accountId,
            ]);
        }

        return OpenFinanceResponse::data(AccountResource::fromModel($account), 201);
    }

    public function show(Request $request, string $accountId): JsonResponse
    {
        $this->authorizeAccountRead($request, $accountId);

        $account = WalletAccount::query()->find($accountId);

        if ($account === null) {
            return OpenFinanceResponse::notFound(
                'CONTA_NAO_ENCONTRADA',
                'Conta não encontrada',
                'O accountId informado não existe.',
            );
        }

        return OpenFinanceResponse::data(AccountResource::fromModel($account));
    }

    public function balances(Request $request, string $accountId): JsonResponse
    {
        $this->authorizeAccountRead($request, $accountId);

        $balance = AccountBalance::query()->where('account_id', $accountId)->first();

        if ($balance === null) {
            return OpenFinanceResponse::notFound(
                'CONTA_NAO_ENCONTRADA',
                'Conta não encontrada',
                'O accountId informado não existe.',
            );
        }

        return OpenFinanceResponse::data(BalanceResource::fromModel($balance));
    }

    public function transactions(Request $request, string $accountId): JsonResponse
    {
        $this->authorizeAccountRead($request, $accountId);

        if (! WalletAccount::query()->where('id', $accountId)->exists()) {
            return OpenFinanceResponse::notFound(
                'CONTA_NAO_ENCONTRADA',
                'Conta não encontrada',
                'O accountId informado não existe.',
            );
        }

        $transactions = WalletTransaction::query()
            ->where('account_id', $accountId)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        return OpenFinanceResponse::data(
            $transactions->map(fn (WalletTransaction $t) => TransactionResource::fromModel($t))->values()->all(),
            meta: ['totalRecords' => $transactions->count()],
        );
    }

    public function transfer(TransferAccountRequest $request, string $accountId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::PaymentsInitiate);
        $this->authorization->assertPaymentConsentContext($context);
        $this->authorization->assertAccountAccess($context, $accountId);

        $validated = $request->validated();
        $toAccountId = $validated['data']['toAccountId'];
        $this->authorization->assertAccountAccess($context, $toAccountId);

        $this->authorization->assertAccountInConsent($context->consentId, $accountId);
        $this->authorization->assertAccountInConsent($context->consentId, $toAccountId);
        $this->authorization->assertConsentPermission($context->consentId, 'PAYMENTS_INITIATE');

        $amountCents = Money::toCents($validated['data']['amount']);
        $correlationId = (string) Str::uuid();

        $transferred = $this->wallet->transfer(
            $accountId,
            $toAccountId,
            $amountCents,
            $correlationId,
            $context->clientId,
        );

        if (! $transferred) {
            return OpenFinanceResponse::singleError(
                'TRANSFERENCIA_FALHOU',
                'Transferência não realizada',
                'Saldo insuficiente ou operação bloqueada.',
                422,
            );
        }

        return OpenFinanceResponse::accepted($correlationId);
    }

    private function authorizeAccountRead(Request $request, string $accountId): OpenFinanceContext
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::AccountsRead);
        $this->authorization->assertAccountAccess($context, $accountId);

        return $context;
    }
}
