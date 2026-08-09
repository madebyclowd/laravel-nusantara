<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Search;

use MadeByClowd\Nusantara\Support\RegionSearch;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class RegionSearchPaginationTest extends SupportTestCase
{
    protected RegionSearch $regionSearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionSearch = new RegionSearch;
    }

    /** @test */
    public function test_offset_moves_the_result_window_without_repeating_rows()
    {
        $pageOne = $this->regionSearch->search('Kabupaten', 5, 0, 'regencies');
        $pageTwo = $this->regionSearch->search('Kabupaten', 5, 5, 'regencies');

        $this->assertCount(5, $pageOne['regencies']);
        $this->assertCount(5, $pageTwo['regencies']);

        $pageOneIds = array_column($pageOne['regencies'], 'id');
        $pageTwoIds = array_column($pageTwo['regencies'], 'id');

        $this->assertEmpty(array_intersect($pageOneIds, $pageTwoIds));
    }

    /** @test */
    public function test_limit_still_caps_the_result_count_alongside_offset()
    {
        $results = $this->regionSearch->search('Kabupaten', 3, 10, 'regencies');

        $this->assertLessThanOrEqual(3, count($results['regencies']));
    }
}
