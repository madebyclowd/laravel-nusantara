<?php

namespace MadeByClowd\Nusantara\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;
use MadeByClowd\Nusantara\Models;

class RegionQuery
{
    use HasNusantaraCaching;

    protected ?LegacyRegionResolver $legacyResolver = null;

    protected function legacyResolver(): LegacyRegionResolver
    {
        return $this->legacyResolver ??= new LegacyRegionResolver;
    }

    /**
     * Get the configured Province model class name.
     */
    public function getProvinceModel(): string
    {
        return config('nusantara.models.province', Models\Province::class);
    }

    /**
     * Get the configured Regency model class name.
     */
    public function getRegencyModel(): string
    {
        return config('nusantara.models.regency', Models\Regency::class);
    }

    /**
     * Get the configured District model class name.
     */
    public function getDistrictModel(): string
    {
        return config('nusantara.models.district', Models\District::class);
    }

    /**
     * Get the configured Village model class name.
     */
    public function getVillageModel(): string
    {
        return config('nusantara.models.village', Models\Village::class);
    }

    /**
     * Fetch all provinces.
     */
    public function provinces(): Collection
    {
        return $this->remember('provinces', function () {
            return $this->getProvinceModel()::all();
        });
    }

    /**
     * Fetch a province by ID.
     *
     * @return Model|null
     */
    public function findProvince(string $id)
    {
        return $this->remember("province.{$id}", function () use ($id) {
            return $this->getProvinceModel()::find($id);
        });
    }

    /**
     * Fetch regencies of a province.
     */
    public function regenciesOf(string $provinceId): Collection
    {
        return $this->remember("regencies.{$provinceId}", function () use ($provinceId) {
            $province = $this->findProvince($provinceId);

            return $province ? $province->regencies : new Collection;
        });
    }

    /**
     * Fetch a regency by ID.
     *
     * @return Model|null
     */
    public function findRegency(string $id)
    {
        return $this->remember("regency.{$id}", function () use ($id) {
            return $this->getRegencyModel()::find($id)
                ?? $this->findRegencyByLegacyId($id);
        });
    }

    /**
     * Fall back to a legacy regency ID's current equivalent when the direct
     * lookup misses (e.g. a pre-2022 Papua regency code).
     *
     * @return Model|null
     */
    protected function findRegencyByLegacyId(string $id)
    {
        $resolvedId = $this->legacyResolver()->resolveLegacyId($id);

        return $resolvedId !== $id ? $this->getRegencyModel()::find($resolvedId) : null;
    }

    /**
     * Fetch districts of a regency.
     */
    public function districtsOf(string $regencyId): Collection
    {
        return $this->remember("districts.{$regencyId}", function () use ($regencyId) {
            $regency = $this->findRegency($regencyId);

            return $regency ? $regency->districts : new Collection;
        });
    }

    /**
     * Fetch a district by ID.
     *
     * @return Model|null
     */
    public function findDistrict(string $id)
    {
        return $this->remember("district.{$id}", function () use ($id) {
            return $this->getDistrictModel()::find($id)
                ?? $this->findDistrictByLegacyId($id);
        });
    }

    /**
     * @return Model|null
     */
    protected function findDistrictByLegacyId(string $id)
    {
        $resolvedId = $this->legacyResolver()->resolveLegacyId($id);

        return $resolvedId !== $id ? $this->getDistrictModel()::find($resolvedId) : null;
    }

    /**
     * Fetch villages of a district.
     */
    public function villagesOf(string $districtId): Collection
    {
        return $this->remember("villages.{$districtId}", function () use ($districtId) {
            $district = $this->findDistrict($districtId);

            return $district ? $district->villages : new Collection;
        });
    }

    /**
     * Fetch a village by ID.
     *
     * @return Model|null
     */
    public function findVillage(string $id)
    {
        return $this->remember("village.{$id}", function () use ($id) {
            return $this->getVillageModel()::find($id)
                ?? $this->findVillageByLegacyId($id);
        });
    }

    /**
     * @return Model|null
     */
    protected function findVillageByLegacyId(string $id)
    {
        $resolvedId = $this->legacyResolver()->resolveLegacyId($id);

        return $resolvedId !== $id ? $this->getVillageModel()::find($resolvedId) : null;
    }
}
