<?php

namespace MadeByClowd\Nusantara\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use MadeByClowd\Nusantara\Support\PostalCodeResolver;

class ValidPostalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! is_string($value) || ! (new PostalCodeResolver)->isValid($value)) {
            $fail('The :attribute is not a valid postal code.');
        }
    }
}
