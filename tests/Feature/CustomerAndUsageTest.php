<?php

declare(strict_types = 1);

use QuantumTecnology\FalconCrmHub\Resources\Customers\CustomerResource;
use QuantumTecnology\FalconCrmHub\Resources\Usage\UsageResource;

it('registra cliente novo sem mandar a origem', function (): void {
    [$customers, $http] = resourceWithFake(CustomerResource::class);

    $http->queue(201, [
        'success' => true,
        'message' => 'Registro criado com sucesso.',
        'data'    => ['created' => true, 'contact_id' => 'uuid-1', 'phone' => '+5519999999999'],
    ]);

    $response = $customers->arrived('Ana Silva', '19999999999');

    // A origem vem do NOME DA CHAVE, definido pelo dono da conta no painel —
    // o SDK não tem como (nem deve) influenciar isso.
    expect($http->lastCall()['payload'])->toBe(['name' => 'Ana Silva', 'phone' => '19999999999'])
        ->and($http->lastCall()['payload'])->not->toHaveKey('source_system');

    expect($response->data['created'])->toBeTrue();
});

it('sinaliza cliente que já existia sem lançar exceção', function (): void {
    [$customers, $http] = resourceWithFake(CustomerResource::class);

    $http->queue(200, [
        'success' => true,
        'message' => 'Esse cliente já estava cadastrado.',
        'data'    => ['created' => false],
    ]);

    // Idempotência é feature: reenviar no retry não pode explodir.
    $response = $customers->arrived('Ana Silva', '19999999999');

    expect($response->success)->toBeTrue()
        ->and($response->data['created'])->toBeFalse();
});

it('lê o consumo do mês com limite ilimitado sinalizado', function (): void {
    [$usage, $http] = resourceWithFake(UsageResource::class);

    $http->queue(200, [
        'success' => true,
        'data'    => [
            'period'   => '2026-09',
            'features' => [
                ['feature' => 'phone_verify', 'label' => 'Validação de celular', 'used' => 3, 'limit' => 10, 'unlimited' => false],
                ['feature' => 'message_sent', 'label' => 'Mensagem enviada pelo sistema', 'used' => 42, 'limit' => -1, 'unlimited' => true],
            ],
        ],
    ]);

    $response = $usage->current();

    expect($http->lastCall()['method'])->toBe('GET')
        ->and($http->lastCall()['url'])->toBe('https://crm.example.com/integration/v1/usage');

    $features = collect_by_key($response->data['features'], 'feature');

    expect($features['phone_verify']['used'])->toBe(3)
        // -1 é ILIMITADO, não zero — e `unlimited` existe para não depender
        // de quem consome lembrar dessa convenção.
        ->and($features['message_sent']['limit'])->toBe(-1)
        ->and($features['message_sent']['unlimited'])->toBeTrue();
});

/** @param list<array<string, mixed>> $rows */
function collect_by_key(array $rows, string $key): array
{
    $out = [];

    foreach ($rows as $row) {
        $out[$row[$key]] = $row;
    }

    return $out;
}
