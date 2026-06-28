<?php

namespace App\OpenFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferAccountRequest extends FormRequest
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
            'data.amount' => ['required', 'numeric', 'min:0.01'],
            'data.toAccountId' => ['required', 'uuid'],
        ];
    }
}
