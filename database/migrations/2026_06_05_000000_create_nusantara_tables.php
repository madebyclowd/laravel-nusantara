<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
        $this->validateConfig();

        $tables = config('nusantara.tables');
        $columns = config('nusantara.columns');
        $enableForeignKeys = config('nusantara.enable_foreign_keys', true);

        // 1. Provinces Table
        Schema::connection($this->getConnection())->create($tables['provinces'], function (Blueprint $table) use ($columns) {
            $cols = $columns['provinces'];

            $table->string($cols['id']['name'], 2)->primary();
            $table->string($cols['name']['name'], 100);

            if ($this->isColEnabled($cols, 'capital')) {
                $table->string($cols['capital']['name'], 100)->nullable();
            }
            if ($this->isColEnabled($cols, 'latitude')) {
                $table->double($cols['latitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'longitude')) {
                $table->double($cols['longitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'elevation')) {
                $table->float($cols['elevation']['name'])->default(0);
            }
            if ($this->isColEnabled($cols, 'timezone')) {
                $table->tinyInteger($cols['timezone']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'area')) {
                $table->double($cols['area']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'population')) {
                $table->double($cols['population']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'boundary')) {
                $table->longText($cols['boundary']['name'])->nullable();
            }
        });

        // 2. Regencies Table
        Schema::connection($this->getConnection())->create($tables['regencies'], function (Blueprint $table) use ($tables, $columns, $enableForeignKeys) {
            $cols = $columns['regencies'];

            $table->string($cols['id']['name'], 4)->primary();
            $table->string($cols['province_id']['name'], 2)->index();
            $table->string($cols['name']['name'], 100);

            if ($this->isColEnabled($cols, 'capital')) {
                $table->string($cols['capital']['name'], 100)->nullable();
            }
            if ($this->isColEnabled($cols, 'latitude')) {
                $table->double($cols['latitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'longitude')) {
                $table->double($cols['longitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'elevation')) {
                $table->float($cols['elevation']['name'])->default(0);
            }
            if ($this->isColEnabled($cols, 'timezone')) {
                $table->tinyInteger($cols['timezone']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'area')) {
                $table->double($cols['area']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'population')) {
                $table->double($cols['population']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'boundary')) {
                $table->longText($cols['boundary']['name'])->nullable();
            }

            if ($enableForeignKeys) {
                $table->foreign($cols['province_id']['name'])
                    ->references($columns['provinces']['id']['name'])
                    ->on($tables['provinces'])
                    ->onDelete('cascade');
            }
        });

        // 3. Districts Table
        Schema::connection($this->getConnection())->create($tables['districts'], function (Blueprint $table) use ($tables, $columns, $enableForeignKeys) {
            $cols = $columns['districts'];

            $table->string($cols['id']['name'], 6)->primary();
            $table->string($cols['regency_id']['name'], 4)->index();
            $table->string($cols['name']['name'], 100);

            if ($this->isColEnabled($cols, 'latitude')) {
                $table->double($cols['latitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'longitude')) {
                $table->double($cols['longitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'boundary')) {
                $table->longText($cols['boundary']['name'])->nullable();
            }

            if ($enableForeignKeys) {
                $table->foreign($cols['regency_id']['name'])
                    ->references($columns['regencies']['id']['name'])
                    ->on($tables['regencies'])
                    ->onDelete('cascade');
            }
        });

        // 4. Villages Table
        Schema::connection($this->getConnection())->create($tables['villages'], function (Blueprint $table) use ($tables, $columns, $enableForeignKeys) {
            $cols = $columns['villages'];

            $table->string($cols['id']['name'], 10)->primary();
            $table->string($cols['district_id']['name'], 6)->index();
            $table->string($cols['name']['name'], 100);

            if ($this->isColEnabled($cols, 'postal_code')) {
                $table->string($cols['postal_code']['name'], 5)->nullable();
            }
            if ($this->isColEnabled($cols, 'latitude')) {
                $table->double($cols['latitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'longitude')) {
                $table->double($cols['longitude']['name'])->nullable();
            }
            if ($this->isColEnabled($cols, 'boundary')) {
                $table->longText($cols['boundary']['name'])->nullable();
            }

            if ($enableForeignKeys) {
                $table->foreign($cols['district_id']['name'])
                    ->references($columns['districts']['id']['name'])
                    ->on($tables['districts'])
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = config('nusantara.tables');

        Schema::connection($this->getConnection())->dropIfExists($tables['villages']);
        Schema::connection($this->getConnection())->dropIfExists($tables['districts']);
        Schema::connection($this->getConnection())->dropIfExists($tables['regencies']);
        Schema::connection($this->getConnection())->dropIfExists($tables['provinces']);
    }

    /**
     * Helper to verify if column config exists and is enabled.
     */
    protected function isColEnabled(array $cols, string $key): bool
    {
        return isset($cols[$key]) && ($cols[$key]['enabled'] ?? false);
    }

    /**
     * Validate that the essential primary and foreign keys are enabled.
     *
     * @throws RuntimeException
     */
    protected function validateConfig(): void
    {
        $requiredKeys = [
            'provinces' => ['id', 'name'],
            'regencies' => ['id', 'province_id', 'name'],
            'districts' => ['id', 'regency_id', 'name'],
            'villages' => ['id', 'district_id', 'name'],
        ];

        foreach ($requiredKeys as $table => $keys) {
            foreach ($keys as $key) {
                $colConfig = config("nusantara.columns.{$table}.{$key}");
                if (! $colConfig || ! ($colConfig['enabled'] ?? false)) {
                    throw new RuntimeException("Configuration error: Column '{$key}' in '{$table}' is required and must be enabled.");
                }
                if (empty($colConfig['name'])) {
                    throw new RuntimeException("Configuration error: Column '{$key}' in '{$table}' must have a valid non-empty 'name' configured.");
                }
            }
        }
    }
};
