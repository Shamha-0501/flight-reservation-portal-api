<?php

namespace App\Support;

use App\Services\EnvironmentStore;

final class Environment
{
    public static function key(string $key, mixed $default = null): mixed
    {
        return app(EnvironmentStore::class)->key($key, $default);
    }

    public static function required(string $key): mixed
    {
        return app(EnvironmentStore::class)->required($key);
    }
}
