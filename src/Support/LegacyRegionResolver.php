<?php

namespace MadeByClowd\Nusantara\Support;

use MadeByClowd\Nusantara\Support\Data\HistoricalRegionMap;

class LegacyRegionResolver
{
    /**
     * Resolve a legacy regency, district, or village ID to its current
     * active ID. Matches on the first 4 digits (the regency-level prefix)
     * and substitutes only that prefix, preserving any district/village
     * suffix digits unchanged.
     *
     * Province IDs (2 digits) always pass through unchanged — they're too
     * short to contain a remappable regency prefix, and no historical split
     * has ever changed a province's own 2-digit code.
     */
    public function resolveLegacyId(string $regionId): string
    {
        if (strlen($regionId) < 4) {
            return $regionId;
        }

        $prefix = substr($regionId, 0, 4);

        if (isset(HistoricalRegionMap::$regencies[$prefix])) {
            return HistoricalRegionMap::$regencies[$prefix].substr($regionId, 4);
        }

        return $regionId;
    }
}
