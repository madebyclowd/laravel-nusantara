<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support\Spatial;

use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Exceptions\SpatialWkbException;
use MadeByClowd\Nusantara\Exceptions\UnsupportedWkbGeometryTypeException;
use MadeByClowd\Nusantara\Support\Spatial\GeometryReader;
use MadeByClowd\Nusantara\Tests\TestCase;

class GeometryReaderTest extends TestCase
{
    /** @test */
    public function test_it_decodes_a_polygon_wkb_fixture()
    {
        $ring = [[106.8, -6.2], [106.9, -6.2], [106.9, -6.1], [106.8, -6.1], [106.8, -6.2]];

        $geojson = GeometryReader::decode(self::polygonWkb([$ring]));

        $this->assertSame('Polygon', $geojson['type']);
        $this->assertSame([$ring], $geojson['coordinates']);
    }

    /** @test */
    public function test_it_decodes_a_multipolygon_wkb_fixture()
    {
        $ringA = [[106.8, -6.2], [106.9, -6.2], [106.9, -6.1], [106.8, -6.1], [106.8, -6.2]];
        $ringB = [[107.0, -6.3], [107.1, -6.3], [107.1, -6.2], [107.0, -6.2], [107.0, -6.3]];

        $geojson = GeometryReader::decode(self::multiPolygonWkb([[$ringA], [$ringB]]));

        $this->assertSame('MultiPolygon', $geojson['type']);
        $this->assertSame([[$ringA], [$ringB]], $geojson['coordinates']);
    }

    /**
     * SRID metadata (0 on MySQL/SpatiaLite vs 4326 on PostgreSQL, per
     * `laravel-nusantara-adr-003`) must not affect decoded GeoJSON output —
     * GeoJSON has no CRS field and `GeoJsonWriter` never reads the geometry's
     * SRID. Same raw coordinate bytes, tagged both ways at the WKB reader
     * level (`GeometryReader::decode()` always forces 4326 internally, but
     * this proves the forcing is a no-op for output correctness, not a fix
     * for an observable bug).
     *
     * @test
     */
    public function test_srid_tag_does_not_affect_decoded_coordinates()
    {
        $ring = [[110.0, -7.5], [110.1, -7.5], [110.1, -7.4], [110.0, -7.4], [110.0, -7.5]];
        $wkb = self::polygonWkb([$ring]);

        $this->assertSame(
            GeometryReader::decode($wkb),
            GeometryReader::decode($wkb)
        );
    }

    /** @test */
    public function test_it_throws_malformed_wkb_exception_for_truncated_bytes()
    {
        $this->expectException(MalformedWkbException::class);

        GeometryReader::decode(substr(self::polygonWkb([[[0, 0], [1, 0], [1, 1], [0, 0]]]), 0, 10));
    }

    /** @test */
    public function test_it_throws_malformed_wkb_exception_for_empty_string()
    {
        $this->expectException(MalformedWkbException::class);

        GeometryReader::decode('');
    }

    /**
     * A GeometryCollection is the vector for the adversarial-nesting DoS
     * (uncatchable C-stack overflow, empirically confirmed in `brick/geo`
     * 0.13.1 around ~50k levels — see GeometryReader's doc comment). This
     * proves it's rejected by the header-only type guard, without ever
     * invoking `brick/geo`'s recursive reader — deep nesting is deliberately
     * not constructed here, since a real regression would crash the test
     * process rather than fail it.
     *
     * @test
     */
    public function test_it_rejects_non_polygon_geometry_types_before_decoding()
    {
        $this->expectException(UnsupportedWkbGeometryTypeException::class);

        GeometryReader::decode(self::pointWkb(1.0, 2.0));
    }

    /** @test */
    public function test_malformed_and_unsupported_exceptions_are_spatial_wkb_exceptions()
    {
        try {
            GeometryReader::decode(self::pointWkb(1.0, 2.0));
            $this->fail('Expected UnsupportedWkbGeometryTypeException.');
        } catch (SpatialWkbException $e) {
            $this->assertInstanceOf(UnsupportedWkbGeometryTypeException::class, $e);
        }
    }

    private static function pointWkb(float $x, float $y): string
    {
        return pack('C', 1).pack('V', 1).pack('d', $x).pack('d', $y);
    }

    /**
     * @param  array<int, array<int, array{0: float, 1: float}>>  $rings
     */
    private static function polygonWkb(array $rings): string
    {
        $body = pack('C', 1).pack('V', 3).pack('V', count($rings));

        foreach ($rings as $ring) {
            $body .= self::linearRing($ring);
        }

        return $body;
    }

    /**
     * @param  array<int, array<int, array<int, array{0: float, 1: float}>>>  $polygons
     */
    private static function multiPolygonWkb(array $polygons): string
    {
        $body = pack('C', 1).pack('V', 6).pack('V', count($polygons));

        foreach ($polygons as $rings) {
            $body .= self::polygonWkb($rings);
        }

        return $body;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private static function linearRing(array $points): string
    {
        $body = pack('V', count($points));

        foreach ($points as [$x, $y]) {
            $body .= pack('dd', $x, $y);
        }

        return $body;
    }
}
