<?php

namespace MadeByClowd\Nusantara\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Support\CoordinateGeometry;

trait HasGeoBoundary
{
    /**
     * Convert this region to a GeoJSON Feature. Uses the `boundary` column
     * (Polygon/MultiPolygon) when enabled and populated, falling back to a
     * Point built from `latitude`/`longitude` otherwise — mirrors
     * py-nusantara's BaseRecord::to_geojson() fallback behavior.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if `boundary` is populated but stored as a native spatial column (not yet supported — see class docs, mirrors Geocoder::findByCoordinate()'s same limitation).
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
            if ($this->isSpatialBoundaryColumn($boundaryColumn)) {
                throw new \RuntimeException(
                    "toGeoJson() does not yet support native spatial boundary columns ('{$boundaryColumn}' on ".
                    "'{$this->getTable()}' is stored as a spatial type, e.g. via config('nusantara.boundaries.type', 'spatial')). ".
                    "Use text-mode boundary storage (config('nusantara.boundaries.type', 'text')) to read boundaries back as GeoJSON."
                );
            }

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $geometry = $this->coordinatesToGeoJsonGeometry($decoded);

                if ($geometry !== null) {
                    return $geometry;
                }
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

    protected function isSpatialBoundaryColumn(string $boundaryColumn): bool
    {
        $type = strtolower(Schema::connection($this->getConnectionName())->getColumnType($this->getTable(), $boundaryColumn));

        return str_contains($type, 'geometry') || str_contains($type, 'geography');
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
