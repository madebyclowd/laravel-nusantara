<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Historical;

use MadeByClowd\Nusantara\Support\RegionQuery;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class RegionQueryLegacyFallbackTest extends SupportTestCase
{
    protected RegionQuery $regionQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionQuery = new RegionQuery;
    }

    /** @test */
    public function test_it_finds_a_regency_by_its_legacy_pre_2022_papua_code()
    {
        $regency = $this->regionQuery->findRegency('9101');

        $this->assertNotNull($regency);
        $this->assertSame('9301', $regency->id);
        $this->assertEquals('Kabupaten Merauke', $regency->name);
    }

    /** @test */
    public function test_it_finds_a_district_by_its_legacy_pre_2022_papua_code()
    {
        $district = $this->regionQuery->findDistrict('910101');

        $this->assertNotNull($district);
        $this->assertSame('930101', $district->id);
    }

    /** @test */
    public function test_it_finds_a_village_by_its_legacy_pre_2022_papua_code()
    {
        $village = $this->regionQuery->findVillage('9101011002');

        $this->assertNotNull($village);
        $this->assertSame('9301011002', $village->id);
        $this->assertEquals('Samkai', $village->name);
    }

    /** @test */
    public function test_direct_lookup_still_takes_priority_over_legacy_fallback()
    {
        // The active code itself must resolve directly, without touching
        // the legacy resolver at all.
        $regency = $this->regionQuery->findRegency('9301');

        $this->assertNotNull($regency);
        $this->assertSame('9301', $regency->id);
    }

    /** @test */
    public function test_it_returns_null_for_an_id_that_is_neither_active_nor_legacy()
    {
        $this->assertNull($this->regionQuery->findRegency('0000'));
    }
}
