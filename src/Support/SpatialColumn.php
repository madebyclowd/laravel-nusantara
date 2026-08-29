<?php

namespace MadeByClowd\Nusantara\Support;

use Illuminate\Support\Facades\Schema;

class SpatialColumn
{
    /**
     * Determine whether a column is stored as a native spatial type
     * (`geometry`/`geography`, e.g. via `config('nusantara.boundaries.type', 'spatial')`)
     * rather than plain text/JSON.
     */
    public static function isSpatial(?string $connection, string $table, string $column): bool
    {
        $type = strtolower(Schema::connection($connection)->getColumnType($table, $column));

        return str_contains($type, 'geometry') || str_contains($type, 'geography');
    }
}
