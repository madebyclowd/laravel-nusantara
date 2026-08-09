<?php

namespace MadeByClowd\Nusantara\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use MadeByClowd\Nusantara\Support\NikParser;

class ValidNik implements ValidationRule
{
    public function __construct(
        protected ?int $referenceYear = null,
        protected ?int $centuryOverride = null,
    ) {}

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! is_string($value) || ! (new NikParser)->isValid($value, $this->referenceYear, $this->centuryOverride)) {
            $fail('The :attribute is not a valid NIK.');
        }
    }
}
