<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Geocoding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Support\Geocoder;
use MadeByClowd\Nusantara\Tests\TestCase;

/**
 * Phase 06 acceptance items deferred from Phase 05: proves the DB-side
 * `ST_Contains` pushdown (Geocoder::findContainingRegionSpatial()) actually
 * uses the spatial index where one exists, and stays bounded at real
 * village-count scale (~83,000 rows) — a regression guard against silently
 * falling back to the old pull-everything-then-ray-cast-in-PHP behavior,
 * which would be dramatically slower at this scale, not just "a bit slower".
 *
 * Deliberately generous time budgets — this is a regression guard, not a
 * micro-benchmark. `EXPLAIN` alone proves index usage, not wall-clock
 * behavior (decision surfaced during the enterprise-standard review, see
 * laravel-nusantara-impl-spatial-05); this file covers both, separately.
 *
 * Grid-seeding note: MySQL's `boundary` column is deliberately left
 * unindexed here, matching production — see
 * `addBoundaryColumn()`'s own comment in
 * `2026_06_05_000000_create_nusantara_tables.php`: MySQL requires
 * spatial-indexed columns to be `NOT NULL`, and `boundary` is nullable, so
 * this package only adds a spatial index on PostgreSQL. SQL Server's
 * `boundary` column is also left unindexed — its spatial index needs an
 * explicit `BOUNDING_BOX` parameter Laravel's `Blueprint::spatialIndex()`
 * doesn't emit for the `sqlsrv` grammar, and adding raw-SQL index creation
 * is out of scope for this phase (verified empirically, not assumed — see
 * `laravel-nusantara-impl-spatial-09-sqlsrv-support`). The `EXPLAIN`
 * index-usage assertion below therefore only runs against PostgreSQL —
 * "where one exists" per this phase's own acceptance wording.
 */
class GeocoderSpatialRegressionGuardTest extends TestCase
{
    protected const GRID_COLUMNS = 300;

    protected const GRID_ROWS = 280;

    protected const CELL_SIZE = 0.01;

    protected const LNG_ORIGIN = -150.0;

    protected const LAT_ORIGIN = -70.0;

    protected function connectOrSkip(string $name, array $config): void
    {
        config(["database.connections.{$name}" => $config]);

        try {
            DB::connection($name)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped("[{$name}] not reachable in this environment: {$e->getMessage()}");
        }
    }

    /**
     * Tiles a grid of small, non-overlapping square polygons (each cell
     * shrunk to 90% of its slot so adjacent cells never touch) and returns
     * the id + target point of one cell near the middle of the grid, so the
     * benchmark exercises a real "find the one matching row among tens of
     * thousands" query, not a trivially-first/last row.
     *
     * @return array{id: string, lat: float, lng: float, count: int}
     */
    protected function seedGrid(string $connection, string $driver): array
    {
        $table = 'provinces';
        Schema::connection($connection)->dropIfExists($table);
        Schema::connection($connection)->create($table, function (Blueprint $table) use ($driver) {
            $table->string('id')->primary();

            if ($driver === 'pgsql') {
                $table->geometry('boundary', 'GEOMETRY', 4326)->nullable();
                // Matches production: PostgreSQL is the only driver this
                // package spatial-indexes the boundary column on today.
                $table->spatialIndex('boundary');
            } else {
                $table->geometry('boundary')->nullable();
            }
        });

        $insertExpr = match ($driver) {
            'pgsql' => 'ST_GeomFromText(?, 4326)',
            'sqlsrv' => 'geometry::STGeomFromText(?, 4326)',
            default => 'ST_GeomFromText(?)',
        };
        $pdo = DB::connection($connection)->getPdo();

        $targetColumn = (int) (self::GRID_COLUMNS / 2);
        $targetRow = (int) (self::GRID_ROWS / 2);
        $targetId = null;
        $targetLat = null;
        $targetLng = null;
        $count = 0;

        $batch = [];
        $batchSize = 500;

        $flush = function () use (&$batch, $pdo, $table, $insertExpr) {
            if ($batch === []) {
                return;
            }

            $placeholders = implode(', ', array_fill(0, count($batch), "(?, {$insertExpr})"));
            $stmt = $pdo->prepare("INSERT INTO {$table} (id, boundary) VALUES {$placeholders}");

            $params = [];
            foreach ($batch as [$id, $wkt]) {
                $params[] = $id;
                $params[] = $wkt;
            }
            $stmt->execute($params);

            $batch = [];
        };

        for ($col = 0; $col < self::GRID_COLUMNS; $col++) {
            for ($row = 0; $row < self::GRID_ROWS; $row++) {
                $lng = self::LNG_ORIGIN + $col * self::CELL_SIZE;
                $lat = self::LAT_ORIGIN + $row * self::CELL_SIZE;
                $size = self::CELL_SIZE * 0.9;

                $id = "cell-{$col}-{$row}";
                $wkt = sprintf(
                    'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
                    $lng, $lat,
                    $lng + $size, $lat,
                    $lng + $size, $lat + $size,
                    $lng, $lat + $size,
                    $lng, $lat
                );

                $batch[] = [$id, $wkt];
                $count++;

                if ($col === $targetColumn && $row === $targetRow) {
                    $targetId = $id;
                    $targetLng = $lng + $size / 2;
                    $targetLat = $lat + $size / 2;
                }

                if (count($batch) >= $batchSize) {
                    $flush();
                }
            }
        }
        $flush();

        return ['id' => $targetId, 'lat' => $targetLat, 'lng' => $targetLng, 'count' => $count];
    }

    /** @test */
    public function test_postgis_pushdown_query_uses_the_spatial_index()
    {
        $this->connectOrSkip('nusantara_perf_pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'testdb',
            'username' => 'postgres',
            'password' => 'postgres',
            'charset' => 'utf8',
        ]);

        config(['nusantara.connection' => 'nusantara_perf_pgsql']);

        try {
            $target = $this->seedGrid('nusantara_perf_pgsql', 'pgsql');

            $plan = DB::connection('nusantara_perf_pgsql')->select(
                'EXPLAIN SELECT * FROM provinces WHERE ST_Contains(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
                [$target['lng'], $target['lat']]
            );

            $planText = implode("\n", array_map(fn ($row) => (array) $row === [] ? '' : reset($row), $plan));

            $this->assertStringContainsStringIgnoringCase(
                'Index Scan',
                $planText,
                "expected the ST_Contains query to use the GIST spatial index; got plan:\n{$planText}"
            );
        } finally {
            Schema::connection('nusantara_perf_pgsql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_postgis_pushdown_completes_within_time_budget_at_village_scale()
    {
        $this->connectOrSkip('nusantara_perf_pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'testdb',
            'username' => 'postgres',
            'password' => 'postgres',
            'charset' => 'utf8',
        ]);

        config(['nusantara.connection' => 'nusantara_perf_pgsql']);

        try {
            $target = $this->seedGrid('nusantara_perf_pgsql', 'pgsql');
            $this->assertGreaterThan(80000, $target['count'], 'grid must be seeded at real village-count scale (~83,000+)');

            $start = microtime(true);
            $province = (new Geocoder)->findByCoordinate($target['lat'], $target['lng'], 'province');
            $elapsed = microtime(true) - $start;

            $this->assertNotNull($province);
            $this->assertSame($target['id'], $province->id);
            $this->assertLessThan(
                1.0,
                $elapsed,
                "indexed ST_Contains pushdown took {$elapsed}s against {$target['count']} rows — expected well under 1s"
            );
        } finally {
            Schema::connection('nusantara_perf_pgsql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_mysql_pushdown_completes_within_time_budget_at_village_scale()
    {
        $this->connectOrSkip('nusantara_perf_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'testuser',
            'password' => 'testpassword',
            'charset' => 'utf8mb4',
        ]);

        config(['nusantara.connection' => 'nusantara_perf_mysql']);

        try {
            $target = $this->seedGrid('nusantara_perf_mysql', 'mysql');
            $this->assertGreaterThan(80000, $target['count'], 'grid must be seeded at real village-count scale (~83,000+)');

            $start = microtime(true);
            $province = (new Geocoder)->findByCoordinate($target['lat'], $target['lng'], 'province');
            $elapsed = microtime(true) - $start;

            $this->assertNotNull($province);
            $this->assertSame($target['id'], $province->id);
            // Generous on purpose — MySQL's boundary column has no spatial
            // index (see class doc-comment), so this is a real, uncached
            // ~83,000-row ST_Contains scan, not an indexed lookup. The
            // budget guards against a silent fallback to N+1/ray-cast, not
            // against "no index", which is a known, documented tradeoff.
            $this->assertLessThan(
                5.0,
                $elapsed,
                "unindexed MySQL ST_Contains pushdown took {$elapsed}s against {$target['count']} rows — expected well under 5s"
            );
        } finally {
            Schema::connection('nusantara_perf_mysql')->dropIfExists('provinces');
        }
    }

    /** @test */
    public function test_sqlsrv_pushdown_completes_within_time_budget_at_village_scale()
    {
        $this->connectOrSkip('nusantara_perf_sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'testdb',
            'username' => 'sa',
            'password' => 'TestPassword123!',
            'trust_server_certificate' => true,
        ]);

        config(['nusantara.connection' => 'nusantara_perf_sqlsrv']);

        try {
            $target = $this->seedGrid('nusantara_perf_sqlsrv', 'sqlsrv');
            $this->assertGreaterThan(80000, $target['count'], 'grid must be seeded at real village-count scale (~83,000+)');

            $start = microtime(true);
            $province = (new Geocoder)->findByCoordinate($target['lat'], $target['lng'], 'province');
            $elapsed = microtime(true) - $start;

            $this->assertNotNull($province);
            $this->assertSame($target['id'], $province->id);
            // Generous on purpose — same reasoning as the MySQL case above:
            // SQL Server spatial indexes need an explicit BOUNDING_BOX
            // parameter Laravel's Blueprint::spatialIndex() doesn't support,
            // so this package leaves it unindexed here too (not built this
            // phase — out of scope, matching the MySQL precedent).
            $this->assertLessThan(
                5.0,
                $elapsed,
                "unindexed SQL Server STContains pushdown took {$elapsed}s against {$target['count']} rows — expected well under 5s"
            );
        } finally {
            Schema::connection('nusantara_perf_sqlsrv')->dropIfExists('provinces');
        }
    }
}
