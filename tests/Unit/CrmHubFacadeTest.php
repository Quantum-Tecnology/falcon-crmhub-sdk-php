<?php

declare(strict_types = 1);

use QuantumTecnology\FalconCrmHub\CrmHub;
use QuantumTecnology\FalconCrmHub\CrmHubConfig;
use QuantumTecnology\FalconCrmHub\Exceptions\FalconException;
use QuantumTecnology\FalconCrmHub\Resources\Messages\MessageResource;

afterEach(fn () => CrmHub::reset());

it('avisa quando o SDK não foi configurado', function (): void {
    expect(fn () => CrmHub::messages())->toThrow(FalconException::class);
});

it('entrega os recursos depois de configurado', function (): void {
    CrmHub::configure(CrmHubConfig::make('https://crm.example.com', 'fvx_x'));

    expect(CrmHub::messages())->toBeInstanceOf(MessageResource::class);
});

it('tira a barra final da URL, para não montar //integration', function (): void {
    expect(CrmHubConfig::make('https://crm.example.com/', 'fvx_x')->getBaseUrl())
        ->toBe('https://crm.example.com');
});
