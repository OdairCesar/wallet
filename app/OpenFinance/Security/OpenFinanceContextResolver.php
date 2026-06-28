<?php

namespace App\OpenFinance\Security;

use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use Illuminate\Http\Request;

final class OpenFinanceContextResolver
{
    public const ATTRIBUTE_KEY = 'open_finance_context';

    public function fromRequest(Request $request): ?OpenFinanceContext
    {
        /** @var OpenFinanceContext|null $context */
        $context = $request->attributes->get(self::ATTRIBUTE_KEY);

        return $context;
    }

    public function require(Request $request): OpenFinanceContext
    {
        $context = $this->fromRequest($request);

        if ($context === null) {
            throw new OpenFinanceAuthException('UNAUTHORIZED', 'Contexto Open Finance não disponível.');
        }

        return $context;
    }
}
