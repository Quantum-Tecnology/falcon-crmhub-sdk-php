<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Resources\Usage;

use QuantumTecnology\FalconCrmHub\Resources\AbstractResource;
use QuantumTecnology\FalconCrmHub\Response\ApiResponse;

/**
 * Consumo do mês corrente.
 *
 * É o que permite ao seu sistema ter a própria tela de "quanto estou gastando
 * do Falcon Vendas", sem inventar contagem paralela: a fonte da verdade é quem
 * presta o serviço.
 *
 * Cada item traz `feature`, `label`, `description`, `enabled`, `used`, `limit` e
 * `unlimited`. O texto vem pronto do servidor para a sua tela não precisar
 * traduzir nome de produto.
 *
 * ⚠️ `limit = -1` significa ILIMITADO, não zero. Essa é a convenção do Falcon
 * Vendas, e ela difere da do DataHub — se o seu sistema consome os dois, trate a
 * conversão num lugar só. Use `unlimited` e evite comparar o número cru.
 *
 * Consultar não consome cota.
 */
final class UsageResource extends AbstractResource
{
    public function current(): ApiResponse
    {
        return $this->get('integration/v1/usage');
    }
}
