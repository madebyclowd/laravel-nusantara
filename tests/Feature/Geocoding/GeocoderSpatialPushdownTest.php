<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Support\Geocoder;
use MadeByClowd\Nusantara\Tests\TestCase;

/**
 * Integration tests for the Phase 05/09 DB-side spatial pushdown
 * (Geocoder::findContainingRegionSpatial()) against real MySQL,
 * PostgreSQL/PostGIS, and SQL Server instances — connects to the local
 * docker containers this dev environment already runs (mysql-container /
 * postgres-container / compose.spatial.yaml's mssql service). Skips
 * gracefully wherever those containers aren't reachable (e.g. CI, until
 * Phase 06 adds proper service containers), matching this plan's "verify
 * against reality, don't guess" discipline without hard-failing unrelated
 * environments.
 */
class GeocoderSpatialPushdownTest extends TestCase
{
    // Asymmetric polygon pair used as an axis-order trap: 'AA' is wide in
    // longitude / narrow in latitude near the equator, 'BB' is its mirror
    // (narrow longitude / wide latitude) sitting at the coordinates 'AA'
    // would land on if lat/lng were swapped in the containment query. A
    // point correctly matching 'AA' would incorrectly match 'BB' instead
    // under a lat/lng axis-order bug — not just fall outside every boundary.
    protected const POLYGON_AA = 'POLYGON((100 0, 110 0, 110 1, 100 1, 100 0))';

    protected const POLYGON_BB = 'POLYGON((0 100, 1 100, 1 110, 0 110, 0 100))';

    protected function connectOrSkip(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config]);

        try {
            DB::connection($name)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped("[{$name}] not reachable in this environment: {$e->getMessage()}");
        }
    }

    /** @test */
    public function test_mysql_st_contains_pushdown_respects_axis_order()
    {
        $this->connectOrSkip('nusantara_spatial_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpassword',
            'charset' => 'utf8mb4',
        ]);

        config(['nusantara.connection' => 'nusantara_spatial_mysql']);

        Schema::connection('nusantara_spatial_mysql')->dropIfExists('provinces');
        Schema::connection('nusantara_spatial_mysql')->create('provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->geometry('boundary')->nullable();
        });

        try {
            DB::connection('nusantara_spatial_mysql')->insert(
                'INSERT INTO provinces (id, boundary) VALUES (?, ST_GeomFromText(?)), (?, ST_GeomFromText(?))',
                ['AA', self::POLYGON_AA, 'BB', self::POLYGON_BB]
            );

            $province = (new Geocoder)->findByCoordinate(0.5, 105, 'province');

            $this->assertNotNull($province);
            $this->assertSame('AA', $province->id);
        } finally {
            Schema::connection('nusantara_spatial_mysql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_mysql_spatial_pushdown_respects_parent_scoping()
    {
        $this->connectOrSkip('nusantara_spatial_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpassword',
            'charset' => 'utf8mb4',
        ]);

        config(['nusantara.connection' => 'nusantara_spatial_mysql']);

        $connection = 'nusantara_spatial_mysql';
        Schema::connection($connection)->dropIfExists('regencies');
        Schema::connection($connection)->dropIfExists('provinces');
        Schema::connection($connection)->create('provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->geometry('boundary')->nullable();
        });
        Schema::connection($connection)->create('regencies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('province_id');
            $table->geometry('boundary')->nullable();
        });

        try {
            DB::connection($connection)->insert(
                'INSERT INTO provinces (id, boundary) VALUES (?, ST_GeomFromText(?))',
                ['PP', self::POLYGON_AA]
            );

            // 'Impostor' regency has the *same* matching boundary but belongs to a
            // different (non-existent) parent province — narrowing must exclude it
            // purely on the where(province_id, ...) scope, not on spatial containment
            // (its geometry alone would satisfy ST_Contains just fine).
            DB::connection($connection)->insert(
                'INSERT INTO regencies (id, province_id, boundary) VALUES (?, ?, ST_GeomFromText(?))',
                ['R_impostor', 'QQ', self::POLYGON_AA]
            );

            $regency = (new Geocoder)->findByCoordinate(0.5, 105, 'regency');
            $this->assertNull($regency, 'Parent-scoped narrowing must exclude a spatially-matching row belonging to a different parent.');

            DB::connection($connection)->insert(
                'INSERT INTO regencies (id, province_id, boundary) VALUES (?, ?, ST_GeomFromText(?))',
                ['R_correct', 'PP', self::POLYGON_AA]
            );

            $regency = (new Geocoder)->findByCoordinate(0.5, 105, 'regency');
            $this->assertNotNull($regency);
            $this->assertSame('R_correct', $regency->id);
        } finally {
            Schema::connection($connection)->dropIfExists('regencies');
            Schema::connection($connection)->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_sqlsrv_stcontains_pushdown_respects_axis_order()
    {
        $this->connectOrSkip('nusantara_spatial_sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'testdb',
            'username' => 'sa',
            'password' => 'TestPassword123!',
            'trust_server_certificate' => true,
        ]);

        config(['nusantara.connection' => 'nusantara_spatial_sqlsrv']);

        Schema::connection('nusantara_spatial_sqlsrv')->dropIfExists('provinces');
        Schema::connection('nusantara_spatial_sqlsrv')->create('provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->geometry('boundary')->nullable();
        });

        try {
            DB::connection('nusantara_spatial_sqlsrv')->insert(
                'INSERT INTO provinces (id, boundary) VALUES (?, geometry::STGeomFromText(?, 4326)), (?, geometry::STGeomFromText(?, 4326))',
                ['AA', self::POLYGON_AA, 'BB', self::POLYGON_BB]
            );

            $province = (new Geocoder)->findByCoordinate(0.5, 105, 'province');

            $this->assertNotNull($province);
            $this->assertSame('AA', $province->id);
        } finally {
            Schema::connection('nusantara_spatial_sqlsrv')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_sqlsrv_spatial_pushdown_respects_parent_scoping()
    {
        $this->connectOrSkip('nusantara_spatial_sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'testdb',
            'username' => 'sa',
            'password' => 'TestPassword123!',
            'trust_server_certificate' => true,
        ]);

        config(['nusantara.connection' => 'nusantara_spatial_sqlsrv']);

        $connection = 'nusantara_spatial_sqlsrv';
        Schema::connection($connection)->dropIfExists('regencies');
        Schema::connection($connection)->dropIfExists('provinces');
        Schema::connection($connection)->create('provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->geometry('boundary')->nullable();
        });
        Schema::connection($connection)->create('regencies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('province_id');
            $table->geometry('boundary')->nullable();
        });

        try {
            DB::connection($connection)->insert(
                'INSERT INTO provinces (id, boundary) VALUES (?, geometry::STGeomFromText(?, 4326))',
                ['PP', self::POLYGON_AA]
            );

            DB::connection($connection)->insert(
                'INSERT INTO regencies (id, province_id, boundary) VALUES (?, ?, geometry::STGeomFromText(?, 4326))',
                ['R_impostor', 'QQ', self::POLYGON_AA]
            );

            $regency = (new Geocoder)->findByCoordinate(0.5, 105, 'regency');
            $this->assertNull($regency, 'Parent-scoped narrowing must exclude a spatially-matching row belonging to a different parent.');

            DB::connection($connection)->insert(
                'INSERT INTO regencies (id, province_id, boundary) VALUES (?, ?, geometry::STGeomFromText(?, 4326))',
                ['R_correct', 'PP', self::POLYGON_AA]
            );

            $regency = (new Geocoder)->findByCoordinate(0.5, 105, 'regency');
            $this->assertNotNull($regency);
            $this->assertSame('R_correct', $regency->id);
        } finally {
            Schema::connection($connection)->dropIfExists('regencies');
            Schema::connection($connection)->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_postgis_st_contains_pushdown_respects_axis_order()
    {
        $this->connectOrSkip('nusantara_spatial_pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'testdb',
            'username' => 'postgres',
            'password' => 'postgres',
            'charset' => 'utf8',
        ]);

        config(['nusantara.connection' => 'nusantara_spatial_pgsql']);

        Schema::connection('nusantara_spatial_pgsql')->dropIfExists('provinces');
        Schema::connection('nusantara_spatial_pgsql')->create('provinces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->geometry('boundary', 'GEOMETRY', 4326)->nullable();
        });

        try {
            DB::connection('nusantara_spatial_pgsql')->insert(
                'INSERT INTO provinces (id, boundary) VALUES (?, ST_GeomFromText(?, 4326)), (?, ST_GeomFromText(?, 4326))',
                ['AA', self::POLYGON_AA, 'BB', self::POLYGON_BB]
            );

            $province = (new Geocoder)->findByCoordinate(0.5, 105, 'province');

            $this->assertNotNull($province);
            $this->assertSame('AA', $province->id);
        } finally {
            Schema::connection('nusantara_spatial_pgsql')->dropIfExists('provinces');
        }
    }
}
