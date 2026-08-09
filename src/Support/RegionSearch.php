<?php

namespace MadeByClowd\Nusantara\Support;

use MadeByClowd\Nusantara\Concerns\HasNusantaraCaching;

class RegionSearch
{
    use HasNusantaraCaching;

    /**
     * The valid region levels a search may be scoped to.
     */
    protected const VALID_SCOPES = ['provinces', 'regencies', 'districts', 'villages'];

    /**
     * The maximum number of rows pulled per level as fuzzy-match candidates,
     * so levenshtein() is never run against an unfiltered table scan.
     */
    protected const FUZZY_CANDIDATE_LIMIT = 500;

    protected RegionQuery $regionQuery;

    public function __construct(?RegionQuery $regionQuery = null)
    {
        $this->regionQuery = $regionQuery ?? new RegionQuery;
    }

    /**
     * Search regional names dynamically across all levels, or a single
     * scoped level when $scope is given.
     */
    public function search(string $query, int $limit = 20, int $offset = 0, ?string $scope = null): array
    {
        $this->validateScope($scope);

        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'search.'.md5($query).".{$limit}.{$offset}.".($scope ?? 'all');

        return $this->remember($cacheKey, function () use ($query, $limit, $offset, $scope) {
            $results = [
                'provinces' => [],
                'regencies' => [],
                'districts' => [],
                'villages' => [],
            ];

            foreach ($this->levelsForScope($scope) as $level) {
                $model = $this->modelForLevel($level);
                $column = $this->columnForLevel($level);

                $results[$level] = $model::where($column, 'like', "%{$query}%")
                    ->offset($offset)
                    ->limit($limit)
                    ->get()
                    ->toArray();
            }

            return $results;
        });
    }

    /**
     * Fuzzy fallback for when search() misses due to typos. Not invoked
     * automatically by search() — callers opt in explicitly, e.g.
     * `$service->search($q) ?: $service->searchFuzzy($q)`.
     *
     * Bounds the levenshtein() comparisons to a cheap SQL-level candidate
     * set (rows sharing the query's first characters) rather than scanning
     * the whole table.
     */
    public function searchFuzzy(string $query, int $limit = 20, ?string $scope = null, int $maxDistance = 2): array
    {
        $this->validateScope($scope);

        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'searchFuzzy.'.md5($query).".{$limit}.".($scope ?? 'all').".{$maxDistance}";

        return $this->remember($cacheKey, function () use ($query, $limit, $scope, $maxDistance) {
            $results = [
                'provinces' => [],
                'regencies' => [],
                'districts' => [],
                'villages' => [],
            ];

            $prefix = mb_substr($query, 0, 2);
            $needle = mb_strtolower($query);

            foreach ($this->levelsForScope($scope) as $level) {
                $model = $this->modelForLevel($level);
                $column = $this->columnForLevel($level);

                $candidates = $model::where($column, 'like', "{$prefix}%")
                    ->limit(self::FUZZY_CANDIDATE_LIMIT)
                    ->get();

                $results[$level] = $candidates
                    ->map(function ($region) use ($column, $needle) {
                        return [
                            'region' => $region,
                            'distance' => levenshtein($needle, mb_strtolower((string) $region->{$column})),
                        ];
                    })
                    ->filter(fn (array $scored) => $scored['distance'] <= $maxDistance)
                    ->sortBy('distance')
                    ->take($limit)
                    ->values()
                    ->map(fn (array $scored) => $scored['region']->toArray())
                    ->all();
            }

            return $results;
        });
    }

    /**
     * Ensure a given scope is one of the supported region levels.
     */
    protected function validateScope(?string $scope): void
    {
        if ($scope !== null && ! in_array($scope, self::VALID_SCOPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid search scope [{$scope}]. Expected one of: ".implode(', ', self::VALID_SCOPES).', or null.'
            );
        }
    }

    /**
     * Resolve which level(s) to query for a given scope.
     *
     * @return array<int, string>
     */
    protected function levelsForScope(?string $scope): array
    {
        return $scope === null ? self::VALID_SCOPES : [$scope];
    }

    /**
     * Resolve the configured model class for a region level.
     */
    protected function modelForLevel(string $level): string
    {
        return match ($level) {
            'provinces' => $this->regionQuery->getProvinceModel(),
            'regencies' => $this->regionQuery->getRegencyModel(),
            'districts' => $this->regionQuery->getDistrictModel(),
            'villages' => $this->regionQuery->getVillageModel(),
            default => throw new \InvalidArgumentException("Unknown region level [{$level}]."),
        };
    }

    /**
     * Resolve the configured searchable name column for a region level.
     */
    protected function columnForLevel(string $level): string
    {
        return config("nusantara.columns.{$level}.name.name", 'name');
    }
}
