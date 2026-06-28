<?php

namespace App\OpenFinance\Exceptions;

use InvalidArgumentException;

final class OpenFinanceDomainException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public function title(): string
    {
        if ($this->httpStatus === 404) {
            return 'Recurso não encontrado';
        }

        return match ($this->errorCode) {
            'CONSENTIMENTO_INVALIDO', 'CONSENTIMENTO_NAO_AUTORIZADO', 'CONSENTIMENTO_EXPIRADO' => 'Consentimento inválido',
            'CONTA_NAO_ENCONTRADA', 'CONTA_DEBITO_NAO_ENCONTRADA', 'CONTA_CREDORA_NAO_INFORMADA' => 'Conta não encontrada',
            'PAGAMENTO_NAO_ENCONTRADO' => 'Pagamento não encontrado',
            'SALDO_INSUFICIENTE' => 'Saldo insuficiente',
            'TRANSFERENCIA_FALHOU' => 'Transferência não realizada',
            default => 'Operação inválida',
        };
    }
}
