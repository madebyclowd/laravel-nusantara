<?php

namespace MadeByClowd\Nusantara;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NusantaraService
{
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
            return $this->getRegencyModel()::find($id);
        });
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
            return $this->getDistrictModel()::find($id);
        });
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
            return $this->getVillageModel()::find($id);
        });
    }

    /**
     * Search regional names dynamically across all levels.
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        return $this->remember('search.'.md5($query).".{$limit}", function () use ($query, $limit) {
            $results = [
                'provinces' => [],
                'regencies' => [],
                'districts' => [],
                'villages' => [],
            ];

            $provName = config('nusantara.columns.provinces.name.name', 'name');
            $regName = config('nusantara.columns.regencies.name.name', 'name');
            $distName = config('nusantara.columns.districts.name.name', 'name');
            $vilName = config('nusantara.columns.villages.name.name', 'name');

            $results['provinces'] = $this->getProvinceModel()::where($provName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['regencies'] = $this->getRegencyModel()::where($regName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['districts'] = $this->getDistrictModel()::where($distName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['villages'] = $this->getVillageModel()::where($vilName, 'like', "%{$query}%")->limit($limit)->get()->toArray();

            return $results;
        });
    }

    /**
     * Clear all cached regional queries.
     */
    public function clearCache(): bool
    {
        $prefix = config('nusantara.cache.prefix', 'nusantara');

        if (config('nusantara.cache.enabled', true)) {
            try {
                Cache::tags([$prefix])->flush();

                return true;
            } catch (\BadMethodCallException $e) {
                // Fallback to flushing entire cache if tags are unsupported
                return Cache::flush();
            }
        }

        return false;
    }

    /**
     * Helper to wrap cache remembers.
     *
     * @return mixed
     */
    protected function remember(string $key, \Closure $callback)
    {
        $enabled = config('nusantara.cache.enabled', true);

        if (! $enabled) {
            return $callback();
        }

        $prefix = config('nusantara.cache.prefix', 'nusantara');
        $ttl = config('nusantara.cache.ttl', 86400);

        try {
            return Cache::tags([$prefix])->remember("{$prefix}.{$key}", $ttl, $callback);
        } catch (\BadMethodCallException $e) {
            // Fallback for cache drivers that do not support tags (e.g. database, file)
            return Cache::remember("{$prefix}.{$key}", $ttl, $callback);
        }
    }
}
