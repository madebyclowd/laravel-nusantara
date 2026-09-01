<?php

namespace MadeByClowd\Nusantara\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Geocoder
{
    /**
     * Region levels in narrowing order — each level's boundary is a
     * superset of the next, so resolution walks province -> village,
     * only testing candidates within the previously-resolved parent.
     */
    protected const LEVELS = ['province', 'regency', 'district', 'village'];

    public function __construct(protected ?RegionQuery $regionQuery = null)
    {
        $this->regionQuery ??= new RegionQuery;
    }

    /**
     * Reverse-geocode a coordinate to the region containing it, narrowing
     * province -> regency -> district -> village. Requires the `boundary`
     * column to be enabled (and populated) at every level up to and
     * including `$level` — it defaults to disabled per the package's
     * default config.
     *
     * @return Model|null
     *
     * @throws \InvalidArgumentException if `$level` is not a valid region level.
     * @throws \RuntimeException if `boundary` is not enabled at a required level, or is stored as a native spatial column on a driver without DB-side pushdown yet (SpatiaLite — MySQL, PostgreSQL/PostGIS, and SQL Server use real spatial containment, see `findContainingRegionSpatial()`).
     */
    public function findByCoordinate(float $lat, float $lng, string $level = 'village')
    {
        $targetIndex = array_search($level, self::LEVELS, true);

        if ($targetIndex === false) {
            throw new \InvalidArgumentException(
                "Invalid region level '{$level}'. Must be one of: ".implode(', ', self::LEVELS).'.'
            );
        }

        $parent = null;

        foreach (array_slice(self::LEVELS, 0, $targetIndex + 1) as $currentLevel) {
            $parent = $this->findContainingRegion($currentLevel, $lat, $lng, $parent);

            if ($parent === null) {
                return null;
            }
        }

        return $parent;
    }

    /**
     * @return Model|null
     */
    protected function findContainingRegion(string $level, float $lat, float $lng, ?Model $parent)
    {
        $modelClass = $this->modelClassForLevel($level);
        $tableKey = $this->tableKeyForLevel($level);
        $tableName = (new $modelClass)->getTable();
        $connectionName = (new $modelClass)->getConnectionName();

        $boundaryColumn = config("nusantara.columns.{$tableKey}.boundary.name", 'boundary');

        if (! Schema::connection($connectionName)->hasColumn($tableName, $boundaryColumn)) {
            throw new \RuntimeException(
                "Cannot geocode against '{$level}' — the '{$boundaryColumn}' column is not enabled on the '{$tableName}' table. ".
                "Enable it via config('nusantara.columns.{$tableKey}.boundary.enabled', true) and run migrations + nusantara:download-boundaries."
            );
        }

        $isSpatialColumn = SpatialColumn::isSpatial($connectionName, $tableName, $boundaryColumn);

        $query = $modelClass::query()->whereNotNull($boundaryColumn);

        if ($parent !== null) {
            $parentKeyColumn = $this->parentKeyColumnForLevel($level, $tableKey);
            $query->where($parentKeyColumn, $parent->getKey());
        }

        if ($isSpatialColumn) {
            return $this->findContainingRegionSpatial($query, $connectionName, $boundaryColumn, $lat, $lng, $level);
        }

        foreach ($query->get() as $candidate) {
            $coordinates = $this->extractBoundaryCoordinates($candidate, $boundaryColumn);

            if ($coordinates !== null && CoordinateGeometry::isPointInBoundary($lat, $lng, $coordinates)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * DB-side point-in-polygon containment for native spatial boundary columns
     * (decision 5b — pushdown, not decode-then-ray-cast, so the DB's spatial
     * index actually gets used). Point order is `(lng, lat)` here, matching
     * the write path's WKT/`ST_GeomFromText` convention — NOT this package's
     * `[lat, lng]` JSON storage convention used by the text-mode path above.
     *
     * @param  Builder<Model>  $query
     *
     * @throws \RuntimeException if the connection's driver has no pushdown implementation yet (SpatiaLite).
     */
    protected function findContainingRegionSpatial($query, string $connectionName, string $boundaryColumn, float $lat, float $lng, string $level): ?Model
    {
        $driver = DB::connection($connectionName)->getDriverName();

        // SRID must match the write path's convention per driver (DownloadBoundariesCommand::getSpatialExpressionPlaceholder())
        // — MySQL writes SRID 0, PostgreSQL/PostGIS and SQL Server write SRID 4326. A mismatch here doesn't throw,
        // it just silently returns zero matches.
        $containsSql = match ($driver) {
            'mysql' => "ST_Contains({$boundaryColumn}, ST_SRID(POINT(?, ?), 0))",
            'pgsql' => "ST_Contains({$boundaryColumn}, ST_SetSRID(ST_MakePoint(?, ?), 4326))",
            // Method-on-column syntax, not function-wrapping-column — SQL Server has no ST_Contains() function.
            'sqlsrv' => "{$boundaryColumn}.STContains(geometry::Point(?, ?, 4326)) = 1",
            default => null,
        };

        if ($containsSql === null) {
            throw new \RuntimeException(
                "findByCoordinate() does not yet support native spatial boundary columns on the '{$driver}' driver ".
                "(level '{$level}''s '{$boundaryColumn}' column is stored as a spatial type, e.g. via config('nusantara.boundaries.type', 'spatial')). ".
                'DB-side containment pushdown is currently only implemented for MySQL, PostgreSQL/PostGIS, and SQL Server.'
            );
        }

        return $query->whereRaw($containsSql, [$lng, $lat])->first();
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function extractBoundaryCoordinates(Model $candidate, string $boundaryColumn): ?array
    {
        $raw = $candidate->getRawOriginal($boundaryColumn);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return class-string<Model>
     */
    protected function modelClassForLevel(string $level): string
    {
        return match ($level) {
            'province' => $this->regionQuery->getProvinceModel(),
            'regency' => $this->regionQuery->getRegencyModel(),
            'district' => $this->regionQuery->getDistrictModel(),
            'village' => $this->regionQuery->getVillageModel(),
            default => throw new \InvalidArgumentException("Invalid region level '{$level}'."),
        };
    }

    protected function tableKeyForLevel(string $level): string
    {
        return match ($level) {
            'province' => 'provinces',
            'regency' => 'regencies',
            'district' => 'districts',
            'village' => 'villages',
            default => throw new \InvalidArgumentException("Invalid region level '{$level}'."),
        };
    }

    protected function parentKeyColumnForLevel(string $level, string $tableKey): string
    {
        $logicalKey = match ($level) {
            'regency' => 'province_id',
            'district' => 'regency_id',
            'village' => 'district_id',
            default => throw new \LogicException("Level '{$level}' has no parent key."),
        };

        return config("nusantara.columns.{$tableKey}.{$logicalKey}.name", $logicalKey);
    }
}
