<?php

namespace App\OpenFinance\Http;

use Illuminate\Http\JsonResponse;

final class OpenFinanceResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    public static function data(array $data, int $status = 200, ?array $meta = null): JsonResponse
    {
        $body = ['data' => $data];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public static function errors(array $errors, int $status = 422): JsonResponse
    {
        return response()->json(['errors' => $errors], $status);
    }

    public static function accepted(string $correlationId, string $status = 'processing'): JsonResponse
    {
        return response()->json([
            'data' => [
                'correlationId' => $correlationId,
                'status' => $status,
            ],
        ], 202);
    }
}
