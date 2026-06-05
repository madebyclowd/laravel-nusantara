<?php

namespace MadeByClowd\Nusantara\Models\Concerns;

trait HasDynamicNusantaraFields
{
    /**
     * Get the logical table name key in configuration.
     *
     * @return string
     */
    abstract protected function getLogicalTableName(): string;

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $resolvedKey = $this->resolveDbFieldName($key);

        if ($resolvedKey && $resolvedKey !== $key) {
            return parent::getAttribute($resolvedKey);
        }

        return parent::getAttribute($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        $resolvedKey = $this->resolveDbFieldName($key);

        if ($resolvedKey && $resolvedKey !== $key) {
            return parent::setAttribute($resolvedKey, $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        $resolvedKey = $this->resolveDbFieldName($key);

        if ($resolvedKey && $resolvedKey !== $key) {
            return parent::__isset($resolvedKey);
        }

        return parent::__isset($key);
    }

    /**
     * Resolve the logical field name to the actual database column name.
     *
     * @param  string  $key
     * @return string|null
     */
    protected function resolveDbFieldName(string $key): ?string
    {
        $tableKey = $this->getLogicalTableName();
        $configKey = "nusantara.columns.{$tableKey}.{$key}.name";

        if (config()->has($configKey)) {
            return config($configKey);
        }

        return $key;
    }
}
