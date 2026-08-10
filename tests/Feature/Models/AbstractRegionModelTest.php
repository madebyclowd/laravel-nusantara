<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Models;

use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Tests\TestCase;

class AbstractRegionModelTest extends TestCase
{
    /** @test */
    public function test_it_leaves_the_default_connection_untouched_when_none_is_configured()
    {
        $this->assertNull((new Province)->getConnectionName());
    }

    /** @test */
    public function test_it_uses_the_configured_connection_when_one_is_set()
    {
        config(['nusantara.connection' => 'testing']);

        $this->assertSame('testing', (new Province)->getConnectionName());
    }
}
