<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Nik;

use MadeByClowd\Nusantara\Exceptions\NikValidationException;
use MadeByClowd\Nusantara\Support\NikParser;
use MadeByClowd\Nusantara\Tests\TestCase;

class NikParserTest extends TestCase
{
    protected NikParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new NikParser;
    }

    /** @test */
    public function test_it_parses_a_valid_male_nik()
    {
        $info = $this->parser->parse('1101011505900001');

        $this->assertSame('11', $info->provinceId);
        $this->assertSame('1101', $info->regencyId);
        $this->assertSame('110101', $info->districtId);
        $this->assertSame('male', $info->gender);
        $this->assertSame('1990-05-15', $info->birthDate->toDateString());
        $this->assertSame('0001', $info->sequence);
    }

    /** @test */
    public function test_it_parses_a_valid_female_nik_using_the_day_plus_40_convention()
    {
        $info = $this->parser->parse('1101015505900001');

        $this->assertSame('female', $info->gender);
        $this->assertSame('1990-05-15', $info->birthDate->toDateString());
    }

    /** @test */
    public function test_it_applies_the_century_threshold_heuristic()
    {
        // yy=90 with reference year 2026 -> 2090 would be in the future, so
        // it must fall back to 1990.
        $info = $this->parser->parse('1101011505900001', referenceYear: 2026);
        $this->assertSame(1990, $info->birthDate->year);

        // yy=10 with reference year 2026 -> 2010 is not in the future, so
        // it stays in the 2000s.
        $info = $this->parser->parse('1101011505100001', referenceYear: 2026);
        $this->assertSame(2010, $info->birthDate->year);
    }

    /** @test */
    public function test_century_override_takes_priority_over_the_heuristic()
    {
        $info = $this->parser->parse('1101011505900001', centuryOverride: 18);

        $this->assertSame(1890, $info->birthDate->year);
    }

    /** @test */
    public function test_century_override_accepts_a_full_year_form()
    {
        $info = $this->parser->parse('1101011505900001', centuryOverride: 1800);

        $this->assertSame(1890, $info->birthDate->year);
    }

    /** @test */
    public function test_it_rejects_a_nik_with_the_wrong_length()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('110101150590001');
    }

    /** @test */
    public function test_it_rejects_a_nik_with_non_numeric_characters()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('110101150590000A');
    }

    /** @test */
    public function test_it_rejects_a_zero_province_code()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('0001011505900001');
    }

    /** @test */
    public function test_it_rejects_a_zero_regency_code()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1100011505900001');
    }

    /** @test */
    public function test_it_rejects_a_zero_district_code()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1101001505900001');
    }

    /** @test */
    public function test_it_rejects_an_invalid_day_part()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1101019905900001'); // 99 is neither 1-31 nor 41-71
    }

    /** @test */
    public function test_it_rejects_an_invalid_month()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1101011513900001'); // month 13
    }

    /** @test */
    public function test_it_rejects_an_impossible_calendar_date()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1101013002900001'); // Feb 30
    }

    /** @test */
    public function test_it_rejects_a_zero_sequence()
    {
        $this->expectException(NikValidationException::class);
        $this->parser->parse('1101011505900000');
    }

    /** @test */
    public function test_is_valid_returns_a_boolean_without_throwing()
    {
        $this->assertTrue($this->parser->isValid('1101011505900001'));
        $this->assertFalse($this->parser->isValid('not-a-nik'));
    }
}
