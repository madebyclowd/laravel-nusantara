<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Rules;

use Illuminate\Support\Facades\Validator;
use MadeByClowd\Nusantara\Rules\ValidPostalCode;
use MadeByClowd\Nusantara\Tests\TestCase;

class ValidPostalCodeTest extends TestCase
{
    /** @test */
    public function test_it_passes_for_a_well_formed_postal_code()
    {
        $validator = Validator::make(
            ['postal_code' => '23773'],
            ['postal_code' => [new ValidPostalCode]]
        );

        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function test_it_fails_for_a_malformed_postal_code()
    {
        $validator = Validator::make(
            ['postal_code' => '1234'],
            ['postal_code' => [new ValidPostalCode]]
        );

        $this->assertFalse($validator->passes());
        $this->assertSame('The postal code is not a valid postal code.', $validator->errors()->first('postal_code'));
    }

    /** @test */
    public function test_it_fails_for_a_non_string_value()
    {
        $validator = Validator::make(
            ['postal_code' => 12345],
            ['postal_code' => [new ValidPostalCode]]
        );

        $this->assertFalse($validator->passes());
    }
}
