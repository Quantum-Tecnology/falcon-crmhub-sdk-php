<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Resources\Customers;

use QuantumTecnology\FalconCrmHub\Resources\AbstractResource;
use QuantumTecnology\FalconCrmHub\Response\ApiResponse;

/**
 * Registro de clientes no Falcon Vendas.
 *
 * O uso típico é avisar "fulano acabou de se cadastrar no meu sistema": a pessoa
 * vira contato de verdade, com a origem marcada, e pode receber a mensagem de
 * boas-vindas configurada pelo dono da conta.
 */
final class CustomerResource extends AbstractResource
{
    /**
     * Registra a chegada de um cliente novo.
     *
     * Idempotente por telefone: reenviar o mesmo cadastro devolve
     * `data.created = false` e não duplica nem redispara boas-vindas — então
     * você pode chamar sem medo no retry.
     *
     * A ORIGEM do contato não vem daqui: é o nome da chave de API, definido pelo
     * dono da conta no painel. Isso mantém o filtro por origem confiável quando
     * ele tem vários sistemas conectados.
     *
     * Não consome cota.
     */
    public function arrived(string $name, string $phone, ?string $notes = null): ApiResponse
    {
        $payload = ['name' => $name, 'phone' => $phone];

        if (null !== $notes) {
            $payload['notes'] = $notes;
        }

        return $this->post('integration/v1/customers', $payload);
    }
}
