<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Region levels whose `name` column gets a search-supporting index.
     * Bare `name` lookups already benefit (prefix LIKE in searchFuzzy()),
     * and it's a prerequisite for any future full-text search upgrade.
     */
    protected const LEVELS = ['provinces', 'regencies', 'districts', 'villages'];

    /**
     * Get the database connection for the migration.
     *
     * @return string|null
     */
    public function getConnection()
    {
        return config('nusantara.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = config('nusantara.tables');
        $columns = config('nusantara.columns');

        foreach (self::LEVELS as $tableKey) {
            $nameColumn = $columns[$tableKey]['name']['name'];

            Schema::connection($this->getConnection())->table($tables[$tableKey], function (Blueprint $table) use ($nameColumn) {
                $table->index($nameColumn);
            });
        }

        $postalCodeCol = $columns['villages']['postal_code'] ?? null;

        if ($postalCodeCol && ($postalCodeCol['enabled'] ?? false)) {
            Schema::connection($this->getConnection())->table($tables['villages'], function (Blueprint $table) use ($postalCodeCol) {
                $table->index($postalCodeCol['name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = config('nusantara.tables');
        $columns = config('nusantara.columns');

        foreach (self::LEVELS as $tableKey) {
            $nameColumn = $columns[$tableKey]['name']['name'];

            Schema::connection($this->getConnection())->table($tables[$tableKey], function (Blueprint $table) use ($nameColumn) {
                $table->dropIndex([$nameColumn]);
            });
        }

        $postalCodeCol = $columns['villages']['postal_code'] ?? null;

        if ($postalCodeCol && ($postalCodeCol['enabled'] ?? false)) {
            Schema::connection($this->getConnection())->table($tables['villages'], function (Blueprint $table) use ($postalCodeCol) {
                $table->dropIndex([$postalCodeCol['name']]);
            });
        }
    }
};
