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

        $progressBar = null;
        if ($this->command) {
            $this->command->info('Seeding provinces...');
            $totalRows = $this->countGzLines($path);
            $progressBar = $this->command->getOutput()->createProgressBar($totalRows);
            $progressBar->start();
        }

        $this->streamCsv($path, 'provinces', 500, $progressBar);

        if ($progressBar) {
            $progressBar->finish();
            $this->command->getOutput()->newLine();
        }
    }

    /**
     * Seed regencies data.
     */
    protected function seedRegencies(): void
    {
        $path = __DIR__.'/../../database/seeders/data/regencies.csv.gz';

        $progressBar = null;
        if ($this->command) {
            $this->command->info('Seeding regencies...');
            $totalRows = $this->countGzLines($path);
            $progressBar = $this->command->getOutput()->createProgressBar($totalRows);
            $progressBar->start();
        }

        $this->streamCsv($path, 'regencies', 500, $progressBar);

        if ($progressBar) {
            $progressBar->finish();
            $this->command->getOutput()->newLine();
        }
    }

    /**
     * Seed districts data.
     */
    protected function seedDistricts(): void
    {
        $path = __DIR__.'/../../database/seeders/data/districts.csv.gz';

        $progressBar = null;
        if ($this->command) {
            $this->command->info('Seeding districts...');
            $totalRows = $this->countGzLines($path);
            $progressBar = $this->command->getOutput()->createProgressBar($totalRows);
            $progressBar->start();
        }

        $this->streamCsv($path, 'districts', 500, $progressBar);

        if ($progressBar) {
            $progressBar->finish();
            $this->command->getOutput()->newLine();
        }
    }

    /**
     * Seed villages data.
     */
    protected function seedVillages(): void
    {
        $files = glob(__DIR__.'/../../database/seeders/data/villages/villages_*.csv.gz');

        $progressBar = null;
        if ($this->command) {
            $this->command->info('Seeding villages...');
            $totalRows = 0;
            foreach ($files as $file) {
                $totalRows += $this->countGzLines($file);
            }
            $progressBar = $this->command->getOutput()->createProgressBar($totalRows);
            $progressBar->start();
        }

        foreach ($files as $file) {
            $this->streamCsv($file, 'villages', 500, $progressBar);
        }

        if ($progressBar) {
            $progressBar->finish();
            $this->command->getOutput()->newLine();
        }
    }

    /**
     * Streams CSV file and inserts data in chunks.
     */
    protected function streamCsv(string $filePath, string $tableKey, int $chunkSize = 500, $progressBar = null): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $tableName = config("nusantara.tables.{$tableKey}");
        $columnConfigs = config("nusantara.columns.{$tableKey}");
        $connection = config('nusantara.connection');
        $driver = DB::connection($connection)->getDriverName();

        $handle = gzopen($filePath, 'r');
        $headers = fgetcsv($handle);

        if ($headers === false) {
            gzclose($handle);

            return;
        }

        $batch = [];
        $effectiveChunkSize = $chunkSize;

        while (($row = fgetcsv($handle)) !== false) {
            // Protect against empty or malformed rows
            if (count($headers) !== count($row)) {
                if ($progressBar) {
                    $progressBar->advance();
                }
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

            // SQL Server supports a maximum of 2100 parameters per statement.
            // Recalculate the effective chunk size once we know the column count.
            if ($effectiveChunkSize === $chunkSize && ! empty($dbRecord) && $driver === 'sqlsrv') {
                $colCount = count($dbRecord);
                $effectiveChunkSize = $colCount > 0 ? (int) floor(2000 / $colCount) : $chunkSize;
                $effectiveChunkSize = max(1, $effectiveChunkSize);
            }

            $batch[] = $dbRecord;

            if (count($batch) >= $effectiveChunkSize) {
                DB::connection($connection)->table($tableName)->insert($batch);
                if ($progressBar) {
                    $progressBar->advance(count($batch));
                }
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            DB::connection($connection)->table($tableName)->insert($batch);
            if ($progressBar) {
                $progressBar->advance(count($batch));
            }
        }

        gzclose($handle);
    }

    /**
     * Count the number of rows (excluding header) in a gzip file.
     */
    protected function countGzLines(string $filePath): int
    {
        if (! file_exists($filePath)) {
            return 0;
        }
        $handle = gzopen($filePath, 'r');
        $count = 0;
        while (gzgets($handle) !== false) {
            $count++;
        }
        gzclose($handle);

        return max(0, $count - 1); // Exclude header
    }
}
