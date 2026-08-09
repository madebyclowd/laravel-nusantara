<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Search;

use MadeByClowd\Nusantara\Support\RegionSearch;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class RegionSearchScopingTest extends SupportTestCase
{
    protected RegionSearch $regionSearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionSearch = new RegionSearch;
    }

    /** @test */
    public function test_it_scopes_results_to_provinces_only()
    {
        $results = $this->regionSearch->search('Aceh', 20, 0, 'provinces');

        $this->assertNotEmpty($results['provinces']);
        $this->assertEquals('Aceh', $results['provinces'][0]['name']);
        $this->assertSame([], $results['regencies']);
        $this->assertSame([], $results['districts']);
        $this->assertSame([], $results['villages']);
    }

    /** @test */
    public function test_it_scopes_results_to_regencies_only()
    {
        $results = $this->regionSearch->search('Aceh Selatan', 20, 0, 'regencies');

        $this->assertNotEmpty($results['regencies']);
        $this->assertSame([], $results['provinces']);
        $this->assertSame([], $results['districts']);
        $this->assertSame([], $results['villages']);
    }

    /** @test */
    public function test_it_throws_for_an_invalid_scope()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->regionSearch->search('Aceh', 20, 0, 'countries');
    }

    /** @test */
    public function test_unscoped_search_still_queries_all_four_levels()
    {
        $results = $this->regionSearch->search('Aceh');

        $this->assertArrayHasKey('provinces', $results);
        $this->assertArrayHasKey('regencies', $results);
        $this->assertArrayHasKey('districts', $results);
        $this->assertArrayHasKey('villages', $results);
        $this->assertNotEmpty($results['provinces']);
        $this->assertEquals('Aceh', $results['provinces'][0]['name']);
    }
}
