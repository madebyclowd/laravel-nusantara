<?php

/**
 * Regenerates tests/fixtures/wkb/{mysql,pgsql}/*.wkb — real raw WKB bytes
 * read back through the exact retrieval SQL BoundaryWkbQuery::selectExpression()
 * builds, after writing known boundaries through the same WKT-insert
 * convention DownloadBoundariesCommand::getSpatialExpressionPlaceholder()
 * uses (ST_GeomFromText(?) for MySQL/SRID 0, ST_GeomFromText(?, 4326) for
 * PostgreSQL), so fixtures reflect exactly what this package's own
 * downloader produces — not a synthetic shortcut.
 *
 * Idempotent and re-runnable — Phase 06's CI job runs this to catch silent
 * ST_AsBinary()-family format drift across driver versions, not just to
 * generate fixtures once (see laravel-nusantara-impl-spatial-03's
 * acceptance criteria).
 *
 * Requires the live MySQL + PostgreSQL/PostGIS containers this dev
 * environment already runs (mysql-container / postgres-container — same
 * credentials as tests/Feature/Geocoding/GeocoderSpatialPushdownTest).
 * A driver that isn't reachable is skipped, not fatal, matching this plan's
 * "verify against reality, don't guess" discipline without hard-failing
 * unrelated environments. SQL Server / SpatiaLite are not attempted here —
 * no live environment exists in this repo to verify against yet.
 *
 * Usage: php scripts/export-wkb-fixtures.php
 */

declare(strict_types=1);

$fixturesRoot = __DIR__.'/../tests/fixtures/wkb';

/** @var array<string, array{wkt: string}> */
$geometries = [
    'square-polygon' => [
        'wkt' => 'POLYGON((106.8 -6.2, 106.9 -6.2, 106.9 -6.1, 106.8 -6.1, 106.8 -6.2))',
    ],
    'two-part-multipolygon' => [
        'wkt' => 'MULTIPOLYGON(((106.8 -6.2, 106.9 -6.2, 106.9 -6.1, 106.8 -6.1, 106.8 -6.2)), '.
                 '((107.0 -6.3, 107.1 -6.3, 107.1 -6.2, 107.0 -6.2, 107.0 -6.3)))',
    ],
];

/** @var array<string, array{dsn: string, user: string, pass: string, createTable: string, insertExpr: string}> */
$drivers = [
    'mysql' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=testdb;charset=utf8mb4',
        'user' => 'testuser',
        'pass' => 'testpassword',
        'createTable' => 'CREATE TABLE wkb_fixture_export (id VARCHAR(64) PRIMARY KEY, boundary GEOMETRY NOT NULL)',
        'insertExpr' => 'ST_GeomFromText(?)', // SRID 0 — matches DownloadBoundariesCommand's mysql branch
    ],
    'pgsql' => [
        'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=testdb',
        'user' => 'postgres',
        'pass' => 'postgres',
        'createTable' => 'CREATE TABLE wkb_fixture_export (id VARCHAR(64) PRIMARY KEY, boundary GEOMETRY(GEOMETRY, 4326) NOT NULL)',
        'insertExpr' => 'ST_GeomFromText(?, 4326)', // matches DownloadBoundariesCommand's pgsql branch
    ],
];

require __DIR__.'/../src/Support/Spatial/BoundaryWkbQuery.php';

use MadeByClowd\Nusantara\Support\Spatial\BoundaryWkbQuery;

foreach ($drivers as $driverName => $config) {
    fwrite(STDERR, "=== {$driverName} ===\n");

    try {
        $pdo = new PDO($config['dsn'], $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        fwrite(STDERR, "  skipped — not reachable: {$e->getMessage()}\n");

        continue;
    }

    $pdo->exec('DROP TABLE IF EXISTS wkb_fixture_export');
    $pdo->exec($config['createTable']);

    $outDir = "{$fixturesRoot}/{$driverName}";
    if (! is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }

    $selectExpr = BoundaryWkbQuery::selectExpression($driverName, 'boundary');

    foreach ($geometries as $name => $geom) {
        $insert = $pdo->prepare("INSERT INTO wkb_fixture_export (id, boundary) VALUES (?, {$config['insertExpr']})");
        $insert->execute([$name, $geom['wkt']]);

        $select = $pdo->prepare("SELECT {$selectExpr} AS wkb FROM wkb_fixture_export WHERE id = ?");
        $select->execute([$name]);
        $wkb = $select->fetchColumn();

        if (is_resource($wkb)) {
            $wkb = stream_get_contents($wkb);
        }

        $path = "{$outDir}/{$name}.wkb";
        file_put_contents($path, $wkb);
        fwrite(STDERR, '  wrote '.basename($path).' ('.strlen($wkb)." bytes)\n");
    }

    $pdo->exec('DROP TABLE IF EXISTS wkb_fixture_export');
}

fwrite(STDERR, "Done.\n");
