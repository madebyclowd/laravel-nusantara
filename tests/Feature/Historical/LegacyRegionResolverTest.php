<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Historical;

use MadeByClowd\Nusantara\Support\LegacyRegionResolver;
use MadeByClowd\Nusantara\Tests\TestCase;

class LegacyRegionResolverTest extends TestCase
{
    protected LegacyRegionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LegacyRegionResolver;
    }

    /** @test */
    public function test_it_resolves_a_legacy_regency_code()
    {
        $this->assertSame('9301', $this->resolver->resolveLegacyId('9101'));
    }

    /** @test */
    public function test_it_resolves_a_legacy_district_code_preserving_the_suffix()
    {
        $this->assertSame('930101', $this->resolver->resolveLegacyId('910101'));
    }

    /** @test */
    public function test_it_resolves_a_legacy_village_code_preserving_the_suffix()
    {
        $this->assertSame('9301011002', $this->resolver->resolveLegacyId('9101011002'));
    }

    /** @test */
    public function test_it_resolves_across_all_historical_split_groups()
    {
        // One representative code from each split group in HistoricalRegionMap.
        $this->assertSame('6501', $this->resolver->resolveLegacyId('6406')); // Kaltara 2012
        $this->assertSame('7601', $this->resolver->resolveLegacyId('7322')); // Sulbar 2004
        $this->assertSame('3601', $this->resolver->resolveLegacyId('3201')); // Banten 2000
        $this->assertSame('7501', $this->resolver->resolveLegacyId('7105')); // Gorontalo 2000
        $this->assertSame('2101', $this->resolver->resolveLegacyId('1402')); // Kepri 2002
        $this->assertSame('1901', $this->resolver->resolveLegacyId('1607')); // Babel 2000
    }

    /** @test */
    public function test_it_passes_through_an_unmapped_id_unchanged()
    {
        $this->assertSame('1101', $this->resolver->resolveLegacyId('1101'));
        $this->assertSame('110101', $this->resolver->resolveLegacyId('110101'));
    }

    /** @test */
    public function test_it_passes_through_a_province_id_unchanged()
    {
        // Province IDs are 2 digits — too short to contain a regency prefix,
        // and never remapped by any historical split.
        $this->assertSame('91', $this->resolver->resolveLegacyId('91'));
    }
}
