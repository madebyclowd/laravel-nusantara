<?php

namespace MadeByClowd\Nusantara\Models;

use MadeByClowd\Nusantara\Models\Concerns\HasGeoBoundary;

class District extends AbstractRegionModel
{
    use HasGeoBoundary;

    /**
     * Get the logical table name key in configuration.
     */
    protected function getLogicalTableName(): string
    {
        return 'districts';
    }

    /**
     * Get the regency that owns the district.
     */
    public function regency()
    {
        $regencyModel = $this->resolveModel('regency', Regency::class);
        $foreignKey = $this->resolveColumn('districts', 'regency_id');
        $ownerKey = $this->resolveColumn('regencies', 'id');

        return $this->belongsTo($regencyModel, $foreignKey, $ownerKey);
    }

    /**
     * Get the villages for the district.
     */
    public function villages()
    {
        $villageModel = $this->resolveModel('village', Village::class);
        $foreignKey = $this->resolveColumn('villages', 'district_id');
        $localKey = $this->resolveColumn('districts', 'id');

        return $this->hasMany($villageModel, $foreignKey, $localKey);
    }
}
