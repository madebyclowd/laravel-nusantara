<?php

namespace MadeByClowd\Nusantara\Support\Spatial;

/**
 * Builds the SQL fragment that retrieves a spatial boundary column as
 * canonical WKB — the format `GeometryReader::decode()` (Phase 02) expects.
 * Each driver exposes its own function/method for this; querying through it
 * means the database itself strips MySQL's SRID prefix, resolves PostGIS's
 * EWKB flag bits, and untangles SpatiaLite's internal BLOB format — none of
 * it needs PHP-side byte normalization.
 */
class BoundaryWkbQuery
{
    /**
     * Drivers whose `selectExpression()` output has been proven against a
     * live instance with a real committed fixture (Phase 03) — see
     * `tests/fixtures/wkb/{driver}/`. SQL Server and SpatiaLite syntax below
     * is per vendor documentation only; no live environment exists here to
     * verify against yet, mirroring the same gate Phase 05 already applied
     * to `Geocoder::findContainingRegionSpatial()` — revisit when those
     * environments exist to verify against.
     *
     * @var list<string>
     */
    public const VERIFIED_DRIVERS = ['mysql', 'pgsql'];

    public static function isVerified(string $driver): bool
    {
        return in_array($driver, self::VERIFIED_DRIVERS, true);
    }

    /**
     * SQL fragment selecting `$column` as canonical WKB, for the caller to
     * alias (e.g. `SELECT {$expr} AS boundary_wkb FROM ...`). Returns null
     * for a driver with no known WKB-retrieval function.
     */
    public static function selectExpression(string $driver, string $column): ?string
    {
        return match ($driver) {
            'mysql', 'pgsql' => "ST_AsBinary({$column})",
            'sqlite' => "AsBinary({$column})", // SpatiaLite
            'sqlsrv' => "{$column}.STAsBinary()", // method-on-column syntax, not function-wrapping-column
            default => null,
        };
    }
}
