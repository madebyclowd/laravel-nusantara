<?php

namespace MadeByClowd\Nusantara\Support;

use Carbon\Carbon;
use MadeByClowd\Nusantara\Exceptions\NikValidationException;

class NikParser
{
    public function __construct(protected ?RegionQuery $regionQuery = null) {}

    /**
     * Parse a 16-digit NIK into its component parts.
     *
     * @throws NikValidationException
     */
    public function parse(string $nik, ?int $referenceYear = null, ?int $centuryOverride = null): NikInfo
    {
        [$provinceId, $regencyId, $districtId, $gender, $birthDate, $sequence]
            = $this->parseParts($nik, $referenceYear, $centuryOverride);

        return new NikInfo(
            nik: $nik,
            provinceId: $provinceId,
            regencyId: $regencyId,
            districtId: $districtId,
            gender: $gender,
            birthDate: $birthDate,
            sequence: $sequence,
            regionQuery: $this->regionQuery,
        );
    }

    /**
     * Check whether a NIK is structurally valid, without throwing.
     *
     * This only validates the NIK's own format (length, digits, embedded
     * date, non-zero region/sequence segments) — it does not check whether
     * the embedded province/regency/district codes resolve to real regions.
     * Use NikInfo::district()/regency()/province() for that, which also
     * transparently handles pre-split legacy region codes.
     */
    public function isValid(string $nik, ?int $referenceYear = null, ?int $centuryOverride = null): bool
    {
        try {
            $this->parseParts($nik, $referenceYear, $centuryOverride);

            return true;
        } catch (NikValidationException) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: Carbon, 5: string}
     *
     * @throws NikValidationException
     */
    protected function parseParts(string $nik, ?int $referenceYear, ?int $centuryOverride): array
    {
        $nik = trim($nik);

        if (strlen($nik) !== 16) {
            throw new NikValidationException('NIK must be exactly 16 characters long.');
        }

        if (! ctype_digit($nik)) {
            throw new NikValidationException('NIK must contain only numeric digits.');
        }

        $provinceCode = substr($nik, 0, 2);
        $regencyCode = substr($nik, 2, 2);
        $districtCode = substr($nik, 4, 2);

        if ($provinceCode === '00') {
            throw new NikValidationException("Invalid NIK: province code cannot be '00'.");
        }

        if ($regencyCode === '00') {
            throw new NikValidationException("Invalid NIK: regency code cannot be '00'.");
        }

        if ($districtCode === '00') {
            throw new NikValidationException("Invalid NIK: district code cannot be '00'.");
        }

        $provinceId = $provinceCode;
        $regencyId = $provinceCode.$regencyCode;
        $districtId = $provinceCode.$regencyCode.$districtCode;

        // Day-of-month is offset by 40 to encode gender (a longstanding NIK
        // convention, not a bug): 1-31 is male, 41-71 is female (day + 40).
        $dayPart = (int) substr($nik, 6, 2);

        if ($dayPart >= 1 && $dayPart <= 31) {
            $gender = 'male';
            $day = $dayPart;
        } elseif ($dayPart >= 41 && $dayPart <= 71) {
            $gender = 'female';
            $day = $dayPart - 40;
        } else {
            throw new NikValidationException("Invalid NIK: day part '{$nik[6]}{$nik[7]}' is invalid.");
        }

        $month = (int) substr($nik, 8, 2);

        if ($month < 1 || $month > 12) {
            throw new NikValidationException("Invalid NIK: month part '{$nik[8]}{$nik[9]}' is invalid.");
        }

        $yearPart = (int) substr($nik, 10, 2);

        if ($centuryOverride !== null) {
            $year = $centuryOverride < 100
                ? $centuryOverride * 100 + $yearPart
                : intdiv($centuryOverride, 100) * 100 + $yearPart;
        } else {
            // Century-threshold heuristic: assume 2000s unless that would
            // land in the future relative to the reference year, in which
            // case the NIK must have been issued in the 1900s instead.
            $currentYear = $referenceYear ?? (int) date('Y');
            $year = 2000 + $yearPart;

            if ($year > $currentYear) {
                $year = 1900 + $yearPart;
            }
        }

        if (! checkdate($month, $day, $year)) {
            throw new NikValidationException("Invalid NIK: birth date {$year}-{$month}-{$day} is invalid.");
        }

        $birthDate = Carbon::createFromDate($year, $month, $day)->startOfDay();

        $sequence = substr($nik, 12, 4);

        if ($sequence === '0000') {
            throw new NikValidationException("Invalid NIK: sequence number cannot be '0000'.");
        }

        return [$provinceId, $regencyId, $districtId, $gender, $birthDate, $sequence];
    }
}
