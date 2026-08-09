<?php

namespace MadeByClowd\Nusantara\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NikInfo
{
    public function __construct(
        public readonly string $nik,
        public readonly string $provinceId,
        public readonly string $regencyId,
        public readonly string $districtId,
        public readonly string $gender,
        public readonly Carbon $birthDate,
        public readonly string $sequence,
        protected readonly ?RegionQuery $regionQuery = null,
    ) {}

    /**
     * Resolve the district this NIK's embedded code points to. Automatically
     * falls back to the legacy region-code map via RegionQuery::findDistrict()
     * when the code is a pre-split historical district ID.
     *
     * @return Model|null
     */
    public function district()
    {
        return $this->regionQuery?->findDistrict($this->districtId);
    }

    /**
     * Resolve the regency, preferring the one derived from a successfully
     * resolved district (which already carries any legacy-code correction)
     * over a direct lookup by the NIK's raw embedded regency code.
     *
     * @return Model|null
     */
    public function regency()
    {
        if ($district = $this->district()) {
            return $district->regency;
        }

        return $this->regionQuery?->findRegency($this->regencyId);
    }

    /**
     * Resolve the province, preferring the one derived from a successfully
     * resolved district/regency over a direct lookup by the NIK's raw
     * embedded province code.
     *
     * @return Model|null
     */
    public function province()
    {
        if ($district = $this->district()) {
            return $district->regency?->province;
        }

        return $this->regionQuery?->findProvince($this->provinceId);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'nik' => $this->nik,
            'province_id' => $this->provinceId,
            'regency_id' => $this->regencyId,
            'district_id' => $this->districtId,
            'gender' => $this->gender,
            'birth_date' => $this->birthDate->toDateString(),
            'sequence' => $this->sequence,
        ];
    }
}
