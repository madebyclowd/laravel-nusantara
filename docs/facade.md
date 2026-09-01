# Facade & Queries

[← Back to docs index](README.md)

The `Nusantara` facade is the main way to use this package. Every method below is cached automatically, so calling the same lookup twice does not hit your database twice.

```php
use MadeByClowd\Nusantara\Facades\Nusantara;
```

## Basic lookups

Every level (province, regency, district, village) works the same way: get all of them, get one by ID, or get the children of a parent.

```php
// All provinces
$provinces = Nusantara::provinces();

// One province by ID
$province = Nusantara::findProvince('11'); // Province model, or null if not found

// Regencies inside a province
$regencies = Nusantara::regenciesOf('11');

// One regency by ID
$regency = Nusantara::findRegency('1101');

// Districts inside a regency
$districts = Nusantara::districtsOf('1101');

// One district by ID
$district = Nusantara::findDistrict('110101');

// Villages inside a district
$villages = Nusantara::villagesOf('110101');

// One village by ID
$village = Nusantara::findVillage('1101012001');
```

Every `find*()` method transparently resolves legacy region codes too. If a region was split or renamed in a past year (for example, pre-2022 Papua), an old code still resolves to the current active record. No extra code needed on your end.

```php
$regency = Nusantara::findRegency('9101'); // resolves to the current '9301' record
```

## Search

Search names across every level at once, or scope it to just one:

```php
// Search all levels (needs at least 2 characters)
$results = Nusantara::search('Bakongan');
/*
[
    'provinces' => [...],
    'regencies' => [...],
    'districts' => [...],
    'villages'  => [...],
]
*/

// Scope to one level, and paginate
$results = Nusantara::search('Bakongan', limit: 20, offset: 0, scope: 'districts');
```

Queries shorter than 2 characters always return empty results, on purpose, so a single keystroke never scans the whole table.

If a search comes back empty because of a typo, fall back to fuzzy matching. It is never triggered automatically, you have to call it yourself:

```php
$results = Nusantara::search('Bakonagn') ?: Nusantara::searchFuzzy('Bakonagn');
```

## NIK (Indonesian national ID) parsing

```php
$nik = Nusantara::parseNik('1101011505900001'); // throws NikValidationException if malformed

$nik->gender;      // 'male' | 'female'
$nik->birthDate;   // Carbon instance
$nik->sequence;    // the NIK's own sequence digits

$nik->district();  // District model, or null (legacy codes resolve automatically)
$nik->regency();
$nik->province();

Nusantara::isValidNik('1101011505900001'); // bool, never throws
```

`isValidNik()` only checks the NIK's own shape (length, digits, embedded date). It does not check whether the embedded region codes point to a real region, use `$nik->district()` for that.

## Postal codes

```php
$villages = Nusantara::resolvePostalCode('23773'); // Collection of matching villages
$villages->first()->district->regency->province;

Nusantara::isValidPostalCode('23773'); // bool, format check only (5 digits, first digit 1-9)
```

## Reverse geocoding & GeoJSON

```php
// Find the region containing a coordinate
$village = Nusantara::findByCoordinate(lat: 2.931, lng: 97.484, level: 'village');

// Export any region as GeoJSON
$geojson = $village->toGeoJson();
```

Both need the `boundary` column enabled and populated, see [Geographic Boundaries & Maps](geographic-boundaries.md) for the full setup and which databases support it.

## Clearing the cache

```php
Nusantara::clearCache();
```
