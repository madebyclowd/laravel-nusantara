<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support\Spatial;

use MadeByClowd\Nusantara\Exceptions\MalformedWkbException;
use MadeByClowd\Nusantara\Support\Spatial\BoundaryWkbQuery;
use MadeByClowd\Nusantara\Support\Spatial\GeometryReader;
use MadeByClowd\Nusantara\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Primary correctness suite for the retrieval-query + brick/geo pipeline
 * (decision 9): decodes real WKB fixtures — committed bytes read back via
 * BoundaryWkbQuery::selectExpression() against live MySQL/PostgreSQL
 * containers by scripts/export-wkb-fixtures.php — through
 * GeometryReader::decode() end-to-end. No live DB is needed to run this
 * suite; regenerating the fixtures does.
 */
class BoundaryWkbQueryTest extends TestCase
{
    protected const EXPECTED_SQUARE_POLYGON = [
        'type' => 'Polygon',
        'coordinates' => [[
            [106.8, -6.2], [106.9, -6.2], [106.9, -6.1], [106.8, -6.1], [106.8, -6.2],
        ]],
    ];

    protected const EXPECTED_MULTIPOLYGON = [
        'type' => 'MultiPolygon',
        'coordinates' => [
            [[[106.8, -6.2], [106.9, -6.2], [106.9, -6.1], [106.8, -6.1], [106.8, -6.2]]],
            [[[107.0, -6.3], [107.1, -6.3], [107.1, -6.2], [107.0, -6.2], [107.0, -6.3]]],
        ],
    ];

    public static function verifiedDriverProvider(): array
    {
        return [
            'mysql' => ['mysql'],
            'pgsql' => ['pgsql'],
        ];
    }

    /**
     * @test
     */
    #[DataProvider('verifiedDriverProvider')]
    public function test_it_decodes_a_real_square_polygon_fixture_per_driver(string $driver)
    {
        $geojson = GeometryReader::decode(self::fixture($driver, 'square-polygon'));

        $this->assertSame(self::EXPECTED_SQUARE_POLYGON, $geojson);
    }

    /**
     * @test
     */
    #[DataProvider('verifiedDriverProvider')]
    public function test_it_decodes_a_real_multipolygon_fixture_per_driver(string $driver)
    {
        $geojson = GeometryReader::decode(self::fixture($driver, 'two-part-multipolygon'));

        $this->assertSame(self::EXPECTED_MULTIPOLYGON, $geojson);
    }

    /**
     * MySQL writes SRID 0, PostgreSQL writes SRID 4326 (laravel-nusantara-adr-003)
     * — ST_AsBinary() strips SRID metadata regardless (that's the definition
     * of plain WKB vs EWKB), so both drivers' retrieval-query output must be
     * byte-identical for the same coordinates. Proven directly on the
     * committed fixtures, not assumed.
     *
     * @test
     */
    public function test_mysql_and_pgsql_wkb_fixtures_are_byte_identical()
    {
        $this->assertSame(
            self::fixture('mysql', 'square-polygon'),
            self::fixture('pgsql', 'square-polygon')
        );

        $this->assertSame(
            self::fixture('mysql', 'two-part-multipolygon'),
            self::fixture('pgsql', 'two-part-multipolygon')
        );
    }

    /** @test */
    public function test_an_empty_query_result_string_is_rejected_as_malformed()
    {
        $this->expectException(MalformedWkbException::class);

        GeometryReader::decode('');
    }

    public static function driverSyntaxProvider(): array
    {
        return [
            'mysql — function-wrapping-column' => ['mysql', 'ST_AsBinary(boundary)'],
            'pgsql — function-wrapping-column' => ['pgsql', 'ST_AsBinary(boundary)'],
            'sqlite — function-wrapping-column (SpatiaLite)' => ['sqlite', 'AsBinary(boundary)'],
            'sqlsrv — method-on-column, not function-wrapping-column' => ['sqlsrv', 'boundary.STAsBinary()'],
        ];
    }

    /**
     * SQL Server's retrieval syntax is a method call on the column
     * (`{column}.STAsBinary()`), not a function wrapping it like the other
     * 3 drivers — explicitly asserted here rather than assumed to follow
     * the same shape, per this phase's acceptance criteria. Not live-DB
     * verified (no SQL Server environment exists here yet — see
     * BoundaryWkbQuery::VERIFIED_DRIVERS).
     *
     * @test
     */
    #[DataProvider('driverSyntaxProvider')]
    public function test_select_expression_matches_each_drivers_documented_syntax(string $driver, string $expected)
    {
        $this->assertSame($expected, BoundaryWkbQuery::selectExpression($driver, 'boundary'));
    }

    /** @test */
    public function test_select_expression_returns_null_for_an_unknown_driver()
    {
        $this->assertNull(BoundaryWkbQuery::selectExpression('unknown-driver', 'boundary'));
    }

    /** @test */
    public function test_only_mysql_and_pgsql_are_marked_verified()
    {
        $this->assertSame(['mysql', 'pgsql'], BoundaryWkbQuery::VERIFIED_DRIVERS);
        $this->assertTrue(BoundaryWkbQuery::isVerified('mysql'));
        $this->assertTrue(BoundaryWkbQuery::isVerified('pgsql'));
        $this->assertFalse(BoundaryWkbQuery::isVerified('sqlsrv'));
        $this->assertFalse(BoundaryWkbQuery::isVerified('sqlite'));
    }

    private static function fixture(string $driver, string $name): string
    {
        $path = __DIR__."/../../../fixtures/wkb/{$driver}/{$name}.wkb";

        return file_get_contents($path);
    }
}
