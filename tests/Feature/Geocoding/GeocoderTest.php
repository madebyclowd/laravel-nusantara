<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use MadeByClowd\Nusantara\Models\District;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Models\Village;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Support\Geocoder;
use MadeByClowd\Nusantara\Tests\TestCase;

class GeocoderTest extends TestCase
{
    protected Geocoder $geocoder;

    // A large bounding square, [lat, lng] pairs, that encloses Keude
    // Bakongan (village 1101012001, lat=2.9310..., lng=97.4845...).
    protected const ENCLOSING_SQUARE = '[[[0,90],[0,100],[10,100],[10,90],[0,90]]]';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.columns.regencies.boundary.enabled' => true]);
        config(['nusantara.columns.districts.boundary.enabled' => true]);
        config(['nusantara.columns.villages.boundary.enabled' => true]);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $this->geocoder = new Geocoder;
    }

    /** @test */
    public function test_it_resolves_a_point_down_to_village_level_through_all_four_boundaries()
    {
        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();
        Regency::find('1101')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();
        District::find('110101')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();
        Village::find('1101012001')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();

        $village = $this->geocoder->findByCoordinate(2.9310, 97.4845, 'village');

        $this->assertNotNull($village);
        $this->assertSame('1101012001', $village->id);
    }

    /** @test */
    public function test_it_resolves_a_point_at_province_level_only()
    {
        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();

        $province = $this->geocoder->findByCoordinate(2.9310, 97.4845, 'province');

        $this->assertNotNull($province);
        $this->assertSame('11', $province->id);
    }

    /** @test */
    public function test_it_returns_null_for_a_point_outside_every_boundary()
    {
        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();

        $province = $this->geocoder->findByCoordinate(-50, -50, 'province');

        $this->assertNull($province);
    }

    /** @test */
    public function test_it_returns_null_when_the_target_level_boundary_does_not_contain_the_point_even_if_a_parent_does()
    {
        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();
        // Regency boundary deliberately does NOT enclose the point.
        Regency::find('1101')->forceFill(['boundary' => '[[[0,90],[0,91],[1,91],[1,90],[0,90]]]'])->save();

        $regency = $this->geocoder->findByCoordinate(2.9310, 97.4845, 'regency');

        $this->assertNull($regency);
    }

    /** @test */
    public function test_it_rejects_an_invalid_level()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->geocoder->findByCoordinate(2.9310, 97.4845, 'country');
    }

    /** @test */
    public function test_it_throws_when_boundary_is_not_enabled_at_a_required_level()
    {
        config(['nusantara.columns.districts.boundary.enabled' => false]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        Province::find('11')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();
        Regency::find('1101')->forceFill(['boundary' => self::ENCLOSING_SQUARE])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/'boundary' column is not enabled/");

        (new Geocoder)->findByCoordinate(2.9310, 97.4845, 'district');
    }

    /** @test */
    public function test_extract_boundary_coordinates_throws_for_a_spatial_column()
    {
        $method = new \ReflectionMethod(Geocoder::class, 'extractBoundaryCoordinates');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not yet support native spatial boundary columns/');

        $method->invoke($this->geocoder, Province::find('11'), 'boundary', true, 'province');
    }

    /** @test */
    public function test_extract_boundary_coordinates_returns_null_for_a_null_boundary()
    {
        $method = new \ReflectionMethod(Geocoder::class, 'extractBoundaryCoordinates');
        $method->setAccessible(true);

        $result = $method->invoke($this->geocoder, Province::find('11'), 'boundary', false, 'province');

        $this->assertNull($result);
    }

    /** @test */
    public function test_parent_key_column_for_level_throws_for_a_level_without_a_parent()
    {
        $method = new \ReflectionMethod(Geocoder::class, 'parentKeyColumnForLevel');
        $method->setAccessible(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/has no parent key/');

        $method->invoke($this->geocoder, 'province', 'provinces');
    }
}
