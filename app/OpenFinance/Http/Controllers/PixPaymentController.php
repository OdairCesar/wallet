<?php

namespace App\OpenFinance\Http\Controllers;

use App\OpenFinance\Enums\OpenFinanceScope;
use App\OpenFinance\Exceptions\OpenFinanceAuthException;
use App\OpenFinance\Exceptions\OpenFinanceDomainException;
use App\OpenFinance\Security\OpenFinanceContext;
use App\OpenFinance\Http\OpenFinanceResponse;
use App\OpenFinance\Http\Requests\StorePixPaymentRequest;
use App\OpenFinance\Http\Resources\PixPaymentResource;
use App\OpenFinance\Security\OpenFinanceAuthorizationService;
use App\OpenFinance\Security\OpenFinanceContextResolver;
use App\OpenFinance\Support\Money;
use App\Payments\Services\PixPaymentService;
use App\Projections\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PixPaymentController
{
    public function __construct(
        private readonly PixPaymentService $payments,
        private readonly OpenFinanceAuthorizationService $authorization,
        private readonly OpenFinanceContextResolver $contextResolver,
    ) {}

    public function store(StorePixPaymentRequest $request): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $consentId = $request->input('data.consentId');
        $this->authorization->assertScope($context, OpenFinanceScope::PaymentsInitiate);
        $this->authorization->assertConsentAccess($context, $consentId);
        $this->authorization->assertConsentPermission($consentId, 'PAYMENTS_INITIATE');

        $validated = $request->validated();
        $consentId = $validated['data']['consentId'];
        $creditorId = $validated['data']['creditorAccount']['accountId'] ?? null;
        $debtorId = $validated['data']['debtorAccount']['accountId'] ?? null;

        if ($debtorId !== null) {
            $this->authorization->assertAccountInConsent($consentId, $debtorId);
        }
        if ($creditorId !== null) {
            $this->authorization->assertAccountInConsent($consentId, $creditorId);
        }

        $amountCents = Money::toCents($validated['data']['payment']['amount']);

        $result = $this->payments->initiate($consentId, [
            'amountCents' => $amountCents,
            'localInstrument' => $validated['data']['localInstrument'] ?? 'DICT',
            'creditorAccountId' => $creditorId,
            'debtorAccountId' => $debtorId,
            'clientId' => $context->clientId,
        ]);

        $payment = PaymentIntent::query()->where('payment_id', $result['paymentId'])->firstOrFail();

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment), 201);
    }

    public function show(Request $request, string $paymentId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::PaymentsRead);

        $payment = $this->resolvePayment($context, $paymentId);

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment));
    }

    public function cancel(Request $request, string $paymentId): JsonResponse
    {
        $context = $this->contextResolver->require($request);
        $this->authorization->assertScope($context, OpenFinanceScope::PaymentsInitiate);

        $payment = $this->resolvePayment($context, $paymentId);

        $this->payments->cancel($paymentId);

        $payment = PaymentIntent::query()->where('payment_id', $paymentId)->firstOrFail();

        return OpenFinanceResponse::data(PixPaymentResource::fromModel($payment));
    }

    private function resolvePayment(OpenFinanceContext $context, string $paymentId): PaymentIntent
    {
        $payment = PaymentIntent::query()->where('payment_id', $paymentId)->first();

        if ($payment === null) {
            throw new OpenFinanceDomainException(
                'PAGAMENTO_NAO_ENCONTRADO',
                'O paymentId informado não existe.',
                404,
            );
        }

        if ($context->consentId !== null && $payment->consent_id !== $context->consentId) {
            throw new OpenFinanceAuthException(
                'FORBIDDEN',
                'Pagamento não autorizado para este token.',
                403,
            );
        }

        $this->authorization->assertConsentAccess($context, $payment->consent_id);

        return $payment;
    }
}
