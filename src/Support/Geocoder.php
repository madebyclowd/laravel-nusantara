<?php

namespace MadeByClowd\Nusantara\Support;

use Illuminate\Database\Eloquent\Model;
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
     * @throws \RuntimeException if `boundary` is not enabled at a required level, or is stored as a native spatial column (not yet supported by this method — see class docs).
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

        $isSpatialColumn = $this->isSpatialColumn($connectionName, $tableName, $boundaryColumn);

        $query = $modelClass::query()->whereNotNull($boundaryColumn);

        if ($parent !== null) {
            $parentKeyColumn = $this->parentKeyColumnForLevel($level, $tableKey);
            $query->where($parentKeyColumn, $parent->getKey());
        }

        foreach ($query->get() as $candidate) {
            $coordinates = $this->extractBoundaryCoordinates($candidate, $boundaryColumn, $isSpatialColumn, $level);

            if ($coordinates !== null && CoordinateGeometry::isPointInBoundary($lat, $lng, $coordinates)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function extractBoundaryCoordinates(Model $candidate, string $boundaryColumn, bool $isSpatialColumn, string $level): ?array
    {
        if ($isSpatialColumn) {
            throw new \RuntimeException(
                "findByCoordinate() does not yet support native spatial boundary columns (level '{$level}''s ".
                "'{$boundaryColumn}' column is stored as a spatial type, e.g. via config('nusantara.boundaries.type', 'spatial')). ".
                "Use text-mode boundary storage (config('nusantara.boundaries.type', 'text')) for coordinate-based lookups."
            );
        }

        $raw = $candidate->getRawOriginal($boundaryColumn);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function isSpatialColumn(?string $connection, string $table, string $column): bool
    {
        $type = strtolower(Schema::connection($connection)->getColumnType($table, $column));

        return str_contains($type, 'geometry') || str_contains($type, 'geography');
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
