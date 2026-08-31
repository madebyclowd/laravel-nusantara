<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Support\RegionQuery;
use MadeByClowd\Nusantara\Tests\Support\RememberLockedProbe;
use MadeByClowd\Nusantara\Tests\TestCase;

class HasNusantaraCachingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);
    }

    /** @test */
    public function test_it_skips_cache_when_disabled()
    {
        config(['nusantara.cache.enabled' => false]);

        $provinces = (new RegionQuery)->provinces();

        $this->assertNotEmpty($provinces);
        $this->assertFalse(Cache::has(config('nusantara.cache.prefix').'.provinces'));
    }

    /** @test */
    public function test_it_falls_back_to_plain_remember_when_tags_are_unsupported()
    {
        config(['cache.default' => 'file']);
        config(['nusantara.cache.enabled' => true]);

        $provinces = (new RegionQuery)->provinces();

        $this->assertNotEmpty($provinces);
        $this->assertTrue(Cache::has(config('nusantara.cache.prefix').'.provinces'));
    }

    /** @test */
    public function test_it_clears_the_cache_when_tags_are_supported()
    {
        config(['nusantara.cache.enabled' => true]);

        $query = new RegionQuery;
        $query->provinces();

        $this->assertTrue($query->clearCache());
    }

    /** @test */
    public function test_it_clears_the_cache_falling_back_when_tags_are_unsupported()
    {
        config(['cache.default' => 'file']);
        config(['nusantara.cache.enabled' => true]);

        $query = new RegionQuery;
        $query->provinces();

        $this->assertIsBool($query->clearCache());
    }

    /** @test */
    public function test_it_does_not_clear_cache_when_disabled()
    {
        config(['nusantara.cache.enabled' => false]);

        $this->assertFalse((new RegionQuery)->clearCache());
    }

    /**
     * Decision 7b (HasGeoBoundary's spatial WKB decode path): rememberLocked()
     * must still only run the expensive callback once per key, same as
     * plain remember() — the cache-hit fast path (checked before ever
     * touching Cache::lock()) is what makes this so.
     *
     * @test
     */
    public function test_remember_locked_only_runs_the_callback_once_per_key()
    {
        // 'file' (untagged) so Cache::has() below reads the same untagged
        // key space rememberLocked() writes to — 'array' (this suite's
        // default) supports tags, which prefixes keys differently, same
        // reason the existing tags-supported tests above don't assert
        // Cache::has() directly either.
        config(['cache.default' => 'file']);
        config(['nusantara.cache.enabled' => true]);

        $probe = new RememberLockedProbe;
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'decoded-value';
        };

        $first = $probe->run('locked-probe-key', $callback);
        $second = $probe->run('locked-probe-key', $callback);

        $this->assertSame('decoded-value', $first);
        $this->assertSame('decoded-value', $second);
        $this->assertSame(1, $calls);
        $this->assertTrue(Cache::has(config('nusantara.cache.prefix').'.locked-probe-key'));
    }

    /** @test */
    public function test_remember_locked_bypasses_the_lock_when_cache_is_disabled()
    {
        config(['nusantara.cache.enabled' => false]);

        $probe = new RememberLockedProbe;
        $calls = 0;

        $probe->run('locked-probe-key', function () use (&$calls) {
            $calls++;

            return 'decoded-value';
        });
        $probe->run('locked-probe-key', function () use (&$calls) {
            $calls++;

            return 'decoded-value';
        });

        $this->assertSame(2, $calls, 'disabled cache must run the callback every time, same as remember()');
    }

    /**
     * Proves rememberLocked() genuinely serializes via Cache::lock() rather
     * than just happening to run the callback once in a single-threaded
     * test — a lock already held (simulating a concurrent in-flight decode
     * from another process/request) must block this caller until it times
     * out, not silently re-run the callback itself.
     *
     * @test
     */
    public function test_remember_locked_blocks_while_another_caller_holds_the_lock()
    {
        config(['nusantara.cache.enabled' => true]);

        $prefix = config('nusantara.cache.prefix', 'nusantara');
        $key = 'locked-probe-key-contended';
        $heldLock = Cache::lock("{$prefix}.{$key}.lock", 10);
        $this->assertTrue($heldLock->get(), 'test setup: failed to acquire the lock the probe should contend on');

        try {
            $probe = new RememberLockedProbe;

            $this->expectException(LockTimeoutException::class);

            $probe->run($key, fn () => 'decoded-value');
        } finally {
            $heldLock->forceRelease();
        }
    }
}
