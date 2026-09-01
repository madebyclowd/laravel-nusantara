<?php

namespace MadeByClowd\Nusantara\Support\Spatial;

use Brick\Geo\Exception\GeometryIoException;
use Brick\Geo\Geometry;
use Brick\Geo\Io\GeoJsonWriter;
use Brick\Geo\Io\WkbReader;
use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Exceptions\MissingSpatialDependencyException;
use MadeByClowd\Nusantara\Exceptions\UnsupportedWkbGeometryTypeException;

/**
 * Thin wrapper around `brick/geo`, decoding canonical WKB (as returned by
 * each driver's `ST_AsBinary()`/`.STAsBinary()`) into a GeoJSON geometry
 * array. `brick/geo` is a `suggest`-only dependency (decision 8) — every
 * entry point here guards with `class_exists()` and throws this package's
 * own typed exception when it's absent, instead of a fatal class-not-found
 * error.
 */
class GeometryReader
{
    /**
     * GeoJSON (RFC 7946) is always implicitly WGS84 and carries no CRS field,
     * so this is forced for `Geometry` object correctness only — it does not
     * change `toGeoJson()` output. Empirically verified (Phase 02): decoding
     * identical coordinate bytes tagged SRID 0 vs SRID 4326 produces
     * byte-identical `GeoJsonWriter` output, since `GeoJsonWriter` never
     * reads the geometry's SRID at all. Reconciles the write path's
     * per-driver SRID split (PostgreSQL writes 4326, MySQL/SpatiaLite write 0
     * — see `laravel-nusantara-adr-003`).
     */
    private const SRID = 4326;

    /**
     * The only geometry types this package's boundary column ever stores
     * (`DownloadBoundariesCommand` only ever writes Polygon/MultiPolygon).
     * Enforced via a cheap 5-byte header peek in `assertAllowedType()`
     * *before* `brick/geo`'s `WkbReader` ever sees the bytes — this is a
     * real DoS guard, not just an input-shape check: a deeply nested
     * `GeometryCollection` WKB crashes the PHP process with an uncatchable
     * C-stack overflow (segfault, no exception raised) around ~50,000
     * levels of nesting on this environment's PHP build. Empirically
     * confirmed against `brick/geo` 0.13.1 — the library has no built-in
     * recursion/depth guard, so this package supplies one by construction
     * (nested `GeometryCollection`s can never reach the recursive reader,
     * since they're rejected before it's invoked).
     *
     * @var list<int>
     */
    private const ALLOWED_TYPES = [Geometry::POLYGON, Geometry::MULTIPOLYGON];

    /**
     * Decode a canonical WKB string into a GeoJSON geometry array —
     * `['type' => 'Polygon'|'MultiPolygon', 'coordinates' => [...]]`,
     * a drop-in match for `HasGeoBoundary::toGeoJson()`'s existing contract
     * (verified in Phase 02: `GeoJsonWriter::writeRaw()` returns a `stdClass`
     * with exactly these two keys and plain-array coordinates, needing only
     * an `(array)` cast — no coordinate-order swap, unlike this package's
     * `[lat, lng]` text-mode storage convention, since WKB's x/y order
     * already matches GeoJSON's `[lng, lat]`).
     *
     * @return array{type: string, coordinates: array<int, mixed>}
     *
     * @throws MissingSpatialDependencyException if `brick/geo` is not installed.
     * @throws UnsupportedWkbGeometryTypeException if the WKB is not a Polygon/MultiPolygon.
     * @throws MalformedWkbException if the WKB cannot be decoded (truncated/corrupt bytes, invalid byte-order flag, etc.).
     */
    public static function decode(string $wkb): array
    {
        if (! class_exists(Geometry::class)) {
            throw new MissingSpatialDependencyException(
                "Decoding a native spatial boundary column requires the 'brick/geo' package, which is not installed. ".
                'Install it with: composer require brick/geo'
            );
        }

        self::assertAllowedType($wkb);

        try {
            $geometry = (new WkbReader)->read($wkb, self::SRID);
            $raw = (new GeoJsonWriter)->writeRaw($geometry);
        } catch (GeometryIoException $e) {
            throw new MalformedWkbException(
                "Malformed WKB in spatial boundary column: {$e->getMessage()}. ".
                'Re-run nusantara:download-boundaries --force to re-seed this row.',
                previous: $e
            );
        }

        /** @var array{type: string, coordinates: array<int, mixed>} */
        return (array) $raw;
    }

    /**
     * Peeks the WKB header (byte-order flag + geometry-type word, 5 bytes)
     * without invoking `brick/geo` at all, so a disallowed/nested geometry
     * never reaches the recursive reader. See `ALLOWED_TYPES` doc comment.
     */
    private static function assertAllowedType(string $wkb): void
    {
        if (strlen($wkb) < 5) {
            throw new MalformedWkbException(
                'Malformed WKB in spatial boundary column: unexpected end of stream. '.
                'Re-run nusantara:download-boundaries --force to re-seed this row.'
            );
        }

        $isLittleEndian = ord($wkb[0]) === 1;
        /** @var array{1: int} $unpacked */
        $unpacked = unpack($isLittleEndian ? 'V' : 'N', substr($wkb, 1, 4));
        $wkbType = $unpacked[1] % 1000; // strips EWKB Z/M offsets (1000/2000/3000), matches brick/geo's own header parsing

        if (! in_array($wkbType, self::ALLOWED_TYPES, true)) {
            throw new UnsupportedWkbGeometryTypeException(
                "Spatial boundary WKB has geometry type {$wkbType}, but this package's boundary column only ever ".
                'stores Polygon or MultiPolygon — decoding any other geometry type is not supported.'
            );
        }
    }
}
