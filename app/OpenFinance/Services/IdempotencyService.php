<?php

namespace App\OpenFinance\Services;

use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Models\IdempotencyKey;
use App\OpenFinance\Security\OpenFinanceContext;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyService
{
    public function resolve(Request $request, Closure $callback): Response
    {
        $key = $request->header('x-idempotency-key');

        if ($key === null || $key === '') {
            return $callback();
        }

        $context = $request->attributes->get('open_finance_context');
        $clientId = $context instanceof OpenFinanceContext ? $context->clientId : 'anonymous';
        $route = $request->path();
        $hash = hash('sha256', $request->getContent());

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $existing = $this->findActiveKey($clientId, $route, $key);

            if ($existing !== null) {
                if ($existing->request_hash !== $hash) {
                    return OpenFinanceResponse::singleError(
                        'IDEMPOTENCY_CONFLICT',
                        'Conflito de idempotência',
                        'A chave foi usada com um corpo de requisição diferente.',
                        409,
                    );
                }

                if ($existing->response_status > 0) {
                    return response()->json(
                        $existing->response_body,
                        $existing->response_status,
                    );
                }
            }

            try {
                DB::transaction(function () use ($clientId, $route, $key, $hash): void {
                    $locked = $this->findActiveKey($clientId, $route, $key, lock: true);

                    if ($locked !== null && $locked->response_status > 0) {
                        return;
                    }

                    if ($locked === null) {
                        IdempotencyKey::query()->create([
                            'client_id' => $clientId,
                            'route' => $route,
                            'key' => $key,
                            'request_hash' => $hash,
                            'response_status' => 0,
                            'response_body' => [],
                            'expires_at' => now()->addHours(config('open_finance.idempotency.ttl_hours')),
                        ]);
                    }
                });

                break;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        $response = $callback();

        DB::transaction(function () use ($clientId, $route, $key, $hash, $response): void {
            IdempotencyKey::query()
                ->where('client_id', $clientId)
                ->where('route', $route)
                ->where('key', $key)
                ->update([
                    'request_hash' => $hash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode($response->getContent(), true) ?? [],
                    'expires_at' => now()->addHours(config('open_finance.idempotency.ttl_hours')),
                ]);
        });

        return $response;
    }

    private function findActiveKey(
        string $clientId,
        string $route,
        string $key,
        bool $lock = false,
    ): ?IdempotencyKey {
        $query = IdempotencyKey::query()
            ->where('client_id', $clientId)
            ->where('route', $route)
            ->where('key', $key)
            ->where('expires_at', '>', now());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
