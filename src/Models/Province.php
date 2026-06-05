<?php

namespace MadeByClowd\Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Models\Concerns\HasDynamicNusantaraFields;

class Province extends Model
{
    use HasDynamicNusantaraFields;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Create a new Eloquent model instance.
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('nusantara.tables.provinces', 'provinces'));
        $this->setKeyName(config('nusantara.columns.provinces.id.name', 'id'));
        $this->setKeyType('string');
        $this->incrementing = false;

        $connectionName = config('nusantara.connection');
        if ($connectionName) {
            $this->setConnection($connectionName);
        }
    }

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
        $regencyModel = config('nusantara.models.regency', Regency::class);
        $foreignKey = config('nusantara.columns.regencies.province_id.name', 'province_id');
        $localKey = config('nusantara.columns.provinces.id.name', 'id');

        return $this->hasMany($regencyModel, $foreignKey, $localKey);
    }

    /**
     * Get the districts for the province through regencies.
     */
    public function districts()
    {
        $districtModel = config('nusantara.models.district', District::class);
        $regencyModel = config('nusantara.models.regency', Regency::class);

        $firstKey = config('nusantara.columns.regencies.province_id.name', 'province_id');
        $secondKey = config('nusantara.columns.districts.regency_id.name', 'regency_id');
        $localKey = config('nusantara.columns.provinces.id.name', 'id');
        $secondLocalKey = config('nusantara.columns.regencies.id.name', 'id');

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
        $villageModel = config('nusantara.models.village', Village::class);
        $districtTable = config('nusantara.tables.districts', 'districts');
        $regencyTable = config('nusantara.tables.regencies', 'regencies');
        $villageTable = config('nusantara.tables.villages', 'villages');

        $regencyIdCol = config('nusantara.columns.regencies.id.name', 'id');
        $regencyProvinceIdCol = config('nusantara.columns.regencies.province_id.name', 'province_id');

        $districtIdCol = config('nusantara.columns.districts.id.name', 'id');
        $districtRegencyIdCol = config('nusantara.columns.districts.regency_id.name', 'regency_id');

        $villageIdCol = config('nusantara.columns.villages.id.name', 'id');
        $villageDistrictIdCol = config('nusantara.columns.villages.district_id.name', 'district_id');

        return (new $villageModel)->newQuery()
            ->select("{$villageTable}.*")
            ->join($districtTable, "{$villageTable}.{$villageDistrictIdCol}", '=', "{$districtTable}.{$districtIdCol}")
            ->join($regencyTable, "{$districtTable}.{$districtRegencyIdCol}", '=', "{$regencyTable}.{$regencyIdCol}")
            ->where("{$regencyTable}.{$regencyProvinceIdCol}", '=', $this->getKey());
    }
}
