<?php

namespace MadeByClowd\Nusantara;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;
use MadeByClowd\Nusantara\Exceptions\NikValidationException;
use MadeByClowd\Nusantara\Support\Geocoder;
use MadeByClowd\Nusantara\Support\NikInfo;
use MadeByClowd\Nusantara\Support\NikParser;
use MadeByClowd\Nusantara\Support\RegionQuery;
use MadeByClowd\Nusantara\Support\RegionSearch;

class NusantaraService
{
    use HasNusantaraCaching;

    protected ?RegionQuery $regionQuery = null;

    protected ?RegionSearch $regionSearch = null;

    protected ?NikParser $nikParser = null;

    protected ?Geocoder $geocoder = null;

    protected function regionQuery(): RegionQuery
    {
        return $this->regionQuery ??= new RegionQuery;
    }

    protected function regionSearch(): RegionSearch
    {
        return $this->regionSearch ??= new RegionSearch($this->regionQuery());
    }

    protected function nikParser(): NikParser
    {
        return $this->nikParser ??= new NikParser($this->regionQuery());
    }

    protected function geocoder(): Geocoder
    {
        return $this->geocoder ??= new Geocoder($this->regionQuery());
    }

    /**
     * Get the configured Province model class name.
     */
    public function getProvinceModel(): string
    {
        return $this->regionQuery()->getProvinceModel();
    }

    /**
     * Get the configured Regency model class name.
     */
    public function getRegencyModel(): string
    {
        return $this->regionQuery()->getRegencyModel();
    }

    /**
     * Get the configured District model class name.
     */
    public function getDistrictModel(): string
    {
        return $this->regionQuery()->getDistrictModel();
    }

    /**
     * Get the configured Village model class name.
     */
    public function getVillageModel(): string
    {
        return $this->regionQuery()->getVillageModel();
    }

    /**
     * Fetch all provinces.
     */
    public function provinces(): Collection
    {
        return $this->regionQuery()->provinces();
    }

    /**
     * Fetch a province by ID.
     *
     * @return Model|null
     */
    public function findProvince(string $id)
    {
        return $this->regionQuery()->findProvince($id);
    }

    /**
     * Fetch regencies of a province.
     */
    public function regenciesOf(string $provinceId): Collection
    {
        return $this->regionQuery()->regenciesOf($provinceId);
    }

    /**
     * Fetch a regency by ID.
     *
     * @return Model|null
     */
    public function findRegency(string $id)
    {
        return $this->regionQuery()->findRegency($id);
    }

    /**
     * Fetch districts of a regency.
     */
    public function districtsOf(string $regencyId): Collection
    {
        return $this->regionQuery()->districtsOf($regencyId);
    }

    /**
     * Fetch a district by ID.
     *
     * @return Model|null
     */
    public function findDistrict(string $id)
    {
        return $this->regionQuery()->findDistrict($id);
    }

    /**
     * Fetch villages of a district.
     */
    public function villagesOf(string $districtId): Collection
    {
        return $this->regionQuery()->villagesOf($districtId);
    }

    /**
     * Fetch a village by ID.
     *
     * @return Model|null
     */
    public function findVillage(string $id)
    {
        return $this->regionQuery()->findVillage($id);
    }

    /**
     * Search regional names dynamically across all levels.
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->regionSearch()->search($query, $limit);
    }

    /**
     * Parse a 16-digit NIK into its component parts.
     *
     * @throws NikValidationException
     */
    public function parseNik(string $nik, ?int $referenceYear = null, ?int $centuryOverride = null): NikInfo
    {
        return $this->nikParser()->parse($nik, $referenceYear, $centuryOverride);
    }

    /**
     * Check whether a NIK is structurally valid.
     */
    public function isValidNik(string $nik, ?int $referenceYear = null, ?int $centuryOverride = null): bool
    {
        return $this->nikParser()->isValid($nik, $referenceYear, $centuryOverride);
    }

    /**
     * Reverse-geocode a coordinate to the region containing it.
     *
     * @return Model|null
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function findByCoordinate(float $lat, float $lng, string $level = 'village')
    {
        return $this->geocoder()->findByCoordinate($lat, $lng, $level);
    }
}
