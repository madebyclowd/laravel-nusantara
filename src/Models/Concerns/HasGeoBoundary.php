<?php

namespace MadeByClowd\Nusantara\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;
use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Support\CoordinateGeometry;
use MadeByClowd\Nusantara\Support\Spatial\BoundaryWkbQuery;
use MadeByClowd\Nusantara\Support\Spatial\GeometryReader;
use MadeByClowd\Nusantara\Support\SpatialColumn;

trait HasGeoBoundary
{
    use HasNusantaraCaching;

    /**
     * Convert this region to a GeoJSON Feature. Uses the `boundary` column
     * (Polygon/MultiPolygon) when enabled and populated, falling back to a
     * Point built from `latitude`/`longitude` otherwise — mirrors
     * py-nusantara's BaseRecord::to_geojson() fallback behavior.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if `boundary` is populated but stored as a native spatial column on a driver without verified WKB retrieval yet (SQL Server, SpatiaLite — see BoundaryWkbQuery::VERIFIED_DRIVERS; MySQL and PostgreSQL/PostGIS decode via brick/geo).
     * @throws MalformedWkbException if a native spatial `boundary` column holds corrupt/unsupported WKB.
     */
    public function toGeoJson(): array
    {
        $tableKey = $this->getLogicalTableName();

        return [
            'type' => 'Feature',
            'geometry' => $this->resolveGeoJsonGeometry($tableKey),
            'properties' => $this->attributesToArray(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveGeoJsonGeometry(string $tableKey): ?array
    {
        $boundaryColumn = config("nusantara.columns.{$tableKey}.boundary.name", 'boundary');
        $hasBoundaryColumn = Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), $boundaryColumn);
        $raw = $hasBoundaryColumn ? $this->getRawOriginal($boundaryColumn) : null;

        if ($raw !== null) {
            $geometry = SpatialColumn::isSpatial($this->getConnectionName(), $this->getTable(), $boundaryColumn)
                ? $this->resolveSpatialBoundaryGeometry($boundaryColumn, $raw)
                : $this->resolveTextBoundaryGeometry($raw);

            if ($geometry !== null) {
                return $geometry;
            }
        }

        $latColumn = config("nusantara.columns.{$tableKey}.latitude.name", 'latitude');
        $lngColumn = config("nusantara.columns.{$tableKey}.longitude.name", 'longitude');

        $lat = $this->getRawOriginal($latColumn);
        $lng = $this->getRawOriginal($lngColumn);

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'type' => 'Point',
            'coordinates' => [(float) $lng, (float) $lat], // GeoJSON coordinate order is [lng, lat]
        ];
    }

    /**
     * @param  array<int, mixed>  $coordinates  Stored as [lat, lng] pairs (this package's boundary storage convention).
     * @return array<string, mixed>|null
     */
    protected function coordinatesToGeoJsonGeometry(array $coordinates): ?array
    {
        $depth = CoordinateGeometry::depth($coordinates);

        if ($depth === 3) {
            return [
                'type' => 'Polygon',
                'coordinates' => $this->swapLatLngRings($coordinates),
            ];
        }

        if ($depth === 4) {
            return [
                'type' => 'MultiPolygon',
                'coordinates' => array_map(fn ($polygon) => $this->swapLatLngRings($polygon), $coordinates),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveTextBoundaryGeometry(string $raw): ?array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $this->coordinatesToGeoJsonGeometry($decoded) : null;
    }

    /**
     * Decodes a native spatial `boundary` column into GeoJSON, cached per
     * row (keyed by table + primary key + a hash of the raw column value,
     * so a `--force` re-download naturally invalidates the cache without an
     * explicit clear) and guarded against cache-stampede via
     * HasNusantaraCaching::rememberLocked() (decision 7b).
     *
     * @param  mixed  $raw  The model's raw (uncast) attribute value for `$boundaryColumn` — not itself WKB (its exact
     *                      shape is driver-internal); used only as a cheap, stable input to the cache key's hash.
     * @return array{type: string, coordinates: array<int, mixed>}
     *
     * @throws \RuntimeException if the connection's driver has no verified WKB retrieval query yet.
     * @throws MalformedWkbException if the retrieved WKB is corrupt/unsupported.
     */
    protected function resolveSpatialBoundaryGeometry(string $boundaryColumn, $raw): array
    {
        $cacheKey = "boundary-wkb.{$this->getTable()}.{$this->getKey()}.".md5((string) $raw);

        return $this->rememberLocked($cacheKey, fn () => GeometryReader::decode($this->fetchBoundaryWkb($boundaryColumn)));
    }

    /**
     * Retrieves the current row's boundary column as canonical WKB via
     * Phase 03's per-driver query (`ST_AsBinary()`/`AsBinary()`/`.STAsBinary()`)
     * — `getRawOriginal()` can't be reused here since it reads the plain
     * column value, not this `ST_AsBinary()`-wrapped projection.
     */
    protected function fetchBoundaryWkb(string $boundaryColumn): string
    {
        $connectionName = $this->getConnectionName();
        $driver = DB::connection($connectionName)->getDriverName();

        if (! BoundaryWkbQuery::isVerified($driver)) {
            throw new \RuntimeException(
                "toGeoJson() does not yet support native spatial boundary columns on the '{$driver}' driver ".
                "('{$boundaryColumn}' on '{$this->getTable()}' is stored as a spatial type, e.g. via config('nusantara.boundaries.type', 'spatial')). ".
                'WKB retrieval is currently only verified for MySQL and PostgreSQL/PostGIS (see BoundaryWkbQuery::VERIFIED_DRIVERS).'
            );
        }

        $selectExpr = BoundaryWkbQuery::selectExpression($driver, $boundaryColumn);

        $wkb = DB::connection($connectionName)
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->selectRaw("{$selectExpr} AS wkb")
            ->value('wkb');

        // PostgreSQL's PDO driver returns `bytea` columns as a stream
        // resource, not a string — same handling export-wkb-fixtures.php
        // already needs for the identical retrieval query.
        if (is_resource($wkb)) {
            $wkb = stream_get_contents($wkb);
        }

        if ($wkb === null || $wkb === false) {
            throw new MalformedWkbException(
                "Spatial boundary column '{$boundaryColumn}' on '{$this->getTable()}' returned no WKB for key ".
                "'{$this->getKey()}' — the row may have been deleted or the boundary cleared since this model was loaded."
            );
        }

        return $wkb;
    }

    /**
     * @param  array<int, array<int, array{0: float, 1: float}>>  $rings
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    protected function swapLatLngRings(array $rings): array
    {
        return array_map(
            fn ($ring) => array_map(fn ($point) => [(float) $point[1], (float) $point[0]], $ring),
            $rings
        );
    }
}
