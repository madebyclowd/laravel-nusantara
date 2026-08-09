<?php

namespace MadeByClowd\Nusantara\Support\Data;

class HistoricalRegionMap
{
    /**
     * Legacy regency-code prefix (the first 4 digits of a regency, district,
     * or village ID) mapped to its current active prefix. The remaining
     * suffix digits (district/village portion) are preserved on substitution.
     *
     * Ported from py-nusantara's HISTORICAL_REGENCY_MAP.
     *
     * Keys are written as numeric strings but PHP casts them to int array
     * keys automatically — lookups via a string prefix still work correctly
     * since PHP coerces the comparison either way.
     *
     * @var array<int, string>
     */
    public static array $regencies = [
        // --- Papua splits in 2022 ---
        // Merauke under Papua (9101) split into Papua Selatan (9301)
        '9101' => '9301',
        // Boven Digoel under Papua (9116) split into Papua Selatan (9302)
        '9116' => '9302',
        // Mappi under Papua (9117) split into Papua Selatan (9303)
        '9117' => '9303',
        // Asmat under Papua (9118) split into Papua Selatan (9304)
        '9118' => '9304',

        // Nabire under Papua (9104) split into Papua Tengah (9401)
        '9104' => '9401',
        // Puncak Jaya under Papua (9107) split into Papua Tengah (9402)
        '9107' => '9402',
        // Paniai under Papua (9108) split into Papua Tengah (9403)
        '9108' => '9403',
        // Mimika under Papua (9109) split into Papua Tengah (9404)
        '9109' => '9404',
        // Puncak under Papua (9125) split into Papua Tengah (9405)
        '9125' => '9405',
        // Dogiyai under Papua (9126) split into Papua Tengah (9406)
        '9126' => '9406',
        // Intan Jaya under Papua (9127) split into Papua Tengah (9407)
        '9127' => '9407',
        // Deiyai under Papua (9128) split into Papua Tengah (9408)
        '9128' => '9408',

        // Jayawijaya under Papua (9102) split into Papua Pegunungan (9501)
        '9102' => '9501',
        // Pegunungan Bintang under Papua (9112) split into Papua Pegunungan (9502)
        '9112' => '9502',
        // Yahukimo under Papua (9113) split into Papua Pegunungan (9503)
        '9113' => '9503',
        // Tolikara under Papua (9114) split into Papua Pegunungan (9504)
        '9114' => '9504',
        // Mamberamo Tengah under Papua (9121) split into Papua Pegunungan (9505)
        '9121' => '9505',
        // Yalimo under Papua (9122) split into Papua Pegunungan (9506)
        '9122' => '9506',
        // Lanny Jaya under Papua (9123) split into Papua Pegunungan (9507)
        '9123' => '9507',
        // Nduga under Papua (9124) split into Papua Pegunungan (9508)
        '9124' => '9508',

        // Sorong (Regency) under Papua Barat (9201) split into Papua Barat Daya (9601)
        '9201' => '9601',
        // Sorong Selatan under Papua Barat (9206) split into Papua Barat Daya (9602)
        '9206' => '9602',
        // Raja Ampat under Papua Barat (9209) split into Papua Barat Daya (9603)
        '9209' => '9603',
        // Tambrauw under Papua Barat (9211) split into Papua Barat Daya (9604)
        '9211' => '9604',
        // Maybrat under Papua Barat (9212) split into Papua Barat Daya (9605)
        '9212' => '9605',
        // Kota Sorong under Papua Barat (9271) split into Papua Barat Daya (9671)
        '9271' => '9671',

        // --- Kalimantan Utara splits in 2012 ---
        // Bulungan under Kaltim (6406) split into Kaltara (6501)
        '6406' => '6501',
        // Malinau under Kaltim (6410) split into Kaltara (6502)
        '6410' => '6502',
        // Nunukan under Kaltim (6411) split into Kaltara (6503)
        '6411' => '6503',
        // Tana Tidung under Kaltim (6414) split into Kaltara (6504)
        '6414' => '6504',
        // Kota Tarakan under Kaltim (6473) split into Kaltara (6571)
        '6473' => '6571',

        // --- Sulawesi Barat splits in 2004 ---
        // Mamuju Utara under Sulsel (7322) split into Sulbar (7601)
        '7322' => '7601',
        // Mamuju under Sulsel (7308) split into Sulbar (7602)
        '7308' => '7602',
        // Mamasa under Sulsel (7318) split into Sulbar (7603)
        '7318' => '7603',
        // Polewali Mandar under Sulsel (7304) split into Sulbar (7604)
        '7304' => '7604',
        // Majene under Sulsel (7305) split into Sulbar (7605)
        '7305' => '7605',

        // --- Banten splits in 2000 ---
        // Pandeglang under Jabar (3201) split into Banten (3601)
        '3201' => '3601',
        // Lebak under Jabar (3202) split into Banten (3602)
        '3202' => '3602',
        // Tangerang under Jabar (3203) split into Banten (3603)
        '3203' => '3603',
        // Serang under Jabar (3204) split into Banten (3604)
        '3204' => '3604',
        // Kota Tangerang under Jabar (3271) split into Banten (3671)
        '3271' => '3671',
        // Kota Cilegon under Jabar (3272) split into Banten (3672)
        '3272' => '3672',

        // --- Gorontalo splits in 2000 ---
        // Boalemo under Sulut (7105) split into Gorontalo (7501)
        '7105' => '7501',
        // Gorontalo under Sulut (7104) split into Gorontalo (7502)
        '7104' => '7502',
        // Kota Gorontalo under Sulut (7171) split into Gorontalo (7571)
        '7171' => '7571',

        // --- Kepulauan Riau splits in 2002 ---
        // Bintan under Riau (1402) split into Kepri (2101)
        '1402' => '2101',
        // Karimun under Riau (1407) split into Kepri (2102)
        '1407' => '2102',
        // Natuna under Riau (1408) split into Kepri (2103)
        '1408' => '2103',
        // Kota Batam under Riau (1471) split into Kepri (2171)
        '1471' => '2171',
        // Kota Tanjungpinang under Riau (1472) split into Kepri (2172)
        '1472' => '2172',

        // --- Kepulauan Bangka Belitung splits in 2000 ---
        // Bangka under Sumsel (1607) split into Babel (1901)
        '1607' => '1901',
        // Belitung under Sumsel (1608) split into Babel (1902)
        '1608' => '1902',
        // Kota Pangkal Pinang under Sumsel (1671) split into Babel (1971)
        '1671' => '1971',
    ];
}
