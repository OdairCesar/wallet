<?php

namespace App\OpenFinance\Http;

use App\OpenFinance\Exceptions\OpenFinanceAuthException;
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

    public static function singleError(string $code, string $title, string $detail, int $status): JsonResponse
    {
        return self::errors([
            [
                'code' => $code,
                'title' => $title,
                'detail' => $detail,
            ],
        ], $status);
    }

    public static function notFound(string $code, string $title, string $detail): JsonResponse
    {
        return self::singleError($code, $title, $detail, 404);
    }

    public static function fromAuthException(OpenFinanceAuthException $e): JsonResponse
    {
        return self::singleError(
            $e->errorCode,
            $e->status === 403 ? 'Proibido' : 'Não autorizado',
            $e->getMessage(),
            $e->status,
        );
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
