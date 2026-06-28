<?php

namespace App\Wallet\Enums;

final class WalletEventType
{
    public const AccountCreated = 'wallet.account.created';

    public const MoneyDeposited = 'wallet.money.deposited';

    public const TransferRequested = 'wallet.transfer.requested';

    public const TransferCompleted = 'wallet.transfer.completed';

    public const TransferFailed = 'wallet.transfer.failed';

    public const TransferReversed = 'wallet.transfer.reversed';

    public const FundsReserved = 'wallet.funds.reserved';

    public const FundsReleased = 'wallet.funds.released';
}
