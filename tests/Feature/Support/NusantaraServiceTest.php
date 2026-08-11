<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use MadeByClowd\Nusantara\Facades\Nusantara;
use MadeByClowd\Nusantara\Models\District;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Models\Village;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;

class NusantaraServiceTest extends TestCase
{
    // A large bounding square, [lat, lng] pairs, that encloses village 1101012001.
    protected const ENCLOSING_SQUARE = '[[[0,90],[0,100],[10,100],[10,90],[0,90]]]';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.columns.regencies.boundary.enabled' => true]);
        config(['nusantara.columns.districts.boundary.enabled' => true]);
        config(['nusantara.columns.villages.boundary.enabled' => true]);
        config(['nusantara.columns.villages.postal_code.enabled' => true]);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);
    }

    /** @test */
    public function test_it_exposes_the_configured_model_class_names()
    {
        $this->assertSame(Province::class, Nusantara::getProvinceModel());
        $this->assertSame(Regency::class, Nusantara::getRegencyModel());
        $this->assertSame(District::class, Nusantara::getDistrictModel());
        $this->assertSame(Village::class, Nusantara::getVillageModel());
    }

    /** @test */
    public function test_it_delegates_hierarchy_lookups()
    {
        $this->assertNotNull(Nusantara::findRegency('1101'));
        $this->assertNotEmpty(Nusantara::districtsOf('1101'));
        $this->assertNotNull(Nusantara::findDistrict('110101'));
        $this->assertNotEmpty(Nusantara::villagesOf('110101'));
        $this->assertNotNull(Nusantara::findVillage('1101012001'));
    }

    /** @test */
    public function test_it_delegates_fuzzy_search()
    {
        $results = Nusantara::searchFuzzy('Acej', 20, 'provinces');

        $this->assertNotEmpty($results['provinces']);
        $this->assertSame('Aceh', $results['provinces'][0]['name']);
    }

    /** @test */
    public function test_it_delegates_nik_parsing_and_validation()
    {
        $info = Nusantara::parseNik('1101011505900001');

        $this->assertSame('1101', $info->regencyId);
        $this->assertTrue(Nusantara::isValidNik('1101011505900001'));
        $this->assertFalse(Nusantara::isValidNik('invalid'));
    }

    /** @test */
    public function test_it_delegates_reverse_geocoding()
    {
        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();

        $province = Nusantara::findByCoordinate(2.9310, 97.4845, 'province');

        $this->assertNotNull($province);
        $this->assertSame('11', $province->id);
    }

    /** @test */
    public function test_it_delegates_postal_code_resolution()
    {
        $villages = Nusantara::resolvePostalCode('23773');

        $this->assertNotEmpty($villages);
        $this->assertTrue(Nusantara::isValidPostalCode('23773'));
        $this->assertFalse(Nusantara::isValidPostalCode('1234'));
    }
}
