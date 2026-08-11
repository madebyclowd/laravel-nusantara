<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Models;

use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;

class HasDynamicNusantaraFieldsTest extends TestCase
{
    /** @test */
    public function test_isset_resolves_a_default_mapped_attribute()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $province = Province::find('11');

        $this->assertTrue(isset($province->name));
        $this->assertFalse(isset($province->does_not_exist));
    }

    /** @test */
    public function test_set_attribute_and_isset_resolve_a_custom_mapped_attribute()
    {
        config(['nusantara.columns.provinces.name.name' => 'custom_name']);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $province = Province::find('11');

        $this->assertTrue(isset($province->name));

        $province->name = 'Renamed';
        $province->save();

        $this->assertSame('Renamed', $province->fresh()->name);
        $this->assertSame('Renamed', $province->fresh()->getAttribute('custom_name'));
    }
}
