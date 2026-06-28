<?php

namespace App\OpenFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePixPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data.consentId' => ['required', 'string'],
            'data.localInstrument' => ['nullable', 'string', 'in:MANU,DICT,QRDN,QRES'],
            'data.payment.amount' => ['required', 'numeric', 'min:0.01'],
            'data.payment.currency' => ['nullable', 'string', 'size:3'],
            'data.creditorAccount.accountId' => ['required', 'uuid'],
            'data.debtorAccount.accountId' => ['nullable', 'uuid'],
        ];
    }
}
