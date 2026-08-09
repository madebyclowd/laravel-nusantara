<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Support;

use MadeByClowd\Nusantara\Support\RegionSearch;

class RegionSearchTest extends SupportTestCase
{
    protected RegionSearch $regionSearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionSearch = new RegionSearch;
    }

    /** @test */
    public function test_it_finds_matches_across_all_levels()
    {
        $results = $this->regionSearch->search('Aceh');

        $this->assertNotEmpty($results['provinces']);
        $this->assertEquals('Aceh', $results['provinces'][0]['name']);
    }

    /** @test */
    public function test_it_returns_empty_result_sets_for_a_query_shorter_than_two_characters()
    {
        $results = $this->regionSearch->search('a');

        $this->assertSame([], $results);
    }

    /** @test */
    public function test_it_respects_the_limit_parameter()
    {
        $results = $this->regionSearch->search('a', 0);
        // still below the 2-char threshold regardless of limit
        $this->assertSame([], $results);

        $results = $this->regionSearch->search('Kabupaten', 3);
        $this->assertLessThanOrEqual(3, count($results['regencies']));
    }

    /** @test */
    public function test_it_returns_empty_result_sets_for_no_match()
    {
        $results = $this->regionSearch->search('Xyzzyx-Nonexistent');

        $this->assertEmpty($results['provinces']);
        $this->assertEmpty($results['regencies']);
        $this->assertEmpty($results['districts']);
        $this->assertEmpty($results['villages']);
    }
}
