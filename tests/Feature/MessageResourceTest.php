<?php

declare(strict_types = 1);

use QuantumTecnology\FalconCrmHub\Exceptions\OptedOutException;
use QuantumTecnology\FalconCrmHub\Exceptions\ValidationException;
use QuantumTecnology\FalconCrmHub\Resources\Messages\MessageResource;

it('dispara por modelo com as variáveis', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);

    $http->queue(202, [
        'success' => true,
        'message' => 'Mensagem na fila de envio.',
        'data'    => ['message_id' => 'abc-123', 'status' => 'queued'],
    ]);

    $response = $messages->send('19999999999', 'assinatura_vencida', ['nome' => 'Ana', 'plano' => 'Pro'], 'Ana Silva');

    expect($http->lastCall()['url'])->toBe('https://crm.example.com/integration/v1/messages')
        ->and($http->lastCall()['payload'])->toBe([
            'phone'     => '19999999999',
            'template'  => 'assinatura_vencida',
            'variables' => ['nome' => 'Ana', 'plano' => 'Pro'],
            'name'      => 'Ana Silva',
        ]);

    // 202, não 200: enfileirada. O SDK não promete entrega.
    expect($response->statusCode)->toBe(202)
        ->and($response->data['status'])->toBe('queued');
});

it('omite o nome quando não informado, em vez de mandar null', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);
    $http->queue(202, ['success' => true, 'data' => ['message_id' => 'x', 'status' => 'queued']]);

    $messages->send('19999999999', 'boas_vindas');

    expect($http->lastCall()['payload'])->not->toHaveKey('name');
});

it('traduz 409 em OptedOutException, que não deve virar retry', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);

    $http->queue(409, [
        'success' => false,
        'message' => 'Esse contato pediu para não receber mensagens.',
        'code'    => 'OPTED_OUT',
    ]);

    // Exception própria justamente para o integrado NÃO tratar como falha
    // transitória: reenviar para quem pediu para sair é o caminho mais curto
    // para o número do dono da conta ser banido.
    expect(fn () => $messages->send('19999999999', 'assinatura_vencida', ['nome' => 'Ana']))
        ->toThrow(OptedOutException::class);
});

it('traduz variável faltando em ValidationException com os campos', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);

    $http->queue(422, [
        'success' => false,
        'message' => 'Faltam variáveis do modelo: plano.',
        'code'    => 'MISSING_VARIABLES',
        'missing' => ['plano'],
        'errors'  => ['variables' => ['plano']],
    ]);

    try {
        $messages->send('19999999999', 'assinatura_vencida', ['nome' => 'Ana']);
        expect(false)->toBeTrue('deveria ter lançado');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('plano')
            ->and($e->getErrors())->toBe(['variables' => ['plano']]);
    }
});

it('manda texto livre no mesmo endpoint, com body em vez de template', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);
    $http->queue(202, ['success' => true, 'data' => ['message_id' => 'x', 'status' => 'queued']]);

    $messages->sendText('19999999999', 'Sua nota fiscal está pronta.');

    expect($http->lastCall()['payload'])->toBe([
        'phone' => '19999999999',
        'body'  => 'Sua nota fiscal está pronta.',
    ]);
});

it('traduz texto livre não liberado em ValidationException', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);

    $http->queue(422, [
        'success' => false,
        'message' => 'Seu plano não permite enviar texto livre. Use um modelo cadastrado.',
        'code'    => 'FREE_TEXT_NOT_ALLOWED',
    ]);

    expect(fn () => $messages->sendText('19999999999', 'oi'))
        ->toThrow(ValidationException::class);
});

it('expõe o código de negócio, que vem na raiz do envelope', function (): void {
    [$messages, $http] = resourceWithFake(MessageResource::class);

    $http->queue(422, [
        'success' => false,
        'message' => 'Não existe um modelo ativo com essa chave.',
        // Raiz, irmão de `data` — não dentro dele.
        'code' => 'TEMPLATE_NOT_FOUND',
    ]);

    try {
        $messages->send('19999999999', 'nao_existe');
        expect(false)->toBeTrue('deveria ter lançado');
    } catch (ValidationException $e) {
        // Sem isto, quem integra fica só com o 422 e não distingue "modelo não
        // existe" de "faltou variável" — casos que exigem ações diferentes.
        expect($e->getResponse()?->code)->toBe('TEMPLATE_NOT_FOUND');
    }
});
