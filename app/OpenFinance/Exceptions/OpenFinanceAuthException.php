<?php

namespace App\OpenFinance\Exceptions;

use Exception;

final class OpenFinanceAuthException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 401,
    ) {
        parent::__construct($message);
    }
}
