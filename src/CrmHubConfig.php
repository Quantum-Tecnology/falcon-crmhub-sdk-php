<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub;

/**
 * Configuração imutável do cliente.
 *
 * Dois modos de autenticação, como no SDK do DataHub:
 *   - `make($baseUrl, $token)`: chave de API criada no painel do Falcon Vendas.
 *     É o modo normal para servidor-a-servidor.
 *   - `withCredentials($baseUrl, $email, $password)`: login do dono da conta,
 *     com o token cacheado e renovado sozinho.
 */
final class CrmHubConfig
{
    private string $baseUrl;
    private ?string $token;
    private ?string $email;
    private ?string $password;
    private int $timeout;
    private int $connectTimeout;
    private int $retries;
    private int $retryDelay;
    /** @var object|null PSR-16 CacheInterface */
    private ?object $cache;
    /** @var object|null PSR-3 LoggerInterface */
    private ?object $logger;

    /**
     * @param int $timeout Segundos até desistir da resposta.
     *
     *                     30s cobre com folga os endpoints deste SDK: o disparo
     *                     de mensagem responde assim que ENFILEIRA (o envio real
     *                     acontece depois, na fila do Falcon Vendas), e o envio
     *                     do código de verificação é síncrono mas curto.
     */
    public function __construct(
        string $baseUrl,
        ?string $token = null,
        ?string $email = null,
        ?string $password = null,
        int $timeout = 30,
        int $connectTimeout = 10,
        int $retries = 3,
        int $retryDelay = 2000,
        ?object $cache = null,
        ?object $logger = null,
    ) {
        $this->baseUrl        = rtrim($baseUrl, '/');
        $this->token          = $token;
        $this->email          = $email;
        $this->password       = $password;
        $this->timeout        = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->retries        = $retries;
        $this->retryDelay     = $retryDelay;
        $this->cache          = $cache;
        $this->logger         = $logger;
    }

    /** Chave de API (fvx_...) criada no painel. */
    public static function make(string $baseUrl, ?string $token = null): self
    {
        return new self(baseUrl: $baseUrl, token: $token);
    }

    public static function withCredentials(string $baseUrl, string $email, string $password): self
    {
        return new self(baseUrl: $baseUrl, email: $email, password: $password);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    public function getCache(): ?object
    {
        return $this->cache;
    }

    public function getLogger(): ?object
    {
        return $this->logger;
    }

    public function hasCredentials(): bool
    {
        return null !== $this->email && null !== $this->password;
    }

    public function hasToken(): bool
    {
        return null !== $this->token && '' !== $this->token;
    }
}
