<?php

namespace MadeByClowd\Nusantara\Models;

class Province extends AbstractRegionModel
{
    /**
     * Get the logical table name key in configuration.
     */
    protected function getLogicalTableName(): string
    {
        return 'provinces';
    }

    /**
     * Get the regencies for the province.
     */
    public function regencies()
    {
        $regencyModel = $this->resolveModel('regency', Regency::class);
        $foreignKey = $this->resolveColumn('regencies', 'province_id');
        $localKey = $this->resolveColumn('provinces', 'id');

        return $this->hasMany($regencyModel, $foreignKey, $localKey);
    }

    /**
     * Get the districts for the province through regencies.
     */
    public function districts()
    {
        $districtModel = $this->resolveModel('district', District::class);
        $regencyModel = $this->resolveModel('regency', Regency::class);

        $firstKey = $this->resolveColumn('regencies', 'province_id');
        $secondKey = $this->resolveColumn('districts', 'regency_id');
        $localKey = $this->resolveColumn('provinces', 'id');
        $secondLocalKey = $this->resolveColumn('regencies', 'id');

        return $this->hasManyThrough(
            $districtModel,
            $regencyModel,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey
        );
    }

    /**
     * Get all villages belonging to this province.
     * Returns a query builder instance with join conditions.
     */
    public function villages()
    {
        $villageModel = $this->resolveModel('village', Village::class);
        $districtTable = $this->resolveTable('districts');
        $regencyTable = $this->resolveTable('regencies');
        $villageTable = $this->resolveTable('villages');

        $regencyIdCol = $this->resolveColumn('regencies', 'id');
        $regencyProvinceIdCol = $this->resolveColumn('regencies', 'province_id');

        $districtIdCol = $this->resolveColumn('districts', 'id');
        $districtRegencyIdCol = $this->resolveColumn('districts', 'regency_id');

        $villageDistrictIdCol = $this->resolveColumn('villages', 'district_id');

        return (new $villageModel)->newQuery()
            ->select("{$villageTable}.*")
            ->join($districtTable, "{$villageTable}.{$villageDistrictIdCol}", '=', "{$districtTable}.{$districtIdCol}")
            ->join($regencyTable, "{$districtTable}.{$districtRegencyIdCol}", '=', "{$regencyTable}.{$regencyIdCol}")
            ->where("{$regencyTable}.{$regencyProvinceIdCol}", '=', $this->getKey());
    }
}
