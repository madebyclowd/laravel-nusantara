<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Tests\TestCase;

/**
 * Phase 04 integration tests for HasGeoBoundary::toGeoJson() against real
 * native spatial boundary columns — connects to the local MySQL/PostgreSQL
 * containers this dev environment already runs (mysql-container /
 * postgres-container, same credentials as GeocoderSpatialPushdownTest).
 * Skips gracefully wherever those containers aren't reachable, matching
 * this plan's "verify against reality, don't guess" discipline.
 *
 * This is the closing test for audit-002: SITARUNG's exact reproduction
 * case (District::find(...)->toGeoJson() under spatial storage) must
 * return Polygon/MultiPolygon, not throw and not silently fall back to
 * Point.
 */
class HasGeoBoundarySpatialTest extends TestCase
{
    protected const SQUARE_POLYGON_WKT = 'POLYGON((106.8 -6.2, 106.9 -6.2, 106.9 -6.1, 106.8 -6.1, 106.8 -6.2))';

    protected const SQUARE_POLYGON_GEOJSON = [[
        [106.8, -6.2], [106.9, -6.2], [106.9, -6.1], [106.8, -6.1], [106.8, -6.2],
    ]];

    protected const TWO_PART_MULTIPOLYGON_WKT = 'MULTIPOLYGON(((106.8 -6.2, 106.9 -6.2, 106.9 -6.1, 106.8 -6.1, 106.8 -6.2)), '.
        '((107.0 -6.3, 107.1 -6.3, 107.1 -6.2, 107.0 -6.2, 107.0 -6.3)))';

    protected function connectOrSkip(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config]);

        try {
            DB::connection($name)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped("[{$name}] not reachable in this environment: {$e->getMessage()}");
        }
    }

    protected function createProvincesTable(string $connection, string $boundaryColumnDefinition): void
    {
        Schema::connection($connection)->dropIfExists('provinces');
        Schema::connection($connection)->create('provinces', function (Blueprint $table) use ($boundaryColumnDefinition) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();

            if ($boundaryColumnDefinition === 'mysql') {
                $table->geometry('boundary')->nullable();
            } else {
                $table->geometry('boundary', 'GEOMETRY', 4326)->nullable();
            }
        });
    }

    /** @test */
    public function test_it_decodes_a_real_polygon_boundary_via_mysql()
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
        $this->createProvincesTable('nusantara_spatial_mysql', 'mysql');

        try {
            DB::connection('nusantara_spatial_mysql')->insert(
                'INSERT INTO provinces (id, name, boundary) VALUES (?, ?, ST_GeomFromText(?))',
                ['AA', 'Aceh', self::SQUARE_POLYGON_WKT]
            );

            $geojson = Province::find('AA')->toGeoJson();

            $this->assertSame('Feature', $geojson['type']);
            $this->assertSame('Polygon', $geojson['geometry']['type']);
            $this->assertSame(self::SQUARE_POLYGON_GEOJSON, $geojson['geometry']['coordinates']);
            $this->assertSame('Aceh', $geojson['properties']['name']);
        } finally {
            Schema::connection('nusantara_spatial_mysql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_it_decodes_a_real_multipolygon_boundary_via_pgsql()
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
        $this->createProvincesTable('nusantara_spatial_pgsql', 'pgsql');

        try {
            DB::connection('nusantara_spatial_pgsql')->insert(
                'INSERT INTO provinces (id, name, boundary) VALUES (?, ?, ST_GeomFromText(?, 4326))',
                ['AA', 'Aceh', self::TWO_PART_MULTIPOLYGON_WKT]
            );

            $geojson = Province::find('AA')->toGeoJson();

            $this->assertSame('MultiPolygon', $geojson['geometry']['type']);
            $this->assertSame(self::SQUARE_POLYGON_GEOJSON[0], $geojson['geometry']['coordinates'][0][0]);
        } finally {
            Schema::connection('nusantara_spatial_pgsql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_decoded_boundary_is_cached_and_the_wkb_query_only_runs_once()
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
        config(['nusantara.cache.enabled' => true]);
        $this->createProvincesTable('nusantara_spatial_mysql', 'mysql');

        try {
            DB::connection('nusantara_spatial_mysql')->insert(
                'INSERT INTO provinces (id, name, boundary) VALUES (?, ?, ST_GeomFromText(?))',
                ['AA', 'Aceh', self::SQUARE_POLYGON_WKT]
            );

            $wkbQueries = 0;
            DB::connection('nusantara_spatial_mysql')->listen(function ($query) use (&$wkbQueries) {
                if (str_contains($query->sql, 'ST_AsBinary')) {
                    $wkbQueries++;
                }
            });

            $first = Province::find('AA')->toGeoJson();
            $second = Province::find('AA')->toGeoJson();

            $this->assertSame($first, $second);
            $this->assertSame(1, $wkbQueries, 'the WKB retrieval query must only run on the first, uncached call');
        } finally {
            Schema::connection('nusantara_spatial_mysql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_it_throws_a_malformed_wkb_exception_when_the_boundary_disappears_after_load()
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
        $this->createProvincesTable('nusantara_spatial_mysql', 'mysql');

        try {
            DB::connection('nusantara_spatial_mysql')->insert(
                'INSERT INTO provinces (id, name, boundary) VALUES (?, ?, ST_GeomFromText(?))',
                ['AA', 'Aceh', self::SQUARE_POLYGON_WKT]
            );

            $province = Province::find('AA');

            // Row deleted between load and toGeoJson() — the fast-path
            // decode-level malformed/truncated-bytes cases are already
            // exhaustively covered by GeometryReaderTest and
            // BoundaryWkbQueryTest against synthetic bytes; this proves the
            // still-correct throw (not a silent wrong return) survives all
            // the way through the live retrieval-query wiring too.
            DB::connection('nusantara_spatial_mysql')->table('provinces')->where('id', 'AA')->delete();

            $this->expectException(MalformedWkbException::class);

            $province->toGeoJson();
        } finally {
            Schema::connection('nusantara_spatial_mysql')->dropIfExists('provinces');
        }
    }
}
