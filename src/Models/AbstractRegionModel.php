<?php

namespace MadeByClowd\Nusantara\Models;

use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Models\Concerns\HasDynamicNusantaraFields;

abstract class AbstractRegionModel extends Model
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

        $this->setTable($this->resolveTable($this->getLogicalTableName()));
        $this->setKeyName($this->resolveColumn($this->getLogicalTableName(), 'id'));
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
    abstract protected function getLogicalTableName(): string;

    /**
     * Resolve the configured model class for a region level.
     *
     * @param  class-string  $default
     * @return class-string
     */
    protected function resolveModel(string $configKey, string $default): string
    {
        return config("nusantara.models.{$configKey}", $default);
    }

    /**
     * Resolve the configured database column name for a logical column.
     */
    protected function resolveColumn(string $tableKey, string $column, ?string $default = null): string
    {
        return config("nusantara.columns.{$tableKey}.{$column}.name", $default ?? $column);
    }

    /**
     * Resolve the configured database table name for a logical table.
     */
    protected function resolveTable(string $tableKey): string
    {
        return config("nusantara.tables.{$tableKey}", $tableKey);
    }
}
