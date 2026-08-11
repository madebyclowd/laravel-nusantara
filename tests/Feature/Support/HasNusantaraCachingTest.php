<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use Illuminate\Support\Facades\Cache;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Support\RegionQuery;
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
}
