<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Resources\AccountResource;
use App\OpenFinance\Http\Resources\BalanceResource;
use App\OpenFinance\Http\Resources\TransactionResource;
use App\Projections\Models\AccountBalance;
use App\Projections\Models\WalletAccount;
use App\Projections\Models\WalletTransaction;
use App\OpenFinance\Http\Requests\StoreAccountRequest;
use App\OpenFinance\Http\Requests\TransferAccountRequest;
use App\Wallet\Enums\AccountType;
use App\Wallet\Services\WalletCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class AccountController
{
    public function __construct(
        private readonly WalletCommandService $wallet,
    ) {}

    public function index(): JsonResponse
    {
        $accounts = WalletAccount::query()->with('balance')->get();

        return OpenFinanceResponse::data(
            $accounts->map(fn (WalletAccount $a) => AccountResource::fromModel($a))->values()->all(),
        );
    }

    /**
     * Criar conta na carteira.
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $type = AccountType::tryFrom($validated['data']['accountType'] ?? '') ?? AccountType::Personal;
        $correlationId = (string) Str::uuid();

        $accountId = $this->wallet->createAccount(null, $type, $correlationId);
        $account = WalletAccount::query()->findOrFail($accountId);

        return OpenFinanceResponse::data(AccountResource::fromModel($account), 201);
    }

    public function show(string $accountId): JsonResponse
    {
        $account = WalletAccount::query()->find($accountId);

        if ($account === null) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONTA_NAO_ENCONTRADA',
                    'title' => 'Conta não encontrada',
                    'detail' => 'O accountId informado não existe.',
                ],
            ], 404);
        }

        return OpenFinanceResponse::data(AccountResource::fromModel($account));
    }

    public function balances(string $accountId): JsonResponse
    {
        $balance = AccountBalance::query()->where('account_id', $accountId)->first();

        if ($balance === null) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONTA_NAO_ENCONTRADA',
                    'title' => 'Conta não encontrada',
                    'detail' => 'O accountId informado não existe.',
                ],
            ], 404);
        }

        return OpenFinanceResponse::data(BalanceResource::fromModel($balance));
    }

    public function transactions(string $accountId): JsonResponse
    {
        if (! WalletAccount::query()->where('id', $accountId)->exists()) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'CONTA_NAO_ENCONTRADA',
                    'title' => 'Conta não encontrada',
                    'detail' => 'O accountId informado não existe.',
                ],
            ], 404);
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

    /**
     * Transferência P2P entre contas (assíncrona — retorna 202).
     */
    public function transfer(TransferAccountRequest $request, string $accountId): JsonResponse
    {
        $validated = $request->validated();

        $amountCents = (int) round(((float) $validated['data']['amount']) * 100);
        $correlationId = (string) Str::uuid();

        try {
            $this->wallet->transfer(
                $accountId,
                $validated['data']['toAccountId'],
                $amountCents,
                $correlationId,
            );
        } catch (\InvalidArgumentException $e) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'OPERACAO_INVALIDA',
                    'title' => 'Operação inválida',
                    'detail' => $e->getMessage(),
                ],
            ], 422);
        }

        return OpenFinanceResponse::accepted($correlationId);
    }
}
