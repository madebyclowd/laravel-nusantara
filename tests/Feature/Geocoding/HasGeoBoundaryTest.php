<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;

class HasGeoBoundaryTest extends TestCase
{
    /** @test */
    public function test_it_exports_a_polygon_feature_when_boundary_is_populated()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        Province::find('11')->forceFill(['boundary' => '[[[0,90],[0,100],[10,100],[10,90],[0,90]]]'])->save();

        $geojson = Province::find('11')->toGeoJson();

        $this->assertSame('Feature', $geojson['type']);
        $this->assertSame('Polygon', $geojson['geometry']['type']);
        // Stored as [lat, lng]; GeoJSON coordinate order is [lng, lat].
        $this->assertSame([90.0, 0.0], $geojson['geometry']['coordinates'][0][0]);
        $this->assertSame('Aceh', $geojson['properties']['name']);
    }

    /** @test */
    public function test_it_falls_back_to_a_point_from_lat_lng_when_boundary_is_disabled()
    {
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $geojson = Province::find('11')->toGeoJson();

        $this->assertSame('Feature', $geojson['type']);
        $this->assertSame('Point', $geojson['geometry']['type']);
        $this->assertSame([95.34080851187178, 5.570546962920454], $geojson['geometry']['coordinates']);
    }

    /** @test */
    public function test_it_falls_back_to_a_point_when_boundary_is_enabled_but_null()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $geojson = Province::find('11')->toGeoJson();

        $this->assertSame('Point', $geojson['geometry']['type']);
    }

    /** @test */
    public function test_it_exports_a_multipolygon_feature_when_boundary_has_multiple_polygons()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $multiPolygon = '[[[[0,90],[0,100],[10,100],[10,90],[0,90]]],[[[20,90],[20,100],[30,100],[30,90],[20,90]]]]';
        Province::find('11')->forceFill(['boundary' => $multiPolygon])->save();

        $geojson = Province::find('11')->toGeoJson();

        $this->assertSame('MultiPolygon', $geojson['geometry']['type']);
        $this->assertSame([90.0, 0.0], $geojson['geometry']['coordinates'][0][0][0]);
    }

    /** @test */
    public function test_it_falls_back_to_a_point_when_boundary_json_has_an_invalid_shape()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Depth-2 array — neither a valid Polygon (3) nor MultiPolygon (4).
        Province::find('11')->forceFill(['boundary' => '[[0,90],[0,100]]'])->save();

        $geojson = Province::find('11')->toGeoJson();

        $this->assertSame('Point', $geojson['geometry']['type']);
    }

    /** @test */
    public function test_it_returns_a_null_geometry_when_boundary_and_lat_lng_are_both_unavailable()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        Province::find('11')->forceFill(['latitude' => null, 'longitude' => null])->save();

        $geojson = Province::find('11')->toGeoJson();

        $this->assertNull($geojson['geometry']);
    }
}
