<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Http\Requests\StorePixPaymentRequest;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Resources\PixPaymentResource;
use App\Payments\Services\PixPaymentService;
use App\Projections\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;

final class PixPaymentController
{
    public function __construct(
        private readonly PixPaymentService $payments,
    ) {}

    /**
     * Iniciar pagamento PIX v5.
     */
    public function store(StorePixPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $amountCents = (int) round(((float) $validated['data']['payment']['amount']) * 100);

        try {
            $result = $this->payments->initiate($validated['data']['consentId'], [
                'amountCents' => $amountCents,
                'localInstrument' => $validated['data']['localInstrument'] ?? 'DICT',
                'accountId' => $validated['data']['creditorAccount']['accountId'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return OpenFinanceResponse::errors([
                [
                    'code' => $e->getMessage(),
                    'title' => 'Pagamento rejeitado',
                    'detail' => 'Validação de consentimento ou pagamento falhou.',
                ],
            ], 422);
        }

        $payment = PaymentIntent::query()->where('payment_id', $result['paymentId'])->firstOrFail();

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment), 201);
    }

    public function show(string $paymentId): JsonResponse
    {
        $payment = PaymentIntent::query()->where('payment_id', $paymentId)->first();

        if ($payment === null) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'PAGAMENTO_NAO_ENCONTRADO',
                    'title' => 'Pagamento não encontrado',
                    'detail' => 'O paymentId informado não existe.',
                ],
            ], 404);
        }

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment));
    }

    public function cancel(string $paymentId): JsonResponse
    {
        try {
            $this->payments->cancel($paymentId);
        } catch (\InvalidArgumentException) {
            return OpenFinanceResponse::errors([
                [
                    'code' => 'PAGAMENTO_NAO_ENCONTRADO',
                    'title' => 'Pagamento não encontrado',
                    'detail' => 'O paymentId informado não existe.',
                ],
            ], 404);
        }

        $payment = PaymentIntent::query()->where('payment_id', $paymentId)->firstOrFail();

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment));
    }
}
