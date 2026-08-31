<?php

namespace MadeByClowd\Nusantara\Tests\Support;

use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;

/**
 * Exposes the protected HasNusantaraCaching::rememberLocked() so it can be
 * exercised directly, independent of any real spatial/DB-backed consumer
 * (currently only HasGeoBoundary — see HasGeoBoundarySpatialTest for the
 * live-DB integration coverage).
 */
class RememberLockedProbe
{
    use HasNusantaraCaching;

    /**
     * @return mixed
     */
    public function run(string $key, \Closure $callback)
    {
        return $this->rememberLocked($key, $callback);
    }
}
