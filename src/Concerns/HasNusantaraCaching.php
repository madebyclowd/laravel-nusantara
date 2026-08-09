<?php

namespace MadeByClowd\Nusantara\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasNusantaraCaching
{
    /**
     * Wrap a callback in a tag-safe cache remember, falling back to a
     * plain remember when the configured cache store doesn't support tags.
     *
     * @return mixed
     */
    protected function remember(string $key, \Closure $callback)
    {
        $enabled = config('nusantara.cache.enabled', true);

        if (! $enabled) {
            return $callback();
        }

        $prefix = config('nusantara.cache.prefix', 'nusantara');
        $ttl = config('nusantara.cache.ttl', 86400);

        try {
            return Cache::tags([$prefix])->remember("{$prefix}.{$key}", $ttl, $callback);
        } catch (\BadMethodCallException $e) {
            // Fallback for cache drivers that do not support tags (e.g. database, file)
            return Cache::remember("{$prefix}.{$key}", $ttl, $callback);
        }
    }

    /**
     * Clear all cached regional queries.
     */
    public function clearCache(): bool
    {
        $prefix = config('nusantara.cache.prefix', 'nusantara');

        if (config('nusantara.cache.enabled', true)) {
            try {
                Cache::tags([$prefix])->flush();

                return true;
            } catch (\BadMethodCallException $e) {
                // Fallback to flushing entire cache if tags are unsupported
                return Cache::flush();
            }
        }

        return false;
    }
}
