<?php

namespace MadeByClowd\Nusantara\Support;

use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;

class RegionSearch
{
    use HasNusantaraCaching;

    protected RegionQuery $regionQuery;

    public function __construct(?RegionQuery $regionQuery = null)
    {
        $this->regionQuery = $regionQuery ?? new RegionQuery;
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

            $results['provinces'] = $this->regionQuery->getProvinceModel()::where($provName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['regencies'] = $this->regionQuery->getRegencyModel()::where($regName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['districts'] = $this->regionQuery->getDistrictModel()::where($distName, 'like', "%{$query}%")->limit($limit)->get()->toArray();
            $results['villages'] = $this->regionQuery->getVillageModel()::where($vilName, 'like', "%{$query}%")->limit($limit)->get()->toArray();

            return $results;
        });
    }
}
