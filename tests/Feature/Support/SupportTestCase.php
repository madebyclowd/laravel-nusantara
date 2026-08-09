<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;

abstract class SupportTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);
    }
}
