<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Search;

use MadeByClowd\Nusantara\Support\RegionSearch;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class RegionSearchFuzzyTest extends SupportTestCase
{
    protected RegionSearch $regionSearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionSearch = new RegionSearch;
    }

    /** @test */
    public function test_fuzzy_search_finds_a_typo_match_that_plain_search_misses()
    {
        $typo = 'Acej'; // one-character typo of "Aceh"

        $plain = $this->regionSearch->search($typo, 20, 0, 'provinces');
        $this->assertEmpty($plain['provinces']);

        $fuzzy = $this->regionSearch->searchFuzzy($typo, 20, 'provinces');

        $this->assertNotEmpty($fuzzy['provinces']);
        $this->assertEquals('Aceh', $fuzzy['provinces'][0]['name']);
    }

    /** @test */
    public function test_fuzzy_search_returns_empty_when_there_is_no_close_match()
    {
        $fuzzy = $this->regionSearch->searchFuzzy('Zzqqxxxwwwyyy', 20, 'provinces');

        $this->assertEmpty($fuzzy['provinces']);
    }

    /** @test */
    public function test_fuzzy_search_is_bounded_by_max_distance()
    {
        // "Acej" is distance 1 from "Aceh"; with maxDistance 0 it must not match.
        $fuzzy = $this->regionSearch->searchFuzzy('Acej', 20, 'provinces', 0);

        $this->assertEmpty($fuzzy['provinces']);
    }

    /** @test */
    public function test_fuzzy_search_respects_scope()
    {
        $fuzzy = $this->regionSearch->searchFuzzy('Acej', 20, 'provinces');

        $this->assertNotEmpty($fuzzy['provinces']);
        $this->assertSame([], $fuzzy['regencies']);
        $this->assertSame([], $fuzzy['districts']);
        $this->assertSame([], $fuzzy['villages']);
    }

    /** @test */
    public function test_search_does_not_automatically_fall_back_to_fuzzy_matching()
    {
        $results = $this->regionSearch->search('Acej', 20, 0, 'provinces');

        $this->assertEmpty($results['provinces']);
    }

    /** @test */
    public function test_fuzzy_search_throws_for_an_invalid_scope()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->regionSearch->searchFuzzy('Acej', 20, 'countries');
    }
}
