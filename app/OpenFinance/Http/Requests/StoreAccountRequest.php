<?php

namespace App\OpenFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
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
            'data.accountType' => ['nullable', 'string', 'in:PERSONAL,BUSINESS,SAVINGS'],
        ];
    }
}
