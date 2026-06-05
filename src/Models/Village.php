<?php

namespace MadeByClowd\Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Models\Concerns\HasDynamicNusantaraFields;

class Village extends Model
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

        $this->setTable(config('nusantara.tables.villages', 'villages'));
        $this->setKeyName(config('nusantara.columns.villages.id.name', 'id'));
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
        return 'villages';
    }

    /**
     * Get the district that owns the village.
     */
    public function district()
    {
        $districtModel = config('nusantara.models.district', District::class);
        $foreignKey = config('nusantara.columns.villages.district_id.name', 'district_id');
        $ownerKey = config('nusantara.columns.districts.id.name', 'id');

        return $this->belongsTo($districtModel, $foreignKey, $ownerKey);
    }
}
