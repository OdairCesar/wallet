<?php

namespace App\OpenFinance\Http\Resources;

use App\Projections\Models\PaymentIntent;

final class PixPaymentResource
{
    /** @return array<string, mixed> */
    public static function fromModel(PaymentIntent $payment): array
    {
        return [
            'paymentId' => $payment->payment_id,
            'endToEndId' => $payment->payment_id,
            'consentId' => $payment->consent_id,
            'creationDateTime' => $payment->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'status' => $payment->status,
            'statusUpdateDateTime' => $payment->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'localInstrument' => $payment->local_instrument,
            'payment' => [
                'amount' => number_format($payment->amount_cents / 100, 2, '.', ''),
                'currency' => $payment->currency,
            ],
            'rejectionReason' => $payment->rejection_reason,
        ];
    }
}
