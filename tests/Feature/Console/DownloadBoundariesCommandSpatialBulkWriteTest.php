<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Console\DownloadBoundariesCommand;
use MadeByClowd\Nusantara\Tests\TestCase;

/**
 * Verifies the Phase 08 bulk `CASE`/`UPDATE` write (audit-004 fix) against
 * real MySQL, PostgreSQL/PostGIS, and SQL Server instances — connects to
 * the local docker containers this dev environment already runs
 * (mysql-container / postgres-container / compose.spatial.yaml's mssql
 * service). Skips gracefully wherever those containers aren't reachable,
 * same pattern as `GeocoderSpatialPushdownTest`.
 */
class DownloadBoundariesCommandSpatialBulkWriteTest extends TestCase
{
    protected function connectOrSkip(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config]);

        try {
            DB::connection($name)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped("[{$name}] not reachable in this environment: {$e->getMessage()}");
        }
    }

    protected function invoke(string $method, array $args)
    {
        $reflection = new \ReflectionMethod(DownloadBoundariesCommand::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new DownloadBoundariesCommand, ...$args);
    }

    /** @test */
    public function test_mysql_bulk_update_writes_a_whole_batch_of_spatial_geometry_in_one_statement()
    {
        $this->connectOrSkip('nusantara_bulk_write_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpassword',
            'charset' => 'utf8mb4',
        ]);

        $this->assertBulkWriteWorks('nusantara_bulk_write_mysql', 'mysql');
    }

    /** @test */
    public function test_postgis_bulk_update_writes_a_whole_batch_of_spatial_geometry_in_one_statement()
    {
        $this->connectOrSkip('nusantara_bulk_write_pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'testdb',
            'username' => 'postgres',
            'password' => 'postgres',
            'charset' => 'utf8',
        ]);

        $this->assertBulkWriteWorks('nusantara_bulk_write_pgsql', 'pgsql');
    }

    /** @test */
    public function test_sqlsrv_bulk_update_writes_a_whole_batch_of_spatial_geometry_in_one_statement()
    {
        $this->connectOrSkip('nusantara_bulk_write_sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'testdb',
            'username' => 'sa',
            'password' => 'TestPassword123!',
            'trust_server_certificate' => true,
        ]);

        $this->assertBulkWriteWorks('nusantara_bulk_write_sqlsrv', 'sqlsrv');
    }

    protected function assertBulkWriteWorks(string $connection, string $driver): void
    {
        Schema::connection($connection)->dropIfExists('bulk_write_test');
        Schema::connection($connection)->create('bulk_write_test', function (Blueprint $table) use ($driver) {
            $table->string('id')->primary();

            if ($driver === 'pgsql') {
                $table->geometry('boundary', 'GEOMETRY', 4326)->nullable();
            } else {
                $table->geometry('boundary')->nullable();
            }
        });

        try {
            $batch = [
                'AA' => 'POLYGON((0 0, 0 10, 10 10, 10 0, 0 0))',
                'BB' => 'POLYGON((20 20, 20 30, 30 30, 30 20, 20 20))',
                'CC' => 'POLYGON((40 40, 40 50, 50 50, 50 40, 40 40))',
            ];
            DB::connection($connection)->insert(
                'INSERT INTO bulk_write_test (id) VALUES (?), (?), (?)',
                array_keys($batch)
            );

            DB::connection($connection)->enableQueryLog();

            $this->invoke('updateBatch', [$connection, 'bulk_write_test', 'id', 'boundary', $batch, $driver, 'spatial']);

            $updates = array_filter(
                DB::connection($connection)->getQueryLog(),
                fn ($entry) => str_starts_with(strtolower($entry['query']), 'update')
            );
            DB::connection($connection)->disableQueryLog();

            $this->assertCount(1, $updates, 'Expected exactly one bulk UPDATE statement for the whole batch, not one per row.');

            // SQL Server has no ST_AsText() function — WKT extraction is a method on the column.
            $wktFn = $driver === 'sqlsrv' ? 'boundary.STAsText()' : 'ST_AsText(boundary)';
            $rows = DB::connection($connection)->select("SELECT id, {$wktFn} AS wkt FROM bulk_write_test ORDER BY id");

            $this->assertCount(3, $rows);
            foreach ($rows as $row) {
                // WKT spacing differs by driver (MySQL keeps a space after each comma,
                // SQL Server adds one between the geometry type and its coordinate list,
                // PostGIS uses neither) — normalize before comparing, the geometry itself
                // (not its textual formatting) is what this test verifies.
                $normalize = fn (string $wkt) => preg_replace('/\s+/', '', $wkt);
                $this->assertSame($normalize($batch[$row->id]), $normalize($row->wkt));
            }
        } finally {
            Schema::connection($connection)->dropIfExists('bulk_write_test');
        }
    }
}
