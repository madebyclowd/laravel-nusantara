<?php

namespace MadeByClowd\Nusantara\Tests\Feature\PostalCode;

use Illuminate\Support\Facades\Schema;
use MadeByClowd\Nusantara\Exceptions\PostalCodeValidationException;
use MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder;
use MadeByClowd\Nusantara\Support\PostalCodeResolver;
use MadeByClowd\Nusantara\Tests\Feature\Support\SupportTestCase;

class PostalCodeResolverTest extends SupportTestCase
{
    protected PostalCodeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PostalCodeResolver;
    }

    /** @test */
    public function test_it_resolves_a_known_postal_code_to_all_matching_villages()
    {
        $villages = $this->resolver->resolve('23773');

        // Kecamatan Bakongan (110101), Kabupaten Aceh Selatan (1101), Aceh
        // (11) has 7 villages sharing postal code 23773 in the seeded fixture.
        $this->assertGreaterThan(1, $villages->count());

        foreach ($villages as $village) {
            $this->assertSame('23773', $village->postal_code);

            $district = $village->district;
            $this->assertNotNull($district);
            $this->assertSame('110101', $district->id);

            $regency = $district->regency;
            $this->assertNotNull($regency);
            $this->assertSame('1101', $regency->id);

            $province = $regency->province;
            $this->assertNotNull($province);
            $this->assertSame('11', $province->id);
            $this->assertSame('Aceh', $province->name);
        }
    }

    /** @test */
    public function test_it_returns_an_empty_collection_for_a_well_formed_but_unassigned_postal_code()
    {
        $villages = $this->resolver->resolve('99999');

        $this->assertCount(0, $villages);
    }

    /** @test */
    public function test_it_throws_for_a_postal_code_that_is_too_short()
    {
        $this->expectException(PostalCodeValidationException::class);
        $this->resolver->resolve('1234');
    }

    /** @test */
    public function test_it_throws_for_a_postal_code_that_is_too_long()
    {
        $this->expectException(PostalCodeValidationException::class);
        $this->resolver->resolve('123456');
    }

    /** @test */
    public function test_it_throws_for_a_non_numeric_postal_code()
    {
        $this->expectException(PostalCodeValidationException::class);
        $this->resolver->resolve('2377A');
    }

    /** @test */
    public function test_it_throws_for_a_postal_code_with_a_leading_zero()
    {
        $this->expectException(PostalCodeValidationException::class);
        $this->resolver->resolve('02377');
    }

    /** @test */
    public function test_is_valid_returns_a_boolean_without_throwing()
    {
        $this->assertTrue($this->resolver->isValid('23773'));

        $this->assertFalse($this->resolver->isValid('1234'));
        $this->assertFalse($this->resolver->isValid('123456'));
        $this->assertFalse($this->resolver->isValid('2377A'));
        $this->assertFalse($this->resolver->isValid('02377'));
    }

    /** @test */
    public function test_it_resolves_postal_codes_under_a_remapped_column_name()
    {
        // Schema-freedom: rename the villages.postal_code column and confirm
        // the resolver still finds it through the configured column lookup
        // rather than a hardcoded 'postal_code' string.
        config(['nusantara.columns.villages.postal_code.name' => 'custom_postal_code']);

        $this->artisan('migrate:fresh')->run();
        $this->seed(NusantaraCoreSeeder::class);

        $this->assertTrue(Schema::hasColumn('villages', 'custom_postal_code'));
        $this->assertFalse(Schema::hasColumn('villages', 'postal_code'));

        $villages = $this->resolver->resolve('23773');

        $this->assertGreaterThan(1, $villages->count());

        foreach ($villages as $village) {
            $this->assertSame('110101', $village->district->id);
        }
    }
}
