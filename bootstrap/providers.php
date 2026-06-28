<?php

use App\Providers\AppServiceProvider;
use App\Providers\OpenFinanceServiceProvider;
use App\Providers\WalletServiceProvider;

return [
    AppServiceProvider::class,
    OpenFinanceServiceProvider::class,
    WalletServiceProvider::class,
];
