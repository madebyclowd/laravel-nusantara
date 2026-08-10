<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use MadeByClowd\Nusantara\Support\CoordinateGeometry;
use MadeByClowd\Nusantara\Tests\TestCase;

class CoordinateGeometryTest extends TestCase
{
    /** @test */
    public function test_it_detects_a_point_inside_a_simple_square()
    {
        // A square ring from (0,0) to (10,10) in [lat, lng].
        $square = [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]];

        $this->assertTrue(CoordinateGeometry::isPointInBoundary(5, 5, $square));
    }

    /** @test */
    public function test_it_detects_a_point_outside_a_simple_square()
    {
        $square = [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]];

        $this->assertFalse(CoordinateGeometry::isPointInBoundary(20, 20, $square));
    }

    /** @test */
    public function test_it_detects_a_point_inside_one_polygon_of_a_multipolygon()
    {
        $multiPolygon = [
            [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]],
            [[[50, 50], [50, 60], [60, 60], [60, 50], [50, 50]]],
        ];

        $this->assertTrue(CoordinateGeometry::isPointInBoundary(55, 55, $multiPolygon));
        $this->assertTrue(CoordinateGeometry::isPointInBoundary(5, 5, $multiPolygon));
        $this->assertFalse(CoordinateGeometry::isPointInBoundary(30, 30, $multiPolygon));
    }

    /** @test */
    public function test_a_hole_ring_excludes_points_inside_it()
    {
        // Outer 0-10 square with an inner 3-7 hole.
        $polygonWithHole = [
            [[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]],
            [[3, 3], [3, 7], [7, 7], [7, 3], [3, 3]],
        ];

        $this->assertTrue(CoordinateGeometry::isPointInBoundary(1, 1, $polygonWithHole));
        $this->assertFalse(CoordinateGeometry::isPointInBoundary(5, 5, $polygonWithHole));
    }

    /** @test */
    public function test_depth_distinguishes_polygon_from_multipolygon()
    {
        $polygon = [[[0, 0], [0, 10], [10, 10]]];
        $multiPolygon = [[[[0, 0], [0, 10], [10, 10]]]];

        $this->assertSame(3, CoordinateGeometry::depth($polygon));
        $this->assertSame(4, CoordinateGeometry::depth($multiPolygon));
    }

    /** @test */
    public function test_depth_of_an_empty_array_is_one()
    {
        $this->assertSame(1, CoordinateGeometry::depth([]));
    }

    /** @test */
    public function test_it_rejects_a_boundary_of_an_invalid_depth()
    {
        // Depth-2 array — neither a Polygon (3) nor a MultiPolygon (4).
        $this->assertFalse(CoordinateGeometry::isPointInBoundary(5, 5, [[0, 0], [0, 10]]));
    }

    /** @test */
    public function test_it_skips_a_ring_with_fewer_than_three_points()
    {
        // A degenerate ring (2 points) inside an otherwise-enclosing square.
        $square = [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]], [[3, 3], [3, 7]]];

        $this->assertTrue(CoordinateGeometry::isPointInBoundary(5, 5, $square));
    }
}
