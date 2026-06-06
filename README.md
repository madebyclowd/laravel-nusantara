# Laravel Nusantara

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/madebyclowd/laravel-nusantara/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![License](https://img.shields.io/packagist/l/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/blob/main/LICENSE)

A highly customizable, enterprise-ready, and developer-friendly Laravel package for Indonesia's administrative regions database (Provinces, Regencies, Districts, and Villages). Compiled according to **Kepmendagri No 300.2.2-2138 Year 2025**.

---

## ⚡ Key Features

* **Complete Schema Freedom**: Use SQLite, PostgreSQL, MySQL, SQL Server, or any custom connection.
* **Custom Table & Column Names**: Rename any default table or column to match your corporate database schema guidelines.
* **Column Exclusion / Toggles**: Disable columns you don't need (e.g. `elevation`, `timezone`, `area`, `population`) to save storage space.
* **Dynamic Attribute Accessor Mapping**: Models use a transparent mapping layer. You can call `$province->name` in your codebase even if it is saved as `nama_provinsi` in the database.
* **Tag-Safe Query Caching**: Minimizes database overhead on repetitive queries using Laravel's caching layer with automatic tag/key prefixes.
* **Cascading Eloquent Relations**: Provides relationships from Province to Regency, District, and Villages (with optimized join methods for deep traversals).
* **Ready-to-Use JSON REST API**: Built-in endpoints protected by middleware and rate limiters for checkout/registration widgets.
* **Low-Memory Seeder**: Streams gzipped CSV data line-by-line, keeping memory consumption under 3MB during the seed.
* **On-Demand Geographic Boundaries**: Keep your package footprint small by downloading high-resolution GIS boundary coordinates only if and when you need them.
* **AI-Agent Ready**: Automatically registers its developer instructions with **Laravel Boost** (`SKILL.md`) to help coding assistants query the dataset correctly.

---

## 🚀 Requirements

- **PHP**: `^8.2`
- **Laravel Framework**: `^10.0`, `^11.0`, `^12.0`, or `^13.0`

---

## 📦 Installation

Install the package via Composer:

```bash
composer require madebyclowd/laravel-nusantara
```

### Option A: Interactive Setup (Recommended)

Run the interactive installer wizard which publishes configuration, executes database migrations, and seeds regional data:

```bash
php artisan nusantara:install
```

### Option B: Manual Setup

If you prefer custom automation or wish to review steps individually:

1. **Publish the configuration file:**
   ```bash
   php artisan vendor:publish --tag=nusantara-config
   ```

2. **Publish and customize migrations (Optional):**
   *(Note: By default, the package loads migrations automatically. If you want to customize migration files manually, set `'load_migrations' => false` in `config/nusantara.php` first).*
   ```bash
   php artisan vendor:publish --tag=nusantara-migrations
   ```

3. **Run database migrations:**
   ```bash
   php artisan migrate
   ```

4. **Seed database:**
   ```bash
   php artisan db:seed --class="MadeByClowd\Nusantara\Seeders\NusantaraCoreSeeder"
   ```

---

## 🗺️ Geographic Boundaries (GIS)

If your application requires geographic boundary shapes (polygons), you can download and seed them on-demand:

1. Enable the `boundary` column for the desired levels (provinces, regencies, districts, or villages) in `config/nusantara.php`:
   ```php
   'columns' => [
       'provinces' => [
           // ...
           'boundary' => ['name' => 'boundary', 'enabled' => true],
       ],
   ]
   ```
2. Run the boundary download Artisan command:
   ```bash
   php artisan nusantara:download-boundaries
   ```

*(Note: The command will download, verify checksums, and seed the high-resolution GIS coordinates into your database automatically).*

---

## ⚙️ Configuration

The configuration is located at `config/nusantara.php`. Below is a detailed breakdown of what you can customize:

```php
return [
    // Automatically load migrations from the package
    'load_migrations' => true,

    // Database connection to use (null defaults to app connection)
    'connection' => null,

    // Custom table names
    'tables' => [
        'provinces' => 'provinces',
        'regencies' => 'regencies',
        'districts' => 'districts',
        'villages'  => 'villages',
    ],

    // Enable or disable foreign key constraints
    'enable_foreign_keys' => true,

    // Map Eloquent models. Replace default classes with your own extensions
    'models' => [
        'province' => \MadeByClowd\Nusantara\Models\Province::class,
        'regency'  => \MadeByClowd\Nusantara\Models\Regency::class,
        'district' => \MadeByClowd\Nusantara\Models\District::class,
        'village'  => \MadeByClowd\Nusantara\Models\Village::class,
    ],

    // Query Caching Configuration
    'cache' => [
        'enabled' => true,
        'ttl'     => 86400, // 24 hours
        'prefix'  => 'nusantara',
    ],

    // REST API configuration
    'api' => [
        'enabled'    => false, // Set to true to expose JSON endpoints
        'prefix'     => 'api/nusantara',
        'middleware' => ['api', 'throttle:60,1'],
    ],

    // Columns configurations. Toggle 'enabled' or change the DB 'name'
    'columns' => [
        'provinces' => [
            'id'          => ['name' => 'id', 'enabled' => true],
            'name'        => ['name' => 'name', 'enabled' => true], // e.g. rename to 'nama'
            'capital'     => ['name' => 'capital', 'enabled' => true],
            'latitude'    => ['name' => 'latitude', 'enabled' => true],
            'longitude'   => ['name' => 'longitude', 'enabled' => true],
            'elevation'   => ['name' => 'elevation', 'enabled' => true],
            'timezone'    => ['name' => 'timezone', 'enabled' => true],
            'area'        => ['name' => 'area', 'enabled' => true],
            'population'  => ['name' => 'population', 'enabled' => true],
            'boundary'    => ['name' => 'boundary', 'enabled' => false], // GIS Boundary Coordinates
        ],
        // ... (regencies, districts, villages keys follow the same structure)
    ],
];
```

> [!IMPORTANT]
> **Referential Integrity Constraints**
> Primary keys and foreign keys (`id`, `province_id`, `regency_id`, `district_id`) must remain enabled (`'enabled' => true`) in the configuration to preserve database relationships. The package will validate this and fail fast with an exception during migrations if misconfigured.

---

## 🏛️ Unified Facade (`Nusantara`)

The `Nusantara` facade is the recommended way to query administrative regions, as it automatically implements caching under the hood:

```php
use MadeByClowd\Nusantara\Facades\Nusantara;

// 1. Fetch Collection of all Provinces
$provinces = Nusantara::provinces();

// 2. Fetch specific Province by ID
$province = Nusantara::findProvince('11'); // returns Province model or null

// 3. Fetch Collection of Regencies of a Province
$regencies = Nusantara::regenciesOf('11'); // Province ID '11'

// 4. Fetch specific Regency by ID
$regency = Nusantara::findRegency('1101'); // returns Regency model or null

// 5. Fetch Collection of Districts of a Regency
$districts = Nusantara::districtsOf('1101'); // Regency ID '1101'

// 6. Fetch specific District by ID
$district = Nusantara::findDistrict('110101'); // returns District model or null

// 7. Fetch Collection of Villages of a District
$villages = Nusantara::villagesOf('110101'); // District ID '110101'

// 8. Fetch specific Village by ID
$village = Nusantara::findVillage('1101012001'); // returns Village model or null

// 9. Search regions dynamically across all administrative levels
// (Requires a query string length >= 2; matches using SQL LIKE %query%)
$results = Nusantara::search('Bakongan');
/*
Returns array:
[
    'provinces' => [...],
    'regencies' => [...],
    'districts' => [...],
    'villages'  => [...]
]
*/

// 10. Clear cached regional queries manually
Nusantara::clearCache();
```

---

## 📂 Eloquent Models & Relations

Laravel Nusantara provides dedicated Eloquent models (`Province`, `Regency`, `District`, `Village`) that handle table and foreign key resolution dynamically based on configuration.

### Model Relationships

All relations resolve keys and table names dynamically:

```php
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Models\District;

$province = Province::find('11');

// Province -> Regencies (HasMany)
$regencies = $province->regencies;

// Province -> Districts (HasManyThrough)
$districts = $province->districts;

// Province -> Villages (Custom optimized Join query builder)
$villages = $province->villages()->get();

// Regency -> Province (BelongsTo)
$province = $regencies->first()->province;

// Regency -> Districts (HasMany)
$districts = $regencies->first()->districts;

// Regency -> Villages (HasManyThrough)
$villages = $regencies->first()->villages;

// District -> Regency (BelongsTo)
$regency = $districts->first()->regency;

// District -> Villages (HasMany)
$villages = $districts->first()->villages;

// Village -> District (BelongsTo)
$district = $villages->first()->district;
```

### Transparent Schema Mapping (Magic Properties)

The models use the `HasDynamicNusantaraFields` trait. If you rename columns in your configuration (for example, renaming `'name'` to `'nama_provinsi'`), you can still access the properties using their standard logical names:

```php
// If in config/nusantara.php you configured: 'name' => ['name' => 'nama_provinsi']
$province = Province::find('11');

echo $province->name;          // Outputs: "Aceh" (Intercepted and mapped dynamically)
echo $province->nama_provinsi; // Outputs: "Aceh" (Direct DB attribute access also works)
```

---

## 🌐 Optional REST API Endpoints

If you are building dynamic frontend UI components (such as checkout dropdowns or select forms), you can expose ready-to-use JSON API endpoints by setting `'api.enabled' => true` in your configuration.

All request parameters are strictly validated:

### 1. Get Provinces
* **Endpoint**: `GET /api/nusantara/provinces`
* **Response**: JSON array of Province records.

### 2. Get Regencies of a Province
* **Endpoint**: `GET /api/nusantara/regencies?province_id={id}`
* **Validation**: `province_id` is required, string, exactly 2 characters.
* **Response**: JSON array of Regency records.

### 3. Get Districts of a Regency
* **Endpoint**: `GET /api/nusantara/districts?regency_id={id}`
* **Validation**: `regency_id` is required, string, exactly 4 characters.
* **Response**: JSON array of District records.

### 4. Get Villages of a District
* **Endpoint**: `GET /api/nusantara/villages?district_id={id}`
* **Validation**: `district_id` is required, string, exactly 6 characters.
* **Response**: JSON array of Village records.

### 5. Search Regions
* **Endpoint**: `GET /api/nusantara/search?q={query}`
* **Validation**: `q` is required, string, between 2 and 50 characters.
* **Response**: JSON object with matching results grouped by level:
  ```json
  {
    "provinces": [],
    "regencies": [],
    "districts": [],
    "villages": []
  }
  ```

---

## 🤖 AI Agent Integration (Laravel Boost)

This package features native integration with **Laravel Boost**. When you install or update the package in an application with Laravel Boost configured, the package automatically publishes an AI agent skill file (`SKILL.md`) to the host project at `.github/skills/laravel-nusantara/SKILL.md` or `.ai/skills/laravel-nusantara/SKILL.md`.

This ensures that any AI coding assistant used by developers in their host application understands:
1. Dynamic attribute mappings and configuration.
2. The dynamic database columns/table schema.
3. Best practices to avoid hardcoding table names in custom queries.

---

## 🧪 Testing

To run the package test suite locally:

```bash
composer install
vendor/bin/phpunit
```

---

## 🤝 Credits

* Special thanks to [cahyadsn](https://github.com/cahyadsn) for curating and providing the raw Indonesia administrative data used as the source for this package's dataset.

---

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
