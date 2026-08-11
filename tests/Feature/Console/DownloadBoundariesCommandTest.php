<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Console\DownloadBoundariesCommand;
use MadeByClowd\Nusantara\Manifest;
use MadeByClowd\Nusantara\Tests\TestCase;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;

class DownloadBoundariesCommandTest extends TestCase
{
    protected string $tempDir;

    protected DownloadBoundariesCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nusantara.columns.provinces.boundary.enabled' => true]);
        config(['nusantara.columns.villages.boundary.enabled' => true]);

        $this->artisan('migrate:fresh')->run();
        DB::table(config('nusantara.tables.provinces'))->insert([
            'id' => '11',
            'name' => 'Aceh',
        ]);

        $this->tempDir = sys_get_temp_dir().'/nusantara-boundaries-test-'.uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        config(['nusantara.boundaries.local_path' => $this->tempDir]);
        config(['nusantara.boundaries.verify_checksum' => false]);
        config(['nusantara.boundaries.type' => 'text']);
        config(['nusantara.boundaries.levels' => [
            'provinces' => true,
            'regencies' => false,
            'districts' => false,
            'villages' => false,
        ]]);

        $this->command = new DownloadBoundariesCommand;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->tempDir);

        parent::tearDown();
    }

    protected function gzWrite(string $filename, string $content): string
    {
        $path = $this->tempDir.'/'.$filename;
        file_put_contents($path, gzencode($content));

        return $path;
    }

    protected function invoke(string $method, array $args)
    {
        $reflection = new \ReflectionMethod(DownloadBoundariesCommand::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->command, ...$args);
    }

    /** @test */
    public function test_it_fails_for_an_invalid_level()
    {
        $this->artisan('nusantara:download-boundaries', ['--level' => 'countries'])
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_returns_success_when_no_levels_are_enabled_in_config()
    {
        config(['nusantara.boundaries.levels' => [
            'provinces' => false,
            'regencies' => false,
            'districts' => false,
            'villages' => false,
        ]]);

        $this->artisan('nusantara:download-boundaries', ['--level' => 'all'])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_processes_a_standard_level_from_local_path_and_seeds_boundaries()
    {
        $this->gzWrite('provinces.csv.gz', "id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces', '--chunk' => 1])
            ->assertExitCode(0);

        $row = DB::table('provinces')->where('id', '11')->first();
        $this->assertNotNull($row->boundary);
    }

    /** @test */
    public function test_dry_run_verifies_without_writing_to_the_database()
    {
        $this->gzWrite('provinces.csv.gz', "id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces', '--dry-run' => true])
            ->assertExitCode(0);

        $row = DB::table('provinces')->where('id', '11')->first();
        $this->assertNull($row->boundary);
    }

    /** @test */
    public function test_it_does_not_overwrite_an_existing_boundary_unless_forced()
    {
        DB::table('provinces')->where('id', '11')->update(['boundary' => 'EXISTING']);

        $this->gzWrite('provinces.csv.gz', "id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->assertExitCode(0);

        $this->assertSame('EXISTING', DB::table('provinces')->where('id', '11')->value('boundary'));

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces', '--force' => true])
            ->assertExitCode(0);

        $this->assertNotSame('EXISTING', DB::table('provinces')->where('id', '11')->value('boundary'));
    }

    /** @test */
    public function test_it_downloads_and_seeds_via_http_when_local_path_is_not_configured()
    {
        config(['nusantara.boundaries.local_path' => null]);

        Http::fake(fn () => Http::response(
            gzencode("id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n"), 200
        ));

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->assertExitCode(0);

        $row = DB::table('provinces')->where('id', '11')->first();
        $this->assertNotNull($row->boundary);
    }

    /** @test */
    public function test_it_fails_when_the_download_response_is_not_successful()
    {
        config(['nusantara.boundaries.local_path' => null]);

        Http::fake(fn () => Http::response('', 404));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to download file from URL/');

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])->run();
    }

    /** @test */
    public function test_it_warns_and_returns_when_there_are_no_provinces_for_villages_level()
    {
        $this->artisan('migrate:fresh')->run();

        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages'])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_seeds_village_boundary_files_for_each_province()
    {
        $this->gzWrite('villages_11.csv.gz', "id,boundary\n1101012001,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages', '--chunk' => 1])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_skips_a_province_without_a_manifest_entry_when_verifying_checksums()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        // Province id '99' has no matching villages_99.csv.gz entry in the
        // manifest at all, so it's skipped before any file resolution happens.
        DB::table('provinces')->where('id', '11')->update(['id' => '99']);

        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages'])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_dry_run_village_level_silently_continues_on_missing_file_when_checksum_verification_is_disabled()
    {
        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages', '--dry-run' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_dry_run_village_level_rethrows_on_missing_file_when_checksum_verification_is_enabled()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Local boundary file not found/');

        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages', '--dry-run' => true])->run();
    }

    /** @test */
    public function test_village_level_silently_continues_on_missing_file_when_checksum_verification_is_disabled()
    {
        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages'])
            ->assertExitCode(0);
    }

    /** @test */
    public function test_resolve_file_throws_when_the_local_file_is_missing()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Local boundary file not found/');

        $this->invoke('resolveFile', ['missing.csv.gz', $this->tempDir]);
    }

    /** @test */
    public function test_verify_checksum_is_a_no_op_when_disabled()
    {
        config(['nusantara.boundaries.verify_checksum' => false]);

        $this->invoke('verifyChecksum', ['anything.csv.gz', $this->tempDir.'/does-not-matter']);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function test_verify_checksum_throws_when_the_manifest_has_no_hash()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        $path = $this->gzWrite('unknown-file.csv.gz', 'content');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected hash not found in manifest/');

        $this->invoke('verifyChecksum', ['unknown-file.csv.gz', $path]);
    }

    /** @test */
    public function test_verify_checksum_throws_when_the_hash_does_not_match()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        $path = $this->gzWrite('checksum-test.csv.gz', 'content');
        Manifest::$hashes['checksum-test.csv.gz'] = 'deadbeef';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/Hash verification failed/');

            $this->invoke('verifyChecksum', ['checksum-test.csv.gz', $path]);
        } finally {
            unset(Manifest::$hashes['checksum-test.csv.gz']);
        }
    }

    /** @test */
    public function test_verify_checksum_passes_when_the_hash_matches()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        $path = $this->gzWrite('checksum-ok.csv.gz', 'content');
        Manifest::$hashes['checksum-ok.csv.gz'] = hash_file('sha256', $path);

        try {
            $this->invoke('verifyChecksum', ['checksum-ok.csv.gz', $path]);
            $this->addToAssertionCount(1);
        } finally {
            unset(Manifest::$hashes['checksum-ok.csv.gz']);
        }
    }

    /** @test */
    public function test_seed_boundary_file_throws_when_the_boundary_column_is_missing()
    {
        config(['nusantara.columns.provinces.boundary.name' => 'does_not_exist']);

        $path = $this->gzWrite('provinces.csv.gz', "id,boundary\n11,[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/is missing from table/');

        $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'text', false, 500, new ProgressBar(new NullOutput)]);
    }

    /** @test */
    public function test_seed_boundary_file_throws_when_the_column_type_needs_upgrading_to_spatial()
    {
        $path = $this->gzWrite('provinces.csv.gz', "id,boundary\n11,[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/is type .* but desired storage is/');

        $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'spatial', false, 500, new ProgressBar(new NullOutput)]);
    }

    /** @test */
    public function test_seed_boundary_file_returns_zero_when_headers_are_missing()
    {
        $path = $this->gzWrite('provinces.csv.gz', '');

        $seeded = $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'text', false, 500, new ProgressBar(new NullOutput)]);

        $this->assertSame(0, $seeded);
    }

    /** @test */
    public function test_seed_boundary_file_skips_malformed_and_incomplete_rows()
    {
        $content = "id,boundary\n99,BadRow,Extra\n11,\n,[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\n";
        $path = $this->gzWrite('provinces.csv.gz', $content);

        $progressBar = new ProgressBar(new NullOutput);

        $seeded = $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'text', false, 500, $progressBar]);

        $this->assertSame(0, $seeded);
        $this->assertNull(DB::table('provinces')->where('id', '11')->value('boundary'));
    }

    /** @test */
    public function test_seed_boundary_file_writes_spatial_geometry_using_the_configured_placeholder()
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->geometry('geom_boundary')->nullable();
        });

        $pdo = DB::connection('testing')->getPdo();
        $pdo->sqliteCreateFunction('ST_GeomFromText', fn ($wkt) => $wkt, 1);

        config(['nusantara.columns.provinces.boundary.name' => 'geom_boundary']);

        $content = "id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n";
        $path = $this->gzWrite('provinces.csv.gz', $content);

        $progressBar = new ProgressBar(new NullOutput);

        $seeded = $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'spatial', true, 1, $progressBar]);

        $this->assertSame(1, $seeded);
        $this->assertStringStartsWith('POLYGON', DB::table('provinces')->where('id', '11')->value('geom_boundary'));
    }

    /** @test */
    public function test_seed_boundary_file_skips_a_row_whose_boundary_json_cannot_be_converted_to_wkt()
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->geometry('geom_boundary')->nullable();
        });

        $pdo = DB::connection('testing')->getPdo();
        $pdo->sqliteCreateFunction('ST_GeomFromText', fn ($wkt) => $wkt, 1);

        config(['nusantara.columns.provinces.boundary.name' => 'geom_boundary']);

        $content = "id,boundary\n11,not-json\n";
        $path = $this->gzWrite('provinces.csv.gz', $content);

        $progressBar = new ProgressBar(new NullOutput);

        $seeded = $this->invoke('seedBoundaryFile', [$path, 'provinces', 'testing', 'sqlite', 'spatial', true, 500, $progressBar]);

        $this->assertSame(0, $seeded);
        $this->assertNull(DB::table('provinces')->where('id', '11')->value('geom_boundary'));
    }

    /** @test */
    public function test_get_spatial_expression_placeholder_varies_by_driver()
    {
        $this->assertSame('geometry::STGeomFromText(?, 4326)', $this->invoke('getSpatialExpressionPlaceholder', ['sqlsrv']));
        $this->assertSame('ST_GeomFromText(?, 4326)', $this->invoke('getSpatialExpressionPlaceholder', ['pgsql']));
        $this->assertSame('ST_GeomFromText(?)', $this->invoke('getSpatialExpressionPlaceholder', ['sqlite']));
    }

    /** @test */
    public function test_is_spatial_supported_returns_false_for_sqlite_without_spatialite()
    {
        $this->assertFalse($this->invoke('isSpatialSupported', ['sqlite', 'testing']));
    }

    /** @test */
    public function test_is_spatial_supported_returns_true_by_default_for_other_drivers()
    {
        $this->assertTrue($this->invoke('isSpatialSupported', ['mysql', 'testing']));
    }

    /** @test */
    public function test_apply_boundary_column_builds_the_correct_column_definition_per_driver_and_storage_type()
    {
        // The pgsql branch's spatialIndex() call genuinely requires a real
        // PostgreSQL connection to execute (SQLite's grammar throws for
        // unsupported spatial indexes) — disable spatial_index so this test
        // still exercises the pgsql SRID-4326 column-type branch without
        // needing a live PostGIS connection in this SQLite-only test suite.
        config(['nusantara.boundaries.spatial_index' => false]);

        foreach ([['sqlite', 'spatial'], ['pgsql', 'spatial'], ['sqlite', 'text']] as [$driver, $storageType]) {
            Schema::connection('testing')->dropIfExists('tmp_boundary_test');

            Schema::connection('testing')->create('tmp_boundary_test', function (Blueprint $table) use ($driver, $storageType) {
                $table->id();
                $this->invoke('applyBoundaryColumn', [$table, 'boundary', $driver, $storageType]);
            });

            $this->assertTrue(Schema::connection('testing')->hasColumn('tmp_boundary_test', 'boundary'));
        }

        Schema::connection('testing')->dropIfExists('tmp_boundary_test');
    }

    /** @test */
    public function test_get_data_version_prefers_the_explicit_config_value()
    {
        config(['nusantara.boundaries.version' => 'v9.9.9']);

        $this->assertSame('v9.9.9', $this->invoke('getDataVersion', []));
    }

    /** @test */
    public function test_get_data_version_falls_back_to_the_default_tag_when_unset()
    {
        config(['nusantara.boundaries.version' => null]);

        $this->assertSame('v1.1.0', $this->invoke('getDataVersion', []));
    }

    /** @test */
    public function test_get_package_version_resolves_without_error()
    {
        $this->assertIsString($this->invoke('getPackageVersion', []));
    }

    /** @test */
    public function test_json_to_wkt_returns_null_for_invalid_input()
    {
        $this->assertNull($this->invoke('jsonToWkt', ['not-json']));
        $this->assertNull($this->invoke('jsonToWkt', ['[]']));
        $this->assertNull($this->invoke('jsonToWkt', ['[[0,0]]'])); // depth 2 -> neither polygon nor multipolygon
    }

    /** @test */
    public function test_json_to_wkt_formats_a_polygon()
    {
        $wkt = $this->invoke('jsonToWkt', ['[[[0,0],[0,10],[10,10],[10,0],[0,0]]]']);

        $this->assertStringStartsWith('POLYGON', $wkt);
    }

    /** @test */
    public function test_json_to_wkt_formats_a_multipolygon()
    {
        $wkt = $this->invoke('jsonToWkt', ['[[[[0,0],[0,10],[10,10],[10,0],[0,0]]],[[[20,20],[20,30],[30,30],[30,20],[20,20]]]]']);

        $this->assertStringStartsWith('MULTIPOLYGON', $wkt);
    }

    /** @test */
    public function test_format_polygon_wkt_skips_degenerate_rings_and_returns_null_when_none_remain()
    {
        // A ring with only 2 distinct points -> fewer than 4 points once closed -> skipped.
        $this->assertNull($this->invoke('formatPolygonWkt', [[[[0, 0], [0, 1]]]]));
    }

    /** @test */
    public function test_format_multi_polygon_wkt_skips_degenerate_polygons_and_returns_null_when_none_remain()
    {
        $this->assertNull($this->invoke('formatMultiPolygonWkt', [[[[[0, 0], [0, 1]]]]]));
    }

    /** @test */
    public function test_format_multi_polygon_wkt_formats_valid_polygons()
    {
        $wkt = $this->invoke('formatMultiPolygonWkt', [[
            [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]],
        ]]);

        $this->assertStringStartsWith('MULTIPOLYGON', $wkt);
    }

    /** @test */
    public function test_format_polygon_wkt_skips_a_non_array_ring()
    {
        $wkt = $this->invoke('formatPolygonWkt', [[
            [[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]],
            'not-a-ring',
        ]]);

        $this->assertStringStartsWith('POLYGON', $wkt);
    }

    /** @test */
    public function test_format_multi_polygon_wkt_skips_a_non_array_polygon_and_a_non_array_ring()
    {
        $wkt = $this->invoke('formatMultiPolygonWkt', [[
            'not-a-polygon',
            [
                'not-a-ring',
                [[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]],
            ],
        ]]);

        $this->assertStringStartsWith('MULTIPOLYGON', $wkt);
    }

    /** @test */
    public function test_it_falls_back_to_text_storage_and_creates_the_cache_directory_when_spatial_is_unsupported()
    {
        config(['nusantara.boundaries.type' => 'spatial']);

        $cacheDir = storage_path('app/nusantara-cache');
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($cacheDir);
        }

        $this->gzWrite('provinces.csv.gz', "id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])
            ->assertExitCode(0);

        $this->assertDirectoryExists($cacheDir);
    }

    /** @test */
    public function test_dry_run_village_level_reports_success_when_the_local_file_and_checksum_are_valid()
    {
        $path = $this->gzWrite('villages_11.csv.gz', "id,boundary\n1101012001,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n");

        config(['nusantara.boundaries.verify_checksum' => true]);
        $originalHash = Manifest::$hashes['villages_11.csv.gz'];
        Manifest::$hashes['villages_11.csv.gz'] = hash_file('sha256', $path);

        try {
            $this->artisan('nusantara:download-boundaries', ['--level' => 'villages', '--dry-run' => true])
                ->assertExitCode(0);
        } finally {
            Manifest::$hashes['villages_11.csv.gz'] = $originalHash;
        }
    }

    /** @test */
    public function test_village_level_rethrows_on_missing_file_when_checksum_verification_is_enabled()
    {
        config(['nusantara.boundaries.verify_checksum' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Local boundary file not found/');

        $this->artisan('nusantara:download-boundaries', ['--level' => 'villages'])->run();
    }

    /** @test */
    public function test_resolve_file_deletes_the_temp_file_and_rethrows_when_the_downloaded_checksum_does_not_match()
    {
        config(['nusantara.boundaries.local_path' => null]);
        config(['nusantara.boundaries.verify_checksum' => true]);

        Http::fake(fn () => Http::response(
            gzencode("id,boundary\n11,\"[[[0,0],[0,10],[10,10],[10,0],[0,0]]]\"\n"), 200
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Hash verification failed/');

        $this->artisan('nusantara:download-boundaries', ['--level' => 'provinces'])->run();
    }

    /** @test */
    public function test_is_spatial_supported_returns_true_when_the_probe_query_succeeds()
    {
        $pdo = DB::connection('testing')->getPdo();
        $pdo->sqliteCreateFunction('spatialite_version', fn () => '5.0', 0);
        $pdo->sqliteCreateFunction('postgis_version', fn () => '3.0', 0);

        $this->assertTrue($this->invoke('isSpatialSupported', ['sqlite', 'testing']));
        $this->assertTrue($this->invoke('isSpatialSupported', ['pgsql', 'testing']));
    }

    /** @test */
    public function test_is_spatial_supported_returns_false_when_the_pgsql_probe_query_fails()
    {
        $this->assertFalse($this->invoke('isSpatialSupported', ['pgsql', 'testing']));
    }

    /** @test */
    public function test_get_data_version_defaults_to_the_base_tag_for_a_real_release_version()
    {
        config(['nusantara.boundaries.version' => null]);

        $command = new TaggedDownloadBoundariesCommand;
        $method = new \ReflectionMethod(DownloadBoundariesCommand::class, 'getDataVersion');
        $method->setAccessible(true);

        $this->assertSame('v1.1.0', $method->invoke($command));
    }
}

class TaggedDownloadBoundariesCommand extends DownloadBoundariesCommand
{
    protected function getPackageVersion(): string
    {
        return 'v3.2.1';
    }
}
