<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Nik;

use MadeByClowd\Nusantara\Support\NikParser;
use MadeByClowd\Nusantara\Support\RegionQuery;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class NikInfoRegionResolutionTest extends SupportTestCase
{
    protected NikParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new NikParser(new RegionQuery);
    }

    /** @test */
    public function test_it_resolves_district_regency_and_province_for_an_active_region_code()
    {
        $info = $this->parser->parse('1101011505900001'); // Aceh / Aceh Selatan / Bakongan

        $this->assertNotNull($info->district());
        $this->assertSame('110101', $info->district()->id);

        $this->assertNotNull($info->regency());
        $this->assertSame('1101', $info->regency()->id);

        $this->assertNotNull($info->province());
        $this->assertSame('11', $info->province()->id);
        $this->assertEquals('Aceh', $info->province()->name);
    }

    /** @test */
    public function test_it_resolves_through_the_legacy_region_code_fallback()
    {
        // Pre-2022 Papua: district 910101 no longer exists directly, but
        // resolves via LegacyRegionResolver to 930101 (Merauke).
        $info = $this->parser->parse('9101010101900001');

        $district = $info->district();
        $this->assertNotNull($district);
        $this->assertSame('930101', $district->id);

        $this->assertNotNull($info->regency());
        $this->assertSame('9301', $info->regency()->id);

        $this->assertNotNull($info->province());
        $this->assertSame('93', $info->province()->id);
    }

    /** @test */
    public function test_region_accessors_return_null_without_a_region_query()
    {
        $info = (new NikParser)->parse('1101011505900001');

        $this->assertNull($info->district());
        $this->assertNull($info->regency());
        $this->assertNull($info->province());
    }
}
