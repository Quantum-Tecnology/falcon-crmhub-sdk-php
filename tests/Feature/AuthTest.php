<?php

declare(strict_types = 1);

use QuantumTecnology\FalconCrmHub\Auth\TokenManager;
use QuantumTecnology\FalconCrmHub\CrmHubConfig;
use QuantumTecnology\FalconCrmHub\Exceptions\AuthException;
use QuantumTecnology\FalconCrmHub\Resources\Usage\UsageResource;
use QuantumTecnology\FalconCrmHub\Tests\Support\FakeHttpClient;

it('faz login no caminho do Falcon Vendas, não no do DataHub', function (): void {
    $config = CrmHubConfig::withCredentials('https://crm.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);

    $http->queue(200, ['data' => ['access_token' => 'jwt-abc', 'expires_in' => 3600]]);

    expect($tokens->getToken())->toBe('jwt-abc');

    // O caminho difere do DataHub (/auth/v1/login): copiar aquele daria 404
    // travestido de falha de autenticação.
    expect($http->lastCall()['url'])->toBe('https://crm.example.com/user/public/auth/v1/login')
        ->and($http->lastCall()['payload'])->toBe(['email' => 'dono@exemplo.com', 'password' => 'senha']);
});

it('cacheia o token e não faz login duas vezes', function (): void {
    $config = CrmHubConfig::withCredentials('https://crm.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);

    $http->queue(200, ['data' => ['access_token' => 'jwt-abc', 'expires_in' => 3600]]);

    $tokens->getToken();
    $tokens->getToken();

    expect($http->calls)->toHaveCount(1);
});

it('renova o token uma vez ao tomar 401 com credenciais', function (): void {
    $config = CrmHubConfig::withCredentials('https://crm.example.com', 'dono@exemplo.com', 'senha');
    $http   = new FakeHttpClient();
    $tokens = new TokenManager($http, $config);
    $usage  = new UsageResource($http, $tokens, $config);

    $http->queue(200, ['data' => ['access_token' => 'token-velho', 'expires_in' => 3600]]) // login
        ->queue(401, ['message' => 'Chave de API inválida ou revogada.'])                  // 1ª tentativa
        ->queue(200, ['data' => ['access_token' => 'token-novo', 'expires_in' => 3600]])    // relogin
        ->queue(200, ['success' => true, 'data' => ['period' => '2026-09', 'features' => []]]);

    $response = $usage->current();

    expect($response->success)->toBeTrue();

    // A retentativa leva o token NOVO. Sem invalidar o cache, o SDK repetiria
    // a chamada com a mesma credencial recusada e entraria em 401 eterno.
    expect($http->lastCall()['headers']['Authorization'])->toBe('Bearer token-novo');
});

it('não tenta renovar quando o token é fixo', function (): void {
    [$usage, $http] = resourceWithFake(UsageResource::class);

    $http->queue(401, ['message' => 'Chave de API inválida ou revogada.']);

    // Com chave de API não há o que renovar: insistir só geraria uma segunda
    // chamada inútil e mascararia a causa real (chave revogada no painel).
    expect(fn () => $usage->current())->toThrow(AuthException::class);
    expect($http->calls)->toHaveCount(1);
});

it('exige token ou credenciais', function (): void {
    $config = CrmHubConfig::make('https://crm.example.com');
    $tokens = new TokenManager(new FakeHttpClient(), $config);

    expect(fn () => $tokens->getToken())->toThrow(AuthException::class);
});
