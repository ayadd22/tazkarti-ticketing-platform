<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EventCacheService
{
    private const VERSION_KEY = 'events_cache_version';

    public function version(): int
    {
        return Cache::get(self::VERSION_KEY, 1);
    }

    public function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    public function key(string $identifier): string
    {
        return 'events:v' . $this->version() . ':' . $identifier;
    }
}