<?php

namespace MadeByClowd\Nusantara\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NusantaraCoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = config('nusantara.connection');

        DB::connection($connection)->transaction(function () {
            $this->seedProvinces();
            $this->seedRegencies();
            $this->seedDistricts();
            $this->seedVillages();
        });
    }

    /**
     * Seed provinces data.
     */
    protected function seedProvinces(): void
    {
        $path = __DIR__.'/../../database/seeders/data/provinces.csv.gz';
        $this->streamCsv($path, 'provinces');
    }

    /**
     * Seed regencies data.
     */
    protected function seedRegencies(): void
    {
        $path = __DIR__.'/../../database/seeders/data/regencies.csv.gz';
        $this->streamCsv($path, 'regencies');
    }

    /**
     * Seed districts data.
     */
    protected function seedDistricts(): void
    {
        $path = __DIR__.'/../../database/seeders/data/districts.csv.gz';
        $this->streamCsv($path, 'districts');
    }

    /**
     * Seed villages data.
     */
    protected function seedVillages(): void
    {
        $files = glob(__DIR__.'/../../database/seeders/data/villages/villages_*.csv.gz');
        foreach ($files as $file) {
            $this->streamCsv($file, 'villages');
        }
    }

    /**
     * Streams CSV file and inserts data in chunks.
     *
     * @param  string  $filePath
     * @param  string  $tableKey
     * @param  int  $chunkSize
     * @return void
     */
    protected function streamCsv(string $filePath, string $tableKey, int $chunkSize = 500): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $tableName = config("nusantara.tables.{$tableKey}");
        $columnConfigs = config("nusantara.columns.{$tableKey}");
        $connection = config('nusantara.connection');

        $handle = gzopen($filePath, 'r');
        $headers = fgetcsv($handle);

        if ($headers === false) {
            gzclose($handle);
            return;
        }

        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Protect against empty or malformed rows
            if (count($headers) !== count($row)) {
                continue;
            }

            $record = array_combine($headers, $row);
            $dbRecord = [];

            foreach ($columnConfigs as $logicalName => $colConfig) {
                if (($colConfig['enabled'] ?? false) && array_key_exists($logicalName, $record)) {
                    $dbName = $colConfig['name'];
                    $val = $record[$logicalName];
                    $dbRecord[$dbName] = ($val === '') ? null : $val;
                }
            }

            $batch[] = $dbRecord;

            if (count($batch) >= $chunkSize) {
                DB::connection($connection)->table($tableName)->insert($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            DB::connection($connection)->table($tableName)->insert($batch);
        }

        gzclose($handle);
    }
}
