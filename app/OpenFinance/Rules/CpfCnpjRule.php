<?php

namespace App\OpenFinance\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CpfCnpjRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('O documento deve ser uma string.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 11 && $this->isValidCpf($digits)) {
            return;
        }

        if (strlen($digits) === 14 && $this->isValidCnpj($digits)) {
            return;
        }

        $fail('O documento informado não é um CPF ou CNPJ válido.');
    }

    private function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $lengths = [5, 6];
        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        for ($t = 0; $t < 2; $t++) {
            $sum = 0;
            for ($i = 0; $i < 12 + $t; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$t][$i];
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $cnpj[12 + $t] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
