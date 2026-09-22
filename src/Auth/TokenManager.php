<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Auth;

use QuantumTecnology\FalconCrmHub\CrmHubConfig;
use QuantumTecnology\FalconCrmHub\Exceptions\AuthException;
use QuantumTecnology\FalconCrmHub\Http\HttpClientInterface;

final class TokenManager
{
    /**
     * Chave própria, e não a do SDK do DataHub.
     *
     * Os dois SDKs convivem no mesmo processo — o CrmHub consome o DataHub, e o
     * DataHub consome o CrmHub. Com a mesma chave de cache, o token de um
     * sobrescreveria o do outro e cada chamada levaria a credencial errada, com
     * 401 intermitente e sem nada no log explicando.
     */
    private const CACHE_KEY = 'falcon_crmhub_token';

    private TokenStoreInterface $store;
    private HttpClientInterface $http;
    private CrmHubConfig $config;

    public function __construct(
        HttpClientInterface $http,
        CrmHubConfig $config,
        ?TokenStoreInterface $store = null,
    ) {
        $this->http   = $http;
        $this->config = $config;
        $this->store  = $store ?? new TokenStore();
    }

    public function getToken(): string
    {
        // 1. Direct token from config
        if ($this->config->hasToken()) {
            return (string) $this->config->getToken();
        }

        // 2. Cached token
        $cached = $this->store->get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        // 3. Login with credentials
        return $this->authenticate();
    }

    public function invalidate(): void
    {
        $this->store->delete(self::CACHE_KEY);
    }

    private function authenticate(): string
    {
        if (!$this->config->hasCredentials()) {
            throw new AuthException(
                'No token or credentials configured. Call CrmHubConfig with token or email/password.',
                401,
            );
        }

        // ⚠️ O caminho NÃO é o mesmo do DataHub (/auth/v1/login): no Falcon
        // Vendas o login do dono da conta mora sob `user/public`. Copiar o do
        // outro SDK daria 404 travestido de falha de autenticação.
        $url = $this->config->getBaseUrl() . '/user/public/auth/v1/login';

        $response = $this->http->post($url, [
            'email'    => $this->config->getEmail(),
            'password' => $this->config->getPassword(),
        ]);

        if (!$response->isSuccessful()) {
            $body = $response->json();
            throw new AuthException(
                $body['message'] ?? 'Authentication failed',
                $response->getStatusCode(),
            );
        }

        $body  = $response->json();
        $data  = $body['data'] ?? $body;
        $token = $data['access_token'] ?? $data['token'] ?? null;

        if ($token === null) {
            throw new AuthException('No access_token returned from login endpoint');
        }

        // Cache token for 50 minutes (Sanctum default is 60 min)
        $expiresIn = (int) ($data['expires_in'] ?? 3000);
        $ttl       = max(60, $expiresIn - 600);

        $this->store->set(self::CACHE_KEY, $token, $ttl);

        return $token;
    }
}
