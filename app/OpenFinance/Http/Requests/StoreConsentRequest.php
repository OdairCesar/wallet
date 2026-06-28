<?php

namespace App\OpenFinance\Http\Requests;

use App\OpenFinance\Rules\CpfCnpjRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsentRequest extends FormRequest
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
            'data.permissions' => ['required', 'array', 'min:1'],
            'data.permissions.*' => ['string'],
            'data.loggedUser.document.identification' => ['nullable', 'string', new CpfCnpjRule],
        ];
    }
}
