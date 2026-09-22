<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Exceptions;

use QuantumTecnology\FalconCrmHub\Response\ApiResponse;
use Throwable;

/**
 * O destinatário pediu para não receber mais mensagens (HTTP 409).
 *
 * Não é erro de quem chamou nem falha do serviço: é uma decisão da pessoa, e o
 * sistema integrado deve registrá-la do lado dele para parar de tentar. Tratar
 * isso como falha transitória e ficar reenviando é justamente o comportamento
 * que leva o número do dono da conta a ser banido.
 */
class OptedOutException extends FalconException
{
    public function __construct(
        string $message = 'Recipient opted out of messaging',
        int $code = 409,
        ?Throwable $previous = null,
        ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous, $response);
    }
}
