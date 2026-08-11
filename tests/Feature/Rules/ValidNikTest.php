<?php

namespace MadeByClowd\Nusantara\Tests\Feature\Rules;

use Illuminate\Support\Facades\Validator;
use MadeByClowd\Nusantara\Rules\ValidNik;
use MadeByClowd\Nusantara\Tests\TestCase;

class ValidNikTest extends TestCase
{
    /** @test */
    public function test_it_passes_for_a_structurally_valid_nik()
    {
        $validator = Validator::make(
            ['nik' => '1101011505900001'],
            ['nik' => [new ValidNik]]
        );

        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function test_it_fails_for_a_malformed_nik()
    {
        $validator = Validator::make(
            ['nik' => 'not-a-nik'],
            ['nik' => [new ValidNik]]
        );

        $this->assertFalse($validator->passes());
        $this->assertSame('The nik is not a valid NIK.', $validator->errors()->first('nik'));
    }

    /** @test */
    public function test_it_fails_for_a_non_string_value()
    {
        $validator = Validator::make(
            ['nik' => 12345],
            ['nik' => [new ValidNik]]
        );

        $this->assertFalse($validator->passes());
    }
}
