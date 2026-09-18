<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;

class IdempotencyRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    private const TTL_SECONDS = 86400; // 24h — covers a flaky-client retry window

    public function find(string $key): ?array
    {
        return Cache::get("idempotency:{$key}");
    }

    public function store(string $key, array $body, int $status): void
    {
        Cache::put("idempotency:{$key}", ['body' => $body, 'status' => $status], self::TTL_SECONDS);
    }
}
