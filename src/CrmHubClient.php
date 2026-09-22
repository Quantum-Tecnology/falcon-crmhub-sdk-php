<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub;

use QuantumTecnology\FalconCrmHub\Auth\CacheTokenStore;
use QuantumTecnology\FalconCrmHub\Auth\TokenManager;
use QuantumTecnology\FalconCrmHub\Auth\TokenStore;
use QuantumTecnology\FalconCrmHub\Http\CurlHttpClient;
use QuantumTecnology\FalconCrmHub\Http\HttpClientInterface;
use QuantumTecnology\FalconCrmHub\Resources\Customers\CustomerResource;
use QuantumTecnology\FalconCrmHub\Resources\Messages\MessageResource;
use QuantumTecnology\FalconCrmHub\Resources\Phones\PhoneResource;
use QuantumTecnology\FalconCrmHub\Resources\Usage\UsageResource;

/**
 * Cliente da API do Falcon Vendas (CrmHub).
 *
 * ```php
 * $crm = new CrmHubClient(CrmHubConfig::make(
 *     'https://crm.falcon-server.com.br',
 *     'fvx_abc12345_...',            // chave criada no painel
 * ));
 *
 * $crm->phones()->challenge('+5519999999999');
 * $crm->messages()->send('+5519999999999', 'assinatura_vencida', ['nome' => 'Ana']);
 * ```
 */
final class CrmHubClient
{
    private HttpClientInterface $http;
    private TokenManager $tokenManager;
    private CrmHubConfig $config;

    public function __construct(CrmHubConfig $config)
    {
        $this->config = $config;
        $this->http   = new CurlHttpClient($config);

        $tokenStore = null !== $config->getCache()
            ? new CacheTokenStore($config->getCache())
            : new TokenStore();

        $this->tokenManager = new TokenManager($this->http, $config, $tokenStore);
    }

    /** Registro de clientes novos. */
    public function customers(): CustomerResource
    {
        return new CustomerResource($this->http, $this->tokenManager, $this->config);
    }

    /** Validação de celular por WhatsApp (código de 6 dígitos). */
    public function phones(): PhoneResource
    {
        return new PhoneResource($this->http, $this->tokenManager, $this->config);
    }

    /** Disparo de mensagem transacional. */
    public function messages(): MessageResource
    {
        return new MessageResource($this->http, $this->tokenManager, $this->config);
    }

    /** Consumo do mês corrente. */
    public function usage(): UsageResource
    {
        return new UsageResource($this->http, $this->tokenManager, $this->config);
    }

    public function getConfig(): CrmHubConfig
    {
        return $this->config;
    }
}
