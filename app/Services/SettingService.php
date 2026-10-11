<?php

namespace App\Services;

use LogicException;

class SettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        throw new LogicException('Not implemented yet.');
    }

    public function set(string $key, mixed $value): void
    {
        throw new LogicException('Not implemented yet.');
    }

    public function all(): array
    {
        throw new LogicException('Not implemented yet.');
    }
}
