<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub;

use QuantumTecnology\FalconCrmHub\Exceptions\FalconException;
use QuantumTecnology\FalconCrmHub\Resources\Customers\CustomerResource;
use QuantumTecnology\FalconCrmHub\Resources\Messages\MessageResource;
use QuantumTecnology\FalconCrmHub\Resources\Phones\PhoneResource;
use QuantumTecnology\FalconCrmHub\Resources\Usage\UsageResource;

/**
 * Facade estática do SDK do Falcon Vendas.
 *
 * ```php
 * CrmHub::configure(CrmHubConfig::make('https://crm.falcon-server.com.br', 'fvx_...'));
 * CrmHub::phones()->challenge('+5519999999999');
 * ```
 *
 * Em aplicação com injeção de dependência, prefira instanciar o CrmHubClient e
 * registrá-lo no container: o estado estático atrapalha teste paralelo e força
 * `reset()` entre casos.
 */
final class CrmHub
{
    private static ?CrmHubClient $instance = null;

    public static function configure(CrmHubConfig $config): void
    {
        self::$instance = new CrmHubClient($config);
    }

    public static function client(): CrmHubClient
    {
        if (null === self::$instance) {
            throw new FalconException(
                'CrmHub SDK not configured. Call CrmHub::configure(CrmHubConfig::make(...)) first.',
            );
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function customers(): CustomerResource
    {
        return self::client()->customers();
    }

    public static function phones(): PhoneResource
    {
        return self::client()->phones();
    }

    public static function messages(): MessageResource
    {
        return self::client()->messages();
    }

    public static function usage(): UsageResource
    {
        return self::client()->usage();
    }
}
