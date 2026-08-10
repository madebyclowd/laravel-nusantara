---
name: laravel-nusantara
description: "Use this skill for madebyclowd/laravel-nusantara package in Laravel applications. ALWAYS use this skill when interacting with Indonesia's administrative regions database, models, facades, seeders, or API endpoints. Trigger when: querying, searching, or displaying provinces, regencies, districts, or villages; performing migration/seeding customization; accessing dynamic relation traits; writing custom queries for custom table/column mappings; configuring API middleware; parsing/validating NIK numbers; resolving postal codes; reverse-geocoding coordinates; exporting GeoJSON boundaries; or running php artisan nusantara:install. Covers: HasDynamicNusantaraFields, Nusantara facade, caching, API controllers, schema overrides, NIK parsing, postal code resolution, fuzzy/scoped search, legacy region-code resolution, and reverse geocoding. Do not use for frontend-only tasks"
license: MIT
metadata:
  author: madebyclowd
---

# Laravel Nusantara Development

## Documentation

Use `search-docs` or refer to the package configuration file at `config/nusantara.php` for details on schema mappings and API preferences.

## Quick Reference

### Installation and Setup

Run the setup wizard to publish the configuration, run migrations, and seed regional data streamingly:
```bash
php artisan nusantara:install
```

To manually republish assets or Laravel Boost skills:
```bash
php artisan vendor:publish --tag=nusantara-config
php artisan vendor:publish --tag=nusantara-migrations
php artisan vendor:publish --tag=nusantara-boost-skills
```

If geographic boundaries (polygons) are required, enable the `boundary` columns in the config file and run the boundary downloader:
```bash
php artisan nusantara:download-boundaries
```

### Config Customization Example

The package allows full schema freedom. Below is an example of customized tables and columns in `config/nusantara.php`:
```php
return [
    'tables' => [
        'provinces' => 'idn_provinces',
        'regencies' => 'idn_regencies',
    ],
    'columns' => [
        'provinces' => [
            'name' => [
                'name' => 'province_title',
                'type' => 'string',
                'length' => 100,
            ],
            'capital' => [
                'enabled' => false,
            ],
        ],
    ],
];
```

### Dynamic Attribute Access

Always use logical attribute names (e.g., `name`, `capital`, `population`, `postal_code`) instead of custom column names. The `HasDynamicNusantaraFields` trait automatically resolves these logical names to the custom database columns:
```php
use MadeByClowd\Nusantara\Models\Province;

// Dynamic attribute mapping works transparently
$province = Province::find('11');
echo $province->name; // Automatically maps to 'province_title' if customized
```

### Eloquent Relationships

Use standard relations for traversal. All relationships resolve table and foreign key names dynamically based on configuration:
```php
// Province to Regencies (HasMany)
$province->regencies;

// Province to Districts (HasManyThrough)
$province->districts;

// Province to Villages (Custom Join builder)
$province->villages()->get();

// Regency to Districts (HasMany)
$regency->districts;

// Regency to Villages (HasManyThrough)
$regency->villages;

// District to Villages (HasMany)
$district->villages;
```

### Unified Facade

Prefer the `Nusantara` facade for fetching data to take advantage of tag-safe caching:
```php
use MadeByClowd\Nusantara\Facades\Nusantara;

// Retrieve all provinces
$provinces = Nusantara::provinces();

// Retrieve children regions
$regencies = Nusantara::regenciesOf($provinceId);
$districts = Nusantara::districtsOf($regencyId);
$villages = Nusantara::villagesOf($districtId);

// Search across all region levels (returns array of matching records)
$results = Nusantara::search('Bandung');

// Scope search to one level, paginate with limit/offset
$results = Nusantara::search('Bandung', limit: 20, offset: 0, scope: 'regencies');

// Fuzzy fallback for typos — NOT invoked automatically, call explicitly
$results = Nusantara::search('Bandngu') ?: Nusantara::searchFuzzy('Bandngu');
```

Valid `scope` values: `provinces`, `regencies`, `districts`, `villages` (or `null` for all levels).

### Legacy Region-Code Resolution

`findRegency()`, `findDistrict()`, and `findVillage()` transparently fall back to historical region codes (e.g. pre-2022 Papua splits, pre-2012 Kaltara, pre-2004 Sulbar, pre-2000 Banten/Gorontalo/Kepri/Babel). No extra call needed — a legacy ID just resolves to its current record:
```php
// '9101' was Papua's old Merauke prefix, now resolves under Papua Selatan (9301)
$regency = Nusantara::findRegency('910101');
```

### NIK Parsing and Validation

Parse or validate Indonesia's 16-digit national ID (NIK). Validation is structural only (region-code cascade resolution is lazy, not automatic):
```php
use MadeByClowd\Nusantara\Exceptions\NikValidationException;

$info = Nusantara::parseNik('3171012501990001'); // throws NikValidationException if malformed
$isValid = Nusantara::isValidNik('3171012501990001');

$info->district(); // lazy-resolved District model (or null), applies legacy fallback
$info->regency();
$info->province();
$info->toArray();
```

Use `MadeByClowd\Nusantara\Rules\ValidNik` as a Laravel validation rule.

### Postal Code Resolution

```php
use MadeByClowd\Nusantara\Exceptions\PostalCodeValidationException;

$villages = Nusantara::resolvePostalCode('40115'); // Collection of Village, eager-loaded district.regency.province
$isValid = Nusantara::isValidPostalCode('40115');
```

Requires `nusantara.columns.villages.postal_code.enabled`. Use `MadeByClowd\Nusantara\Rules\ValidPostalCode` as a Laravel validation rule.

### Reverse Geocoding

Resolve the region containing a coordinate. Requires the `boundary` column enabled (and populated via `nusantara:download-boundaries`) at every level from province down to the target `$level`:
```php
$village = Nusantara::findByCoordinate(-6.9147, 107.6098, 'village'); // level: province|regency|district|village
```
Throws `\InvalidArgumentException` for an invalid `$level`, `\RuntimeException` if a required level's `boundary` column isn't enabled.

### GeoJSON Export

Every model (`Province`, `Regency`, `District`, `Village`) has `toGeoJson()` via the `HasGeoBoundary` trait:
```php
$geojson = $province->toGeoJson();
// Polygon/MultiPolygon Feature if boundary is populated (coords swapped to GeoJSON [lng, lat] order),
// falls back to a Point Feature from lat/lng when boundary is disabled or null.
```

### Writing Custom Queries

If writing raw queries or custom Joins, never hardcode table or column names. Always fetch them from the config or from model helper instances:
```php
use MadeByClowd\Nusantara\Models\Province;

// Resolve table name dynamically
$tableName = (new Province)->getTable();

// Resolve column name dynamically from config
$columnName = config('nusantara.columns.provinces.name.name', 'name');

$results = DB::table($tableName)
    ->where($columnName, 'like', '%Java%')
    ->get();
```

## Verification Checklist

1. Verify that `config/nusantara.php` exists and specifies the correct database connection, table overrides, and enabled columns.
2. Verify that migrations are run with the current configuration values.
3. Ensure that custom database queries resolve table and column names dynamically from the config/models.
4. Verify that caching is functioning, and is tagged with the config-defined cache prefix.
5. If using REST API endpoints, ensure the configured middlewares and route prefixes match the project architecture.
6. If using geographic boundaries, verify that the `boundary` columns are enabled, migrations have been run or modified, and boundaries have been downloaded via `nusantara:download-boundaries`.
7. If using `findByCoordinate()`, verify `boundary` is enabled at every level down to (and including) the target `$level`, not just the target level itself.
8. If using `resolvePostalCode()`/`isValidPostalCode()`, verify `nusantara.columns.villages.postal_code.enabled`.

## Common Pitfalls

- Hardcoding table names (`provinces`, `regencies`, `districts`, `villages`) in raw SQL or migrations.
- Accessing database column names directly instead of utilizing logical properties (e.g. `$model->custom_name` instead of `$model->name`).
- Accessing relation attributes on models before ensuring that the database tables are migrated and seeded.
- Bypassing the `Nusantara` facade for read queries, which disables query caching.
- Querying boundary coordinates before running the boundary downloader.
- Hardcoding coordinate parsing assumptions without checking if spatial support is active (e.g. falling back to text representation if PostGIS/SpatiaLite is missing).
- Calling `->update([...])` directly on region models — none declare `$fillable`/`$guarded`, so mass assignment throws `MassAssignmentException`. Use `->forceFill([...])->save()`.
- Assuming `search()` does fuzzy matching automatically — it doesn't; call `searchFuzzy()` explicitly as a fallback.
- Assuming `parseNik()`/`isValidNik()` verify that embedded region codes correspond to real regions — validation is structural only. Region resolution is separate via `$info->district()`/`regency()`/`province()`.
- Passing an invalid `scope` to `search()`/`searchFuzzy()` — must be one of `provinces`, `regencies`, `districts`, `villages`, or `null`.
- Calling `findByCoordinate()` at `village` level while only `province`/`village` boundaries are enabled — every intermediate level's `boundary` column must also be enabled, or it throws `\RuntimeException`.
