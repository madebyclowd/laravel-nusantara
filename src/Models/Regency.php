<?php

namespace MadeByClowd\Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Models\Concerns\HasDynamicNusantaraFields;

class Regency extends Model
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
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('nusantara.tables.regencies', 'regencies'));
        $this->setKeyName(config('nusantara.columns.regencies.id.name', 'id'));
        $this->setKeyType('string');
        $this->incrementing = false;

        $connectionName = config('nusantara.connection');
        if ($connectionName) {
            $this->setConnection($connectionName);
        }
    }

    /**
     * Get the logical table name key in configuration.
     *
     * @return string
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
        $provinceModel = config('nusantara.models.province', Province::class);
        $foreignKey = config('nusantara.columns.regencies.province_id.name', 'province_id');
        $ownerKey = config('nusantara.columns.provinces.id.name', 'id');

        return $this->belongsTo($provinceModel, $foreignKey, $ownerKey);
    }

    /**
     * Get the districts for the regency.
     */
    public function districts()
    {
        $districtModel = config('nusantara.models.district', District::class);
        $foreignKey = config('nusantara.columns.districts.regency_id.name', 'regency_id');
        $localKey = config('nusantara.columns.regencies.id.name', 'id');

        return $this->hasMany($districtModel, $foreignKey, $localKey);
    }

    /**
     * Get the villages for the regency through districts.
     */
    public function villages()
    {
        $villageModel = config('nusantara.models.village', Village::class);
        $districtModel = config('nusantara.models.district', District::class);

        $firstKey = config('nusantara.columns.districts.regency_id.name', 'regency_id');
        $secondKey = config('nusantara.columns.villages.district_id.name', 'district_id');
        $localKey = config('nusantara.columns.regencies.id.name', 'id');
        $secondLocalKey = config('nusantara.columns.districts.id.name', 'id');

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
