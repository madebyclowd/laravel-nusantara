<?php

namespace MadeByClowd\Nusantara\Support;

class CoordinateGeometry
{
    /**
     * Determine whether a [lat, lng] point falls inside a boundary
     * coordinate array — a depth-3 array (single Polygon: a list of rings,
     * each ring a list of [lat, lng] points) or depth-4 array (MultiPolygon:
     * a list of Polygons). Ported from py-nusantara's is_point_in_boundary()
     * pure-Python ray-casting path.
     */
    public static function isPointInBoundary(float $lat, float $lng, array $coordinates): bool
    {
        $depth = self::depth($coordinates);

        if ($depth === 3) {
            return self::pointInPolygon($lat, $lng, $coordinates);
        }

        if ($depth === 4) {
            foreach ($coordinates as $polygon) {
                if (self::pointInPolygon($lat, $lng, $polygon)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Jordan curve theorem (ray-casting) point-in-polygon test. Ported
     * line-for-line from py-nusantara's point_in_polygon(). `$x`/`$y` are
     * latitude/longitude respectively (not Cartesian x/y) to match the
     * reference implementation's variable naming.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $polygon  List of rings, each a list of [lat, lng] points.
     */
    public static function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $inside = false;

        foreach ($polygon as $ring) {
            $n = count($ring);

            if ($n < 3) {
                continue;
            }

            $xinters = 0.0;
            [$p1x, $p1y] = $ring[0];

            for ($i = 0; $i <= $n; $i++) {
                [$p2x, $p2y] = $ring[$i % $n];

                if ($y > min($p1y, $p2y)) {
                    if ($y <= max($p1y, $p2y)) {
                        if ($x <= max($p1x, $p2x)) {
                            if ($p1y !== $p2y) {
                                $xinters = ($y - $p1y) * ($p2x - $p1x) / ($p2y - $p1y) + $p1x;
                            }

                            if ($p1x === $p2x || $x <= $xinters) {
                                $inside = ! $inside;
                            }
                        }
                    }
                }

                [$p1x, $p1y] = [$p2x, $p2y];
            }
        }

        return $inside;
    }

    /**
     * Maximum nesting depth of a coordinate array — 3 for a single Polygon
     * (rings of points), 4 for a MultiPolygon (a list of Polygons).
     */
    public static function depth(array $array): int
    {
        if (empty($array)) {
            return 1;
        }

        $max = 0;

        foreach ($array as $item) {
            if (is_array($item)) {
                $max = max($max, self::depth($item));
            }
        }

        return 1 + $max;
    }
}
