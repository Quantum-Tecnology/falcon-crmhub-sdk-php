<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Resources\Phones;

use QuantumTecnology\FalconCrmHub\Resources\AbstractResource;
use QuantumTecnology\FalconCrmHub\Response\ApiResponse;

/**
 * Validação de celular por WhatsApp.
 *
 * Fluxo de dois passos: `challenge()` manda um código de 6 dígitos pelo WhatsApp
 * do dono da conta, e `confirm()` confere o que a pessoa digitou.
 *
 * O número pode vir com máscara, com ou sem DDI — a normalização (incluindo a
 * pegadinha do 9º dígito brasileiro) acontece do lado do servidor, e a resposta
 * do challenge traz o número já em E.164 para o integrado guardar.
 */
final class PhoneResource extends AbstractResource
{
    /**
     * Envia o código.
     *
     * Consome uma unidade da cota de validação — MAS só quando a mensagem
     * realmente sai: número que não tem WhatsApp é recusado antes do envio
     * (ValidationException com code NOT_ON_WHATSAPP) e não custa nada.
     *
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException cota do mês esgotada
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\ValidationException    número inválido ou sem WhatsApp
     */
    public function challenge(string $phone): ApiResponse
    {
        return $this->post('integration/v1/phones/challenge', ['phone' => $phone]);
    }

    /**
     * Confere o código digitado. Não consome cota: cobrar aqui seria cobrar o
     * cliente por ele ter digitado o que recebeu.
     *
     * O código vale uma vez só e expira em 10 minutos.
     *
     * @throws \QuantumTecnology\FalconCrmHub\Exceptions\ValidationException código errado ou expirado
     */
    public function confirm(string $phone, string $code): ApiResponse
    {
        return $this->post('integration/v1/phones/confirm', [
            'phone' => $phone,
            'code'  => $code,
        ]);
    }
}
