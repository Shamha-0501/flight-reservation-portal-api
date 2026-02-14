<?php

namespace App\Services;

use App\Models\AppEnv;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class EnvironmentStore
{
    private string $cachePrefix = 'app_env:';
    private int $ttlSeconds = 300;

    public function key(string $key, mixed $default = null, bool $fallbackToEnv = false): mixed
    {
        $cacheKey = $this->cachePrefix . $key;

        $value = Cache::remember($cacheKey, $this->ttlSeconds, function () use ($key) {
            $row = AppEnv::query()->where('key', $key)->first();

            if (!$row || $row->value === null) {
                return null;
            }

            // Secret + array
            if ($row->is_secret && $row->is_array) {
                try {
                    return $this->csvToList(Crypt::decryptString($row->value));
                } catch (\Throwable $e) {
                    return null;
                }
            }

            // Secret scalar
            if ($row->is_secret) {
                try {
                    return Crypt::decryptString($row->value);
                } catch (\Throwable $e) {
                    return null;
                }
            }

            // Non-secret array
            if ($row->is_array) {
                return $this->csvToList($row->value);
            }

            return $row->value;
        });

        if ($value !== null) {
            return $value;
        }

        if ($fallbackToEnv) {
            $envVal = env($key);
            if ($envVal !== null) {
                return $envVal;
            }
        }

        return $default;
    }

    /**
     * Fetch a value and throw if missing/empty.
     *
     * - null => missing
     * - '' (after trim) => missing
     * - [] => missing (so required lists can't be empty)
     */
    public function required(string $key, bool $fallbackToEnv = false): mixed
    {
        $value = $this->key($key, null, $fallbackToEnv);

        if ($value === null) {
            throw new \RuntimeException("Missing required key: $key");
        }

        if (is_string($value) && trim($value) === '') {
            throw new \RuntimeException("Missing required key: $key");
        }

        if (is_array($value) && count($value) === 0) {
            throw new \RuntimeException("Missing required key: $key");
        }

        // IMPORTANT: do not cast; preserve type.
        return $value;
    }

    public function put(string $key, string $value, bool $isArray = false, bool $isSecret = false): void
    {
        $stored = $isSecret ? Crypt::encryptString($value) : $value;

        AppEnv::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'is_array' => $isArray, 'is_secret' => $isSecret]
        );

        Cache::forget($this->cachePrefix . $key);
    }

    public function forget(string $key): void
    {
        AppEnv::query()->where('key', $key)->delete();
        Cache::forget($this->cachePrefix . $key);
    }

    public function clearCache(string $key): void
    {
        Cache::forget($this->cachePrefix . $key);
    }

    /**
     * Parse comma-separated lists safely:
     * - trims whitespace
     * - drops only truly empty entries (''), keeps "0"
     */
    private function csvToList(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));

        // Remove empty strings but keep "0" and other falsey values.
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }
}
