<?php

namespace MadeByClowd\Nusantara\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasNusantaraCaching
{
    /**
     * Memoized tag-support probe result, keyed by cache driver name, so the
     * BadMethodCallException path (thrown by untagged stores like `file` or
     * `database`) is paid once per driver instead of on every single
     * remember()/clearCache() call.
     *
     * @var array<string, bool>
     */
    protected static array $tagsSupported = [];

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

        if ($this->cacheSupportsTags()) {
            return Cache::tags([$prefix])->remember("{$prefix}.{$key}", $ttl, $callback);
        }

        return Cache::remember("{$prefix}.{$key}", $ttl, $callback);
    }

    /**
     * Like remember(), but guards the cache-miss path with Cache::lock() so
     * concurrent first-requests for the same key block on a single caller
     * doing the work instead of every caller redundantly running the
     * (potentially expensive) callback before the cache fills — cache
     * stampede protection for costly one-off computations (e.g. WKB decode),
     * not needed by remember()'s existing bulk-query callers.
     *
     * @return mixed
     */
    protected function rememberLocked(string $key, \Closure $callback)
    {
        $enabled = config('nusantara.cache.enabled', true);

        if (! $enabled) {
            return $callback();
        }

        $prefix = config('nusantara.cache.prefix', 'nusantara');
        $ttl = config('nusantara.cache.ttl', 86400);
        $fullKey = "{$prefix}.{$key}";
        $store = $this->cacheSupportsTags() ? Cache::tags([$prefix]) : Cache::store();

        if ($store->has($fullKey)) {
            return $store->get($fullKey);
        }

        return Cache::lock("{$fullKey}.lock", 10)->block(5, function () use ($store, $fullKey, $ttl, $callback) {
            return $store->remember($fullKey, $ttl, $callback);
        });
    }

    /**
     * Clear all cached regional queries.
     */
    public function clearCache(): bool
    {
        $prefix = config('nusantara.cache.prefix', 'nusantara');

        if (! config('nusantara.cache.enabled', true)) {
            return false;
        }

        if ($this->cacheSupportsTags()) {
            Cache::tags([$prefix])->flush();

            return true;
        }

        return Cache::flush();
    }

    /**
     * Whether the currently configured default cache store supports tags.
     * Probed once per driver (not per call) since the untagged path throws
     * a BadMethodCallException, which is expensive to pay on every request.
     */
    protected function cacheSupportsTags(): bool
    {
        $driver = Cache::getDefaultDriver();

        return self::$tagsSupported[$driver] ??= $this->probeTagsSupport();
    }

    protected function probeTagsSupport(): bool
    {
        try {
            Cache::tags(['__nusantara_tags_probe__'])->has('__nusantara_tags_probe__');

            return true;
        } catch (\BadMethodCallException $e) {
            return false;
        }
    }
}
