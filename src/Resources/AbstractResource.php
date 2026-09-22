<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Resources;

use QuantumTecnology\FalconCrmHub\Auth\TokenManager;
use QuantumTecnology\FalconCrmHub\CrmHubConfig;
use QuantumTecnology\FalconCrmHub\Exceptions\AuthException;
use QuantumTecnology\FalconCrmHub\Exceptions\ForbiddenException;
use QuantumTecnology\FalconCrmHub\Exceptions\NotFoundException;
use QuantumTecnology\FalconCrmHub\Exceptions\OptedOutException;
use QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException;
use QuantumTecnology\FalconCrmHub\Exceptions\RateLimitException;
use QuantumTecnology\FalconCrmHub\Exceptions\ServerException;
use QuantumTecnology\FalconCrmHub\Exceptions\ValidationException;
use QuantumTecnology\FalconCrmHub\Http\HttpClientInterface;
use QuantumTecnology\FalconCrmHub\Http\HttpResponse;
use QuantumTecnology\FalconCrmHub\Response\ApiResponse;

abstract class AbstractResource
{
    protected HttpClientInterface $http;
    protected TokenManager $tokenManager;
    protected CrmHubConfig $config;

    public function __construct(
        HttpClientInterface $http,
        TokenManager $tokenManager,
        CrmHubConfig $config,
    ) {
        $this->http         = $http;
        $this->tokenManager = $tokenManager;
        $this->config       = $config;
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function get(string $path, array $query = []): ApiResponse
    {
        return $this->requestWithAuth('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function post(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('POST', $path, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function put(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('PUT', $path, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function delete(string $path, array $data = []): ApiResponse
    {
        return $this->requestWithAuth('DELETE', $path, $data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestWithAuth(string $method, string $path, array $payload = []): ApiResponse
    {
        $url     = $this->buildUrl($path);
        $headers = $this->authHeaders();

        $response = $this->doRequest($method, $url, $payload, $headers);

        // Auto-retry on 401 (token expired)
        if ($response->getStatusCode() === 401 && $this->config->hasCredentials()) {
            $this->tokenManager->invalidate();
            $headers  = $this->authHeaders();
            $response = $this->doRequest($method, $url, $payload, $headers);
        }

        return $this->handleResponse($response);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function doRequest(string $method, string $url, array $payload, array $headers): HttpResponse
    {
        return match ($method) {
            'GET'    => $this->http->get($url, $payload, $headers),
            'POST'   => $this->http->post($url, $payload, $headers),
            'PUT'    => $this->http->put($url, $payload, $headers),
            'DELETE' => $this->http->delete($url, $payload, $headers),
            default  => $this->http->get($url, $payload, $headers),
        };
    }

    private function handleResponse(HttpResponse $response): ApiResponse
    {
        $apiResponse = ApiResponse::fromHttpResponse($response);
        $statusCode  = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            return $apiResponse;
        }

        $body = $response->json();

        match (true) {
            $statusCode === 401 => throw new AuthException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            /*
             * 402: acabou a cota do mês, o plano não inclui o recurso, ou a
             * assinatura não está ativa. `used`/`limit`/`code` vêm na raiz do
             * envelope e viram propriedades acessíveis — o integrado precisa
             * distinguir "espere o mês virar" de "faça upgrade".
             */
            $statusCode === 402 => throw new QuotaExceededException(
                $apiResponse->message,
                (int) ($body['used'] ?? 0),
                (int) ($body['limit'] ?? 0),
                isset($body['code']) ? (string) $body['code'] : null,
                $statusCode,
                null,
                $apiResponse,
            ),
            /*
             * 403: a chave não tem a permissão da rota (`code: MISSING_ABILITY`,
             * `ability` diz qual). Até a 1.x anterior, 403 caía no `default` e
             * voltava como resposta comum — quem integra seguia achando que a
             * mensagem tinha saído.
             */
            $statusCode === 403 => throw new ForbiddenException(
                $apiResponse->message,
                isset($body['ability']) ? (string) $body['ability'] : null,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 404 => throw new NotFoundException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            /*
             * 409: o destinatário pediu para não receber mais. Exception própria
             * porque tratar isso como falha genérica levaria o integrado a
             * reenviar — que é o caminho mais curto para o número do dono da
             * conta ser banido.
             */
            $statusCode === 409 => throw new OptedOutException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 422 => throw new ValidationException(
                $apiResponse->message,
                $apiResponse->errors,
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode === 429 => throw new RateLimitException(
                $apiResponse->message,
                $response->retryAfter(),
                $statusCode,
                null,
                $apiResponse,
            ),
            $statusCode >= 500 => throw new ServerException(
                $apiResponse->message,
                $statusCode,
                null,
                $apiResponse,
            ),
            default => $apiResponse,
        };

        return $apiResponse;
    }

    private function buildUrl(string $path): string
    {
        return $this->config->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenManager->getToken(),
        ];
    }

    protected function sanitizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? $value;
    }
}
