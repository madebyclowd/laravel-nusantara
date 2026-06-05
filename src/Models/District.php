<?php

namespace MadeByClowd\Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Models\Concerns\HasDynamicNusantaraFields;

class District extends Model
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

        $this->setTable(config('nusantara.tables.districts', 'districts'));
        $this->setKeyName(config('nusantara.columns.districts.id.name', 'id'));
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
        return 'districts';
    }

    /**
     * Get the regency that owns the district.
     */
    public function regency()
    {
        $regencyModel = config('nusantara.models.regency', Regency::class);
        $foreignKey = config('nusantara.columns.districts.regency_id.name', 'regency_id');
        $ownerKey = config('nusantara.columns.regencies.id.name', 'id');

        return $this->belongsTo($regencyModel, $foreignKey, $ownerKey);
    }

    /**
     * Get the villages for the district.
     */
    public function villages()
    {
        $villageModel = config('nusantara.models.village', Village::class);
        $foreignKey = config('nusantara.columns.villages.district_id.name', 'district_id');
        $localKey = config('nusantara.columns.districts.id.name', 'id');

        return $this->hasMany($villageModel, $foreignKey, $localKey);
    }
}
