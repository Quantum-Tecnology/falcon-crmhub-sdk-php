<?php

declare(strict_types = 1);

use QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException;
use QuantumTecnology\FalconCrmHub\Exceptions\ValidationException;
use QuantumTecnology\FalconCrmHub\Resources\Phones\PhoneResource;

it('chama o caminho certo com o número e o token', function (): void {
    [$phones, $http] = resourceWithFake(PhoneResource::class);

    $http->queue(200, [
        'success' => true,
        'message' => 'Código enviado pelo WhatsApp.',
        'data'    => ['phone' => '+5519999999999', 'expires_in' => 600],
    ]);

    $response = $phones->challenge('19999999999');

    $call = $http->lastCall();

    // O caminho é metade do contrato: errar aqui dá 404, que o SDK
    // traduziria como NotFoundException e confundiria quem integra.
    expect($call['url'])->toBe('https://crm.example.com/integration/v1/phones/challenge')
        ->and($call['method'])->toBe('POST')
        ->and($call['payload'])->toBe(['phone' => '19999999999'])
        ->and($call['headers']['Authorization'])->toBe('Bearer fvx_test_token');

    expect($response->success)->toBeTrue()
        ->and($response->data['phone'])->toBe('+5519999999999');
});

it('traduz 402 em QuotaExceededException com used e limit acessíveis', function (): void {
    [$phones, $http] = resourceWithFake(PhoneResource::class);

    $http->queue(402, [
        'success' => false,
        'message' => 'Você já usou as 5 deste mês. O limite renova no dia 1º.',
        'code'    => 'QUOTA_EXCEEDED',
        'used'    => 5,
        'limit'   => 5,
    ]);

    // O integrado precisa saber disso sem fazer parsing de string.
    expect(fn () => $phones->challenge('19999999999'))
        ->toThrow(QuotaExceededException::class);

    try {
        $phones->challenge('19999999999');
    } catch (QuotaExceededException $e) {
        expect($e->getUsed())->toBe(5)
            ->and($e->getLimit())->toBe(5)
            ->and($e->getReason())->toBe('QUOTA_EXCEEDED')
            ->and($e->getCode())->toBe(402);
    }
});

it('traduz 422 de número sem WhatsApp em ValidationException', function (): void {
    [$phones, $http] = resourceWithFake(PhoneResource::class);

    $http->queue(422, [
        'success' => false,
        'message' => 'Esse número não tem WhatsApp. Peça outro número.',
        'code'    => 'NOT_ON_WHATSAPP',
    ]);

    expect(fn () => $phones->challenge('19999999999'))
        ->toThrow(ValidationException::class, 'Esse número não tem WhatsApp. Peça outro número.');
});

it('confirma o código no caminho certo', function (): void {
    [$phones, $http] = resourceWithFake(PhoneResource::class);

    $http->queue(200, [
        'success' => true,
        'message' => 'Número confirmado.',
        'data'    => ['verified' => true],
    ]);

    $response = $phones->confirm('19999999999', '123456');

    expect($http->lastCall()['url'])->toBe('https://crm.example.com/integration/v1/phones/confirm')
        ->and($http->lastCall()['payload'])->toBe(['phone' => '19999999999', 'code' => '123456'])
        ->and($response->data['verified'])->toBeTrue();
});
