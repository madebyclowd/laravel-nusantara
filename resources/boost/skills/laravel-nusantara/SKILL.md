---
name: laravel-nusantara
description: "Use this skill for madebyclowd/laravel-nusantara package in Laravel applications. ALWAYS use this skill when interacting with Indonesia's administrative regions database, models, facades, seeders, or API endpoints. Trigger when: querying, searching, or displaying provinces, regencies, districts, or villages; performing migration/seeding customization; accessing dynamic relation traits; writing custom queries for custom table/column mappings; configuring API middleware; or running php artisan nusantara:install. Covers: HasDynamicNusantaraFields, Nusantara facade, caching, API controllers, and schema overrides. Do not use for frontend-only tasks"
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

## Common Pitfalls

- Hardcoding table names (`provinces`, `regencies`, `districts`, `villages`) in raw SQL or migrations.
- Accessing database column names directly instead of utilizing logical properties (e.g. `$model->custom_name` instead of `$model->name`).
- Accessing relation attributes on models before ensuring that the database tables are migrated and seeded.
- Bypassing the `Nusantara` facade for read queries, which disables query caching.
