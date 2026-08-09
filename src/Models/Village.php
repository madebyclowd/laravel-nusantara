<?php

namespace MadeByClowd\Nusantara\Models;

use MadeByClowd\Nusantara\Models\Concerns\HasGeoBoundary;

class Village extends AbstractRegionModel
{
    use HasGeoBoundary;

    /**
     * Get the logical table name key in configuration.
     */
    protected function getLogicalTableName(): string
    {
        return 'villages';
    }

    /**
     * Get the district that owns the village.
     */
    public function district()
    {
        $districtModel = $this->resolveModel('district', District::class);
        $foreignKey = $this->resolveColumn('villages', 'district_id');
        $ownerKey = $this->resolveColumn('districts', 'id');

        return $this->belongsTo($districtModel, $foreignKey, $ownerKey);
    }
}
