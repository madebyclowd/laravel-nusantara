<?php

namespace MadeByClowd\Nusantara\Models;

use MadeByClowd\Nusantara\Models\Concerns\HasGeoBoundary;

class Regency extends AbstractRegionModel
{
    use HasGeoBoundary;

    /**
     * Get the logical table name key in configuration.
     */
    protected function getLogicalTableName(): string
    {
        return 'regencies';
    }

    /**
     * Get the province that owns the regency.
     */
    public function province()
    {
        $provinceModel = $this->resolveModel('province', Province::class);
        $foreignKey = $this->resolveColumn('regencies', 'province_id');
        $ownerKey = $this->resolveColumn('provinces', 'id');

        return $this->belongsTo($provinceModel, $foreignKey, $ownerKey);
    }

    /**
     * Get the districts for the regency.
     */
    public function districts()
    {
        $districtModel = $this->resolveModel('district', District::class);
        $foreignKey = $this->resolveColumn('districts', 'regency_id');
        $localKey = $this->resolveColumn('regencies', 'id');

        return $this->hasMany($districtModel, $foreignKey, $localKey);
    }

    /**
     * Get the villages for the regency through districts.
     */
    public function villages()
    {
        $villageModel = $this->resolveModel('village', Village::class);
        $districtModel = $this->resolveModel('district', District::class);

        $firstKey = $this->resolveColumn('districts', 'regency_id');
        $secondKey = $this->resolveColumn('villages', 'district_id');
        $localKey = $this->resolveColumn('regencies', 'id');
        $secondLocalKey = $this->resolveColumn('districts', 'id');

        return $this->hasManyThrough(
            $villageModel,
            $districtModel,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }
}
