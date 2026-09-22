<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Resources\Messages;

use QuantumTecnology\FalconCrmHub\Resources\AbstractResource;
use QuantumTecnology\FalconCrmHub\Response\ApiResponse;

/**
 * Disparo de mensagem transacional pelo WhatsApp do dono da conta.
 *
 * ⚠️ A mensagem sai do número DELE. Se o seu sistema disparar o que as pessoas
 * não pediram, é o WhatsApp dele que é banido — e ele perde o próprio negócio,
 * não você. Mande o que o destinatário espera receber (ele se cadastrou,
 * comprou, tem uma fatura vencendo), e respeite quem pediu para sair: o serviço
 * devolve OptedOutException, que NÃO deve virar retry.
 *
 * A pessoa vira contato de verdade no Falcon Vendas e pode responder — a
 * conversa cai na caixa de entrada do dono da conta.
 */
final class MessageResource extends AbstractResource
{
    /**
     * Dispara usando um MODELO cadastrado no painel.
     *
     * É o caminho normal. O dono da conta escreve o texto uma vez, com
     * `{placeholders}`, e você manda só a chave e os valores — assim ele corrige
     * uma vírgula sem depender de deploy seu, e vê no painel o que sai em nome
     * dele.
     *
     * Variável faltando é recusada ANTES do envio: placeholder cru numa mensagem
     * já entregue não tem volta.
     *
     * @param array<string, scalar|null> $variables valores dos {placeholders} do modelo
     * @param string|null                $name      nome do contato, quando ele ainda não existe
     *
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException cota do mês esgotada
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\OptedOutException      pediu para não receber
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\ValidationException    modelo ou variável faltando
     */
    public function send(string $phone, string $template, array $variables = [], ?string $name = null): ApiResponse
    {
        $payload = [
            'phone'     => $phone,
            'template'  => $template,
            'variables' => $variables,
        ];

        if (null !== $name) {
            $payload['name'] = $name;
        }

        return $this->post('integration/v1/messages', $payload);
    }

    /**
     * Dispara texto livre, sem modelo.
     *
     * Exige que a chave de API tenha a permissão E que o plano do dono da conta
     * inclua texto livre — senão vem ValidationException com code
     * FREE_TEXT_NOT_ALLOWED. As duas travas existem porque aqui o controle do
     * que sai em nome dele passa a ser SEU.
     *
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException cota do mês esgotada
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\OptedOutException      pediu para não receber
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\ValidationException    texto livre não liberado
     */
    public function sendText(string $phone, string $body, ?string $name = null): ApiResponse
    {
        $payload = ['phone' => $phone, 'body' => $body];

        if (null !== $name) {
            $payload['name'] = $name;
        }

        return $this->post('integration/v1/messages', $payload);
    }
}
