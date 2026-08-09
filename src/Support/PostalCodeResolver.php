<?php

namespace MadeByClowd\Nusantara\Support;

use Illuminate\Database\Eloquent\Collection;
use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;
use MadeByClowd\Nusantara\Exceptions\PostalCodeValidationException;

class PostalCodeResolver
{
    use HasNusantaraCaching;

    protected ?RegionQuery $regionQuery = null;

    public function __construct(?RegionQuery $regionQuery = null)
    {
        $this->regionQuery = $regionQuery;
    }

    protected function regionQuery(): RegionQuery
    {
        return $this->regionQuery ??= new RegionQuery;
    }

    /**
     * Resolve every village matching a postal code, eager-loaded with its
     * upward hierarchy (district, regency, province).
     *
     * A well-formed postal code that simply isn't assigned to any seeded
     * village resolves to an empty collection rather than throwing.
     *
     * @throws PostalCodeValidationException
     */
    public function resolve(string $postalCode): Collection
    {
        if (! $this->isValid($postalCode)) {
            throw new PostalCodeValidationException(
                "Invalid postal code: '{$postalCode}'. A postal code must be exactly 5 digits and cannot start with '0'."
            );
        }

        return $this->remember("postal-code.{$postalCode}", function () use ($postalCode) {
            $column = config('nusantara.columns.villages.postal_code.name', 'postal_code');

            return $this->regionQuery()->getVillageModel()::query()
                ->where($column, $postalCode)
                ->with(['district.regency.province'])
                ->get();
        });
    }

    /**
     * Check whether a postal code is well-formed, without checking whether
     * it is actually assigned to a village.
     *
     * A valid Indonesian postal code is exactly 5 numeric digits and cannot
     * start with '0'.
     */
    public function isValid(string $postalCode): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{4}$/', $postalCode);
    }
}
