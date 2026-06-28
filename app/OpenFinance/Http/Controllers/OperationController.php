<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Security\OpenFinanceAuthorizationService;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use App\Projections\Models\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperationController
{
    public function __construct(
        private readonly OpenFinanceAuthorizationService $authorization,
        private readonly OpenFinanceContextResolver $contextResolver,
    ) {}

    public function show(Request $request, string $correlationId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::ConsentsRead);
        $this->authorization->assertOperationAccess($context, $correlationId);

        $operation = Operation::query()->where('correlation_id', $correlationId)->first();

        if ($operation === null) {
            return OpenFinanceResponse::notFound(
                'OPERACAO_NAO_ENCONTRADA',
                'Operação não encontrada',
                'O correlationId informado não existe.',
            );
        }

        return OpenFinanceResponse::data([
            'correlationId' => $operation->correlation_id,
            'status' => $operation->status,
            'operationType' => $operation->operation_type,
            'resourceId' => $operation->resource_id,
        ]);
    }
}
