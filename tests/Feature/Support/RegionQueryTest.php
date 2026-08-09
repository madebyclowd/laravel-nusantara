<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use MadeByClowd\Nusantara\Support\RegionQuery;

class RegionQueryTest extends SupportTestCase
{
    protected RegionQuery $regionQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionQuery = new RegionQuery;
    }

    /** @test */
    public function test_it_fetches_all_provinces()
    {
        $provinces = $this->regionQuery->provinces();

        $this->assertCount(38, $provinces);
    }

    /** @test */
    public function test_it_finds_a_province_by_id()
    {
        $province = $this->regionQuery->findProvince('11');

        $this->assertNotNull($province);
        $this->assertEquals('Aceh', $province->name);
    }

    /** @test */
    public function test_it_returns_null_for_an_unknown_province()
    {
        $this->assertNull($this->regionQuery->findProvince('99'));
    }

    /** @test */
    public function test_it_fetches_regencies_of_a_province()
    {
        $regencies = $this->regionQuery->regenciesOf('11');

        $this->assertNotEmpty($regencies);
        $this->assertEquals('1101', $regencies->first()->id);
    }

    /** @test */
    public function test_it_returns_empty_collection_for_regencies_of_an_unknown_province()
    {
        $regencies = $this->regionQuery->regenciesOf('99');

        $this->assertCount(0, $regencies);
    }

    /** @test */
    public function test_it_finds_a_regency_by_id()
    {
        $regency = $this->regionQuery->findRegency('1101');

        $this->assertNotNull($regency);
        $this->assertEquals('Kabupaten Aceh Selatan', $regency->name);
    }

    /** @test */
    public function test_it_fetches_districts_of_a_regency()
    {
        $districts = $this->regionQuery->districtsOf('1101');

        $this->assertNotEmpty($districts);
        $this->assertEquals('110101', $districts->first()->id);
    }

    /** @test */
    public function test_it_finds_a_district_by_id()
    {
        $district = $this->regionQuery->findDistrict('110101');

        $this->assertNotNull($district);
    }

    /** @test */
    public function test_it_fetches_villages_of_a_district()
    {
        $villages = $this->regionQuery->villagesOf('110101');

        $this->assertNotEmpty($villages);
    }

    /** @test */
    public function test_it_finds_a_village_by_id()
    {
        $district = $this->regionQuery->districtsOf('1101')->first();
        $village = $this->regionQuery->villagesOf($district->id)->first();

        $found = $this->regionQuery->findVillage($village->id);

        $this->assertNotNull($found);
        $this->assertEquals($village->id, $found->id);
    }
}
