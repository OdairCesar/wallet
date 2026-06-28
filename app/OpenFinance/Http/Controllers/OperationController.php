<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\Projections\Models\Operation;
use Illuminate\Http\JsonResponse;

final class OperationController
{
    public function show(string $correlationId): JsonResponse
    {
        $operation = Operation::query()->where('correlation_id', $correlationId)->first();

        if ($operation === null) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'OPERACAO_NAO_ENCONTRADA',
                    'title' => 'Operação não encontrada',
                    'detail' => 'O correlationId informado não existe.',
                ],
            ], 404);
        }

        return OpenFinanceResponse::data([
            'correlationId' => $operation->correlation_id,
            'status' => $operation->status,
            'operationType' => $operation->operation_type,
            'resourceId' => $operation->resource_id,
        ]);
    }
}
