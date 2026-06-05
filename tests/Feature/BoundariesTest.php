<?php

namespace MadeByClowd\Nusantara\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Console\DownloadBoundariesCommand;
use MadeByClowd\Nusantara\Manifest;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Tests\TestCase;

class BoundariesTest extends TestCase
{
    /** @test */
    public function test_it_can_download_and_seed_boundaries_using_text_fallback()
    {
        // 1. Enable boundary columns and level config
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.boundaries.levels.provinces' => true]);
        config(['nusantara.boundaries.type' => 'text']);
        config(['nusantara.boundaries.verify_checksum' => true]);

        // 2. Prepare mock gzipped CSV data
        $csvData = "id,boundary\n11,\"[[[2.079098,97.077157],[2.090249,97.083166],[2.079098,97.077157]]]\"\n";
        $gzippedData = gzencode($csvData, 9);

        // Register hash in manifest
        Manifest::$hashes['provinces.csv.gz'] = hash('sha256', $gzippedData);

        // 3. Mock CDN response
        Http::fake([
            '*/provinces.csv.gz' => Http::response($gzippedData, 200),
        ]);

        // 4. Run migration and core seeder
        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Check that province exists but has no boundary
        $province = Province::find('11');
        $this->assertNotNull($province);
        $this->assertNull($province->boundary);

        // 5. Run boundary downloader CLI
        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->expectsOutputToContain('Processing level: provinces')
            ->assertExitCode(0);

        // 6. Verify database updated with the JSON coordinate string
        $province->refresh();
        $this->assertEquals('[[[2.079098,97.077157],[2.090249,97.083166],[2.079098,97.077157]]]', $province->boundary);
    }

    /** @test */
    public function test_it_converts_json_coordinates_to_wkt_correctly()
    {
        $command = new DownloadBoundariesCommand;
        $reflector = new \ReflectionClass($command);
        $method = $reflector->getMethod('jsonToWkt');
        $method->setAccessible(true);

        // Test Polygon conversion (lat, lng reversed to lng lat, and closed)
        $jsonPolygon = '[[[2.079098,97.077157],[2.090249,97.083166],[2.091450,97.098615]]]';
        $wktPolygon = $method->invoke($command, $jsonPolygon);
        $this->assertEquals(
            'POLYGON((97.077157 2.079098, 97.083166 2.090249, 97.098615 2.09145, 97.077157 2.079098))',
            $wktPolygon
        );

        // Test MultiPolygon conversion (each ring needs >= 3 distinct points for PostGIS)
        $jsonMultiPolygon = '[[[[2.079098,97.077157],[2.090249,97.083166],[2.09145,97.098615]],[[2.1,97.1],[2.2,97.2],[2.3,97.3]]]]';
        $wktMultiPolygon = $method->invoke($command, $jsonMultiPolygon);
        $this->assertEquals(
            'MULTIPOLYGON(((97.077157 2.079098, 97.083166 2.090249, 97.098615 2.09145, 97.077157 2.079098), (97.1 2.1, 97.2 2.2, 97.3 2.3, 97.1 2.1)))',
            $wktMultiPolygon
        );
    }

    /** @test */
    public function test_it_throws_security_exception_on_checksum_mismatch()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.boundaries.levels.provinces' => true]);
        config(['nusantara.boundaries.verify_checksum' => true]);

        // Register a wrong checksum in Manifest
        Manifest::$hashes['provinces.csv.gz'] = 'incorrect-sha256-hash';

        $csvData = "id,boundary\n11,\"[[[2.0,97.0]]]\"\n";
        $gzippedData = gzencode($csvData, 9);

        Http::fake([
            '*/provinces.csv.gz' => Http::response($gzippedData, 200),
        ]);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Security Exception: Hash verification failed');

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])->run();
    }

    /** @test */
    public function test_it_can_seed_from_local_path_bypassing_cdn()
    {
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.boundaries.levels.provinces' => true]);
        config(['nusantara.boundaries.verify_checksum' => true]);

        // Create a temporary local path
        $tempLocalDir = storage_path('app/temp-local-boundaries');
        if (! is_dir($tempLocalDir)) {
            mkdir($tempLocalDir, 0755, true);
        }

        $csvData = "id,boundary\n11,\"[[[2.0,97.0],[2.1,97.1],[2.0,97.0]]]\"\n";
        $gzippedData = gzencode($csvData, 9);
        $localFilePath = $tempLocalDir.'/provinces.csv.gz';
        file_put_contents($localFilePath, $gzippedData);

        // Configure local path
        config(['nusantara.boundaries.local_path' => $tempLocalDir]);

        // Register matching hash
        Manifest::$hashes['provinces.csv.gz'] = hash('sha256', $gzippedData);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->expectsOutputToContain('Processing level: provinces')
            ->assertExitCode(0);

        $province = Province::find('11');
        $this->assertEquals('[[[2.0,97.0],[2.1,97.1],[2.0,97.0]]]', $province->boundary);

        // Clean up
        unlink($localFilePath);
        rmdir($tempLocalDir);
    }

    /** @test */
    public function test_it_can_dynamically_add_boundary_column_if_missing_during_seeding()
    {
        // 1. Run migration with boundary disabled
        config(['nusantara.columns.provinces.boundary.enabled' => false]);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        // Assert table exists, but boundary column does NOT exist
        $this->assertFalse(Schema::hasColumn('provinces', 'boundary'));

        // 2. Now enable boundary config (mimicking developer changing their mind)
        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.boundaries.levels.provinces' => true]);
        config(['nusantara.boundaries.type' => 'text']);
        config(['nusantara.boundaries.verify_checksum' => true]);

        // Prepare mock download
        $csvData = "id,boundary\n11,\"[[[2.0,97.0]]]\"\n";
        $gzippedData = gzencode($csvData, 9);
        Manifest::$hashes['provinces.csv.gz'] = hash('sha256', $gzippedData);

        Http::fake([
            '*/provinces.csv.gz' => Http::response($gzippedData, 200),
        ]);

        // 3. Run download boundaries command (expects confirmation and adds column)
        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->expectsConfirmation("Would you like to add the 'boundary' column to table 'provinces' now?", 'yes')
            ->assertExitCode(0);

        // 4. Assert column was dynamically added and seeded
        $this->assertTrue(Schema::hasColumn('provinces', 'boundary'));
        $province = Province::find('11');
        $this->assertEquals('[[[2.0,97.0]]]', $province->boundary);
    }
}
