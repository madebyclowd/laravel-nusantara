# Laravel Nusantara (Indonesia Administrative Regions Database)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/madebyclowd/laravel-nusantara/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![License](https://img.shields.io/packagist/l/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/blob/main/LICENSE)

An enterprise-ready, developer-friendly, and highly customizable Indonesia regional administrative database (Provinces, Regencies, Districts, and Villages) for Laravel. Compiled according to **Kepmendagri No 300.2.2-2138 Year 2025**, complete with centroids, postal codes, and geographical boundary coordinates.

---

## 🏛 Project Seeding Architecture (Hybrid Model)

To meet enterprise standards for performance, security, and build speed, the dataset is split into two phases:

1. **Phase 1: Core Package (Metadata Only - ~2.7 MB)** *(Current)*
   * Lightweight, offline-ready dataset packaged directly inside this library.
   * Contains names, normalized codes (no dots), parent foreign keys, centroid points (`latitude`/`longitude`), and postal codes.
   * 100% firewall-safe and builds instantly in CI/CD pipelines without external network dependencies.
2. **Phase 2: Geographic Boundaries (Polygons/GIS Shapes - ~104 MB)** *(Upcoming)*
   * High-resolution boundary shapes hosted externally on a CDN.
   * Can be downloaded on-demand using an Artisan console command: `php artisan nusantara:download-boundaries`.

---

## ⚡ Key Features

* **Complete Developer Freedom**: Choose your database driver (PostgreSQL, SQLite, MySQL, SQL Server) and database connection.
* **Custom Table & Column Names**: Easily rename default tables and columns to match your company's schema guidelines.
* **Column Exclusion**: Disable columns you don't need (e.g. `elevation`, `timezone`, `area`, `population`) to save storage space.
* **Smart Auto-Mapping**: Access models using standard logical properties (e.g. `$province->name`) even if they are renamed in the database.
* **Unified Facade (`Nusantara::*`)**: A single entry point to fetch, query, and search regions.
* **Query Caching Layer**: Cache regional queries automatically to minimize DB load on production.
* **Has-Many-Through Relations**: Trailing relations from Province directly to Districts and Villages.
* **REST API Endpoints**: Ready-to-use JSON API endpoints for AJAX-driven dropdown widgets.
* **High Performance Streaming**: Gzip stream-based seeder uses line-by-line reading, keeping the memory footprint under 3MB.

---

## 🚀 Installation

Install the package via Composer:
```bash
composer require madebyclowd/laravel-nusantara
```

### Interactive Installer

To set up the package interactively (publish config, run migrations, and seed regional data), run:
```bash
php artisan nusantara:install
```

### Local Development & Testing

If you want to test or develop this package locally in another Laravel project before releasing it:

1. Open your target Laravel application's `composer.json` and add a local path repository pointing to the package directory:
   ```json
   "repositories": [
       {
           "type": "path",
           "url": "../laravel-nusantara"
       }
   ]
   ```
2. Require the package using the development branch constraint:
   ```bash
   composer require madebyclowd/laravel-nusantara:dev-main
   ```

---

## ⚙️ Configuration

The published configuration file is located at `config/nusantara.php`. Here you can customize tables, connections, columns, caching, APIs, and model mappings:

```php
return [
    // Automatically load migrations from the package
    'load_migrations' => true,

    // Database connection to use (null for default)
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

    // Dynamic model classes (allows you to extend or replace default models)
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

    // Optional REST API Endpoints
    'api' => [
        'enabled'    => false, // Set to true to expose endpoints
        'prefix'     => 'api/nusantara',
        'middleware' => ['api', 'throttle:60,1'],
    ],

    // Column configurations (toggle 'enabled' and customize DB 'name')
    'columns' => [
        'provinces' => [
            'id'          => ['name' => 'id', 'enabled' => true],
            'name'        => ['name' => 'name', 'enabled' => true],
            'capital'     => ['name' => 'capital', 'enabled' => true],
            'latitude'    => ['name' => 'latitude', 'enabled' => true],
            'longitude'   => ['name' => 'longitude', 'enabled' => true],
            ...
        ],
        ...
    ],
];
```

> [!IMPORTANT]
> **Referential Integrity Constraint**
> Primary keys and foreign keys (`id`, `province_id`, `regency_id`, `district_id`) must remain enabled in the configuration to preserve database relationships. The package will validate this and fail fast with an exception during migrations if misconfigured.

---

## 🏛️ Nusantara Facade

You can use the `Nusantara` Facade to query regions cleanly. It supports automatic query caching when enabled:

```php
use MadeByClowd\Nusantara\Facades\Nusantara;

// Get all provinces
$provinces = Nusantara::provinces();

// Get regencies in Aceh (Province ID '11')
$regencies = Nusantara::regenciesOf('11');

// Get districts in Aceh Selatan (Regency ID '1101')
$districts = Nusantara::districtsOf('1101');

// Get villages in Bakongan (District ID '110101')
$villages = Nusantara::villagesOf('110101');

// Fetch specific model by ID
$province = Nusantara::findProvince('11');
$regency = Nusantara::findRegency('1101');

// Perform dynamic search across all administrative levels
$results = Nusantara::search('Bakongan');
// Returns array: ['provinces' => [...], 'regencies' => [...], 'districts' => [...], 'villages' => [...]]
```

---

## 📂 Eloquent Models & Relations

Eloquent models resolve relations dynamically using the configuration values.

### Has-Many-Through Relations

You can traverse relations deeply:
```php
use MadeByClowd\Nusantara\Models\Province;

$province = Province::find('11'); // Aceh

// Get all districts directly (through Regencies)
$districts = $province->districts;

// Get all villages directly (custom optimized join)
$villages = $province->villages()->get();

// Get all villages of a Regency directly (through Districts)
$regency = $province->regencies()->first();
$regencyVillages = $regency->villages;
```

#### Magic Interception
When you access `$province->name`, the model translates this to the configured column name (e.g. `nama_provinsi` if renamed). This means your API integration remains clean even if your database uses custom naming conventions.

---

## 🌐 Optional REST API

Expose JSON API endpoints for AJAX-driven dropdowns (e.g. checkout forms) by setting `'api.enabled' => true` in your configuration.

Endpoints:
* `GET /api/nusantara/provinces` -> Returns all provinces.
* `GET /api/nusantara/regencies?province_id=11` -> Returns regencies of province.
* `GET /api/nusantara/districts?regency_id=1101` -> Returns districts of regency.
* `GET /api/nusantara/villages?district_id=110101` -> Returns villages of district.
* `GET /api/nusantara/search?q=Aceh` -> Returns matching search results.

---

## 🧪 Testing

To run the package test suite:

```bash
composer install
vendor/bin/phpunit
```

## Credits

* Raw Indonesia regional administrative data (before normalization) compiled by [cahyadsn](https://github.com/cahyadsn).

---

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
