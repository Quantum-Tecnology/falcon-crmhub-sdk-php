<?php

declare(strict_types = 1);

namespace QuantumTecnology\FalconCrmHub\Auth;

interface TokenStoreInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, int $ttlSeconds): void;

    public function delete(string $key): void;
}
