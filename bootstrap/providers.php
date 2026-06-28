<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\WalletServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    WalletServiceProvider::class,
];
