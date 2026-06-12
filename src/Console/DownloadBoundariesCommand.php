<?php

namespace MadeByClowd\Nusantara\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MadeByClowd\Nusantara\Manifest;

class DownloadBoundariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nusantara:download-boundaries
                            {--level=all : The level to download (all, provinces, regencies, districts, villages)}
                            {--force : Overwrite existing boundaries in the database}
                            {--dry-run : Only download and verify checksums without writing to the database}
                            {--chunk=100 : The chunk size for batch updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and seed geographic boundary shapes from GitHub Releases / CDN';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $levelOption = strtolower($this->option('level'));
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $this->components->info('Starting geographic boundaries downloader...');
        Log::info("Nusantara boundaries downloader started. Level={$levelOption}, Force={$force}, DryRun={$dryRun}, ChunkSize={$chunkSize}");

        $levels = ['provinces', 'regencies', 'districts', 'villages'];
        if ($levelOption !== 'all') {
            if (! in_array($levelOption, $levels)) {
                $this->error("Invalid level: '{$levelOption}'. Allowed levels: all, ".implode(', ', $levels));

                return self::FAILURE;
            }
            $levels = [$levelOption];
        }

        // Filter levels based on config settings (unless forced via level option)
        if ($levelOption === 'all') {
            $levels = array_filter($levels, function ($lvl) {
                return config("nusantara.boundaries.levels.{$lvl}", false);
            });

            if (empty($levels)) {
                $this->warn('No boundary levels are enabled in config/nusantara.php.');
                $this->info('Please enable boundary levels under config key [nusantara.boundaries.levels] or run with --level={name}.');

                return self::SUCCESS;
            }
        }

        $connection = config('nusantara.connection');
        $driver = DB::connection($connection)->getDriverName();

        $storageType = config('nusantara.boundaries.type', 'spatial');
        if ($storageType === 'spatial' && ! $this->isSpatialSupported($driver, $connection)) {
            $this->warn("Database connection [{$driver}] does not support spatial operations or SpatiaLite is missing.");
            $this->info("Automatically falling back to 'text' storage type (JSON strings).");
            $storageType = 'text';
        }

        $tempDir = storage_path('app/nusantara-cache');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($levels as $level) {
            $this->info("Processing level: {$level}");
            if ($level === 'villages') {
                $this->processVillages($tempDir, $connection, $driver, $storageType, $force, $dryRun, $chunkSize);
            } else {
                $this->processStandardLevel($level, $tempDir, $connection, $driver, $storageType, $force, $dryRun, $chunkSize);
            }
        }

        $this->info('Laravel Nusantara boundaries seeding finished!');
        Log::info('Nusantara boundaries downloader completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Process standard levels: provinces, regencies, districts.
     */
    protected function processStandardLevel(string $level, string $tempDir, $connection, string $driver, string $storageType, bool $force, bool $dryRun, int $chunkSize): void
    {
        $filename = "{$level}.csv.gz";
        $this->info("Resolving {$filename}...");
        $filePath = $this->resolveFile($filename, $tempDir);

        if ($dryRun) {
            $this->info(" [Dry Run] Checksum verified successfully for {$filename}. Skipping database write.");

            return;
        }

        $this->seedBoundaryFile($filePath, $level, $connection, $driver, $storageType, $force, $chunkSize);

        if (str_starts_with($filePath, $tempDir) && file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Process sharded village boundary files.
     */
    protected function processVillages(string $tempDir, $connection, string $driver, string $storageType, bool $force, bool $dryRun, int $chunkSize): void
    {
        // Get all unique province IDs seeded in the database
        $provTable = config('nusantara.tables.provinces');
        $provIdCol = config('nusantara.columns.provinces.id.name');
        $provNameCol = config('nusantara.columns.provinces.name.name');

        $provinces = DB::connection($connection)->table($provTable)->pluck($provNameCol, $provIdCol)->toArray();

        if (empty($provinces)) {
            $this->warn("\nNo provinces found in database. Please run core seeder first.");

            return;
        }

        $this->info('Resolving and seeding village files...');

        foreach ($provinces as $provId => $provName) {
            $filename = "villages_{$provId}.csv.gz";

            // Check if file exists in manifest, some provinces might not have village shapefiles
            if (config('nusantara.boundaries.verify_checksum', true) && ! Manifest::get($filename)) {
                continue;
            }

            if ($dryRun) {
                try {
                    $this->resolveFile($filename, $tempDir);
                    $this->info(" [Dry Run] Checksum verified successfully for {$filename}.");
                } catch (\Exception $e) {
                    if (config('nusantara.boundaries.verify_checksum', true)) {
                        throw $e;
                    }
                }
                continue;
            }

            try {
                $filePath = $this->resolveFile($filename, $tempDir);
                $this->info("Seeding villages for {$provName} ({$provId})...");
                $this->seedBoundaryFile($filePath, 'villages', $connection, $driver, $storageType, $force, $chunkSize);

                if (str_starts_with($filePath, $tempDir) && file_exists($filePath)) {
                    unlink($filePath);
                }
            } catch (\Exception $e) {
                // If verify_checksum is false and download failed, it might be that the province has no village boundaries
                if (! config('nusantara.boundaries.verify_checksum', true)) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Resolve the boundary file locally or by downloading it.
     */
    protected function resolveFile(string $filename, string $tempDir): string
    {
        Log::info("Resolving boundary file: {$filename}");
        $localPath = config('nusantara.boundaries.local_path');

        if ($localPath) {
            $fullPath = rtrim($localPath, '/').'/'.$filename;
            if (! file_exists($fullPath)) {
                throw new \RuntimeException("Local boundary file not found at: {$fullPath}");
            }

            $this->verifyChecksum($filename, $fullPath);

            return $fullPath;
        }

        $tempFilePath = $tempDir.'/'.$filename;

        // Dynamic URL Resolution
        $cdnUrl = rtrim(config('nusantara.boundaries.cdn_url'), '/');
        $version = $this->getDataVersion();

        $url = "{$cdnUrl}/{$version}/{$filename}";

        $response = Http::withOptions([
            'sink' => $tempFilePath,
            'connect_timeout' => 10,
            'timeout' => 180,
        ])->get($url);

        if (! $response->successful()) {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            Log::error("Failed to download file {$filename} from URL: {$url}");
            throw new \RuntimeException("Failed to download file from URL: {$url}");
        }

        Log::info("Successfully downloaded file {$filename} from URL: {$url}");

        try {
            $this->verifyChecksum($filename, $tempFilePath);
        } catch (\Exception $e) {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            throw $e;
        }

        return $tempFilePath;
    }

    /**
     * Verify the SHA-256 checksum of the file.
     */
    protected function verifyChecksum(string $filename, string $filePath): void
    {
        if (! config('nusantara.boundaries.verify_checksum', true)) {
            return;
        }

        $expectedHash = Manifest::get($filename);
        if (! $expectedHash) {
            throw new \RuntimeException("Security Exception: Expected hash not found in manifest for file '{$filename}'.");
        }

        $actualHash = hash_file('sha256', $filePath);
        if ($actualHash !== $expectedHash) {
            Log::error("Security Exception: Hash verification failed for '{$filename}'. Expected: {$expectedHash}, Got: {$actualHash}");
            throw new \RuntimeException("Security Exception: Hash verification failed for '{$filename}'. Expected: {$expectedHash}, Got: {$actualHash}");
        }
        Log::info("Hash verification successful for '{$filename}'.");
    }

    /**
     * Stream CSV boundaries and update database.
     *
     * @return int The number of rows seeded.
     */
    protected function seedBoundaryFile(string $filePath, string $level, $connection, string $driver, string $storageType, bool $force, int $chunkSize, $progressBar = null): int
    {
        $tableName = config("nusantara.tables.{$level}");
        $idColName = config("nusantara.columns.{$level}.id.name");
        $boundaryColName = config("nusantara.columns.{$level}.boundary.name");

        $schema = DB::connection($connection)->getSchemaBuilder();
        Log::info("Seeding boundary file '{$filePath}' for level '{$level}' into table '{$tableName}'...");
        if (! $schema->hasColumn($tableName, $boundaryColName)) {
            throw new \RuntimeException("Aborted: Column '{$boundaryColName}' is missing from table '{$tableName}'. Please ensure boundary columns are enabled under 'config/nusantara.php' and migrations have been run.");
        }

        // Column exists — check for type mismatch between desired storage type and actual column type.
        $columnType = strtolower($schema->getColumnType($tableName, $boundaryColName));
        $isSpatialColumn = str_contains($columnType, 'geometry') || str_contains($columnType, 'geography');

        $needsUpgrade = $storageType === 'spatial' && ! $isSpatialColumn;
        $needsDowngrade = $storageType === 'text' && $isSpatialColumn;

        if ($needsUpgrade || $needsDowngrade) {
            $desiredType = $storageType === 'spatial' ? 'geometry' : 'text';
            throw new \RuntimeException("Aborted: Column '{$boundaryColName}' in table '{$tableName}' is type '{$columnType}' but desired storage is '{$desiredType}'. Please run migrations to update the column type.");
        }

        $handle = gzopen($filePath, 'r');
        $headers = fgetcsv($handle);

        if ($headers === false) {
            gzclose($handle);

            return 0;
        }

        $isLocalBar = false;
        if (! $progressBar) {
            $progressBar = $this->output->createProgressBar();
            $progressBar->start();
            $isLocalBar = true;
        }

        $seededCount = 0;
        $batch = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($headers) !== count($row)) {
                $progressBar->advance();

                continue;
            }

            $record = array_combine($headers, $row);
            $id = $record['id'] ?? null;
            $boundaryJson = $record['boundary'] ?? null;

            if (empty($id) || empty($boundaryJson)) {
                $progressBar->advance();

                continue;
            }

            // If not forced, skip updating if boundary already exists in database
            if (! $force) {
                $exists = DB::connection($connection)->table($tableName)
                    ->where($idColName, $id)
                    ->whereNotNull($boundaryColName)
                    ->exists();
                if ($exists) {
                    $progressBar->advance();

                    continue;
                }
            }

            if ($storageType === 'spatial') {
                $wkt = $this->jsonToWkt($boundaryJson);
                if ($wkt) {
                    $batch[$id] = $wkt;
                } else {
                    $progressBar->advance();
                }
            } else {
                $batch[$id] = $boundaryJson;
            }

            if (count($batch) >= $chunkSize) {
                $this->updateBatch($connection, $tableName, $idColName, $boundaryColName, $batch, $driver, $storageType);
                $progressBar->advance(count($batch));
                $seededCount += count($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            $this->updateBatch($connection, $tableName, $idColName, $boundaryColName, $batch, $driver, $storageType);
            $progressBar->advance(count($batch));
            $seededCount += count($batch);
        }

        if ($isLocalBar) {
            $progressBar->finish();
            $this->output->newLine();
        }

        gzclose($handle);

        Log::info("Successfully seeded {$seededCount} boundary records into table '{$tableName}'.");

        return $seededCount;
    }

    /**
     * Perform batch update inside transaction.
     */
    protected function updateBatch($connection, string $tableName, string $idCol, string $boundaryCol, array $batch, string $driver, string $storageType): void
    {
        DB::connection($connection)->transaction(function () use ($connection, $tableName, $idCol, $boundaryCol, $batch, $driver, $storageType) {
            if ($storageType === 'spatial') {
                $placeholder = $this->getSpatialExpressionPlaceholder($driver);
                foreach ($batch as $id => $val) {
                    DB::connection($connection)->update(
                        "UPDATE {$tableName} SET {$boundaryCol} = {$placeholder} WHERE {$idCol} = ?",
                        [$val, $id]
                    );
                }
            } else {
                foreach ($batch as $id => $val) {
                    DB::connection($connection)->table($tableName)
                        ->where($idCol, $id)
                        ->update([$boundaryCol => $val]);
                }
            }
        });
    }

    /**
     * Add the boundary geometry column to a table blueprint.
     * PostgreSQL uses an explicit SRID 4326; other drivers use the default geometry type.
     * MySQL requires NOT NULL for spatial indexes, so we only add spatial indexes on PostgreSQL.
     */
    protected function applyBoundaryColumn(Blueprint $table, string $colName, string $driver, string $storageType): void
    {
        if ($storageType === 'spatial') {
            if ($driver === 'pgsql') {
                $table->geometry($colName, 'GEOMETRY', 4326)->nullable();
            } else {
                $table->geometry($colName)->nullable();
            }

            if (config('nusantara.boundaries.spatial_index', true) && $driver === 'pgsql') {
                $table->spatialIndex($colName);
            }
        } else {
            $table->longText($colName)->nullable();
        }
    }

    /**
     * Get the spatial SQL expression placeholder depending on database driver.
     */
    protected function getSpatialExpressionPlaceholder(string $driver): string
    {
        if ($driver === 'sqlsrv') {
            return "geometry::STGeomFromText(?, 4326)";
        }

        if ($driver === 'pgsql') {
            return "ST_GeomFromText(?, 4326)";
        }

        // MySQL and SQLite SpatiaLite: use SRID 0 to avoid geographic axis-order enforcement.
        return "ST_GeomFromText(?)";
    }

    /**
     * Check if spatial geometry functions are supported in database connection.
     */
    protected function isSpatialSupported(string $driver, $connectionName): bool
    {
        if ($driver === 'sqlite') {
            try {
                DB::connection($connectionName)->select('SELECT spatialite_version()');

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        if ($driver === 'pgsql') {
            try {
                DB::connection($connectionName)->select('SELECT postgis_version()');

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the target dataset version tag.
     */
    protected function getDataVersion(): string
    {
        $configVersion = config('nusantara.boundaries.version');
        if ($configVersion) {
            return $configVersion;
        }

        $packageVersion = $this->getPackageVersion();

        // If package version is a development tag, fallback to v1.1.0 as the base data tag
        if ($packageVersion === 'v1.0.0' || str_starts_with($packageVersion, 'dev-')) {
            return 'v1.1.0';
        }

        // By default, since v1.1.0 is the release containing the assets,
        // we can default to it unless we explicitly want to follow package updates.
        return 'v1.1.0';
    }

    /**
     * Resolve package version.
     */
    protected function getPackageVersion(): string
    {
        try {
            if (class_exists(InstalledVersions::class)) {
                $version = InstalledVersions::getPrettyVersion('madebyclowd/laravel-nusantara');
                if ($version) {
                    if (str_starts_with($version, 'dev-')) {
                        return 'v1.0.0'; // Default tag fallback for dev branch
                    }

                    return $version;
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        $composerJsonPath = __DIR__.'/../../composer.json';
        if (file_exists($composerJsonPath)) {
            $composer = json_decode(file_get_contents($composerJsonPath), true);
            if (isset($composer['version'])) {
                return 'v'.ltrim($composer['version'], 'v');
            }
        }

        return 'v1.0.0'; // Fallback
    }

    /**
     * Convert JSON coordinate array to WKT format.
     */
    protected function jsonToWkt(string $json): ?string
    {
        $coords = json_decode($json, true);
        if (! is_array($coords) || empty($coords)) {
            return null;
        }

        $depth = $this->getArrayDepth($coords);

        if ($depth === 3) {
            // Single Polygon
            return $this->formatPolygonWkt($coords);
        }

        if ($depth === 4) {
            // MultiPolygon
            return $this->formatMultiPolygonWkt($coords);
        }

        return null;
    }

    /**
     * Get array nesting depth.
     */
    protected function getArrayDepth(array $array): int
    {
        $maxDepth = 1;
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }

        return $maxDepth;
    }

    /**
     * Format coordinates as a WKT POLYGON.
     */
    protected function formatPolygonWkt(array $polygon): ?string
    {
        $rings = [];
        foreach ($polygon as $ring) {
            if (! is_array($ring)) {
                continue;
            }
            $points = [];
            foreach ($ring as $coord) {
                if (is_array($coord) && count($coord) >= 2) {
                    $points[] = "{$coord[1]} {$coord[0]}"; // Longitude Latitude
                }
            }
            if (! empty($points)) {
                if ($points[0] !== end($points)) {
                    $points[] = $points[0];
                }
                // PostGIS requires at least 4 points (3 distinct + closing) per ring.
                // Skip degenerate rings that don't meet the minimum.
                if (count($points) < 4) {
                    continue;
                }
                $rings[] = '('.implode(', ', $points).')';
            }
        }

        // If no valid rings remain, the geometry is invalid — return null to skip.
        if (empty($rings)) {
            return null;
        }

        return 'POLYGON('.implode(', ', $rings).')';
    }

    /**
     * Format coordinates as a WKT MULTIPOLYGON.
     */
    protected function formatMultiPolygonWkt(array $multiPolygon): ?string
    {
        $polygons = [];
        foreach ($multiPolygon as $polygon) {
            if (! is_array($polygon)) {
                continue;
            }
            $rings = [];
            foreach ($polygon as $ring) {
                if (! is_array($ring)) {
                    continue;
                }
                $points = [];
                foreach ($ring as $coord) {
                    if (is_array($coord) && count($coord) >= 2) {
                        $points[] = "{$coord[1]} {$coord[0]}"; // Longitude Latitude
                    }
                }
                if (! empty($points)) {
                    if ($points[0] !== end($points)) {
                        $points[] = $points[0];
                    }
                    // PostGIS requires at least 4 points (3 distinct + closing) per ring.
                    // Skip degenerate rings that don't meet the minimum.
                    if (count($points) < 4) {
                        continue;
                    }
                    $rings[] = '('.implode(', ', $points).')';
                }
            }
            // Only include polygons that have at least one valid (exterior) ring.
            if (! empty($rings)) {
                $polygons[] = '('.implode(', ', $rings).')';
            }
        }

        // If no valid polygons remain, the geometry is invalid — return null to skip.
        if (empty($polygons)) {
            return null;
        }

        return 'MULTIPOLYGON('.implode(', ', $polygons).')';
    }

}
