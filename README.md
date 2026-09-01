# Laravel Nusantara

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/madebyclowd/laravel-nusantara/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/actions)
[![codecov](https://img.shields.io/codecov/c/github/madebyclowd/laravel-nusantara?style=flat-square)](https://codecov.io/gh/madebyclowd/laravel-nusantara)
[![Total Downloads](https://img.shields.io/packagist/dt/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-nusantara)
[![License](https://img.shields.io/packagist/l/madebyclowd/laravel-nusantara.svg?style=flat-square)](https://github.com/madebyclowd/laravel-nusantara/blob/main/LICENSE)

This package gives your Laravel app Indonesia's full administrative region data: provinces, regencies, districts, and villages. Real database tables, real Eloquent models, ready to query out of the box.

Data compiled according to **Kepmendagri No 300.2.2-2138 Year 2025**.

## Quick Start

**Step 1: Install the package**

```bash
composer require madebyclowd/laravel-nusantara
```

**Step 2: Run the setup wizard**

It asks a few yes/no questions. Press Enter each time to pick the normal answer. It publishes the config, runs the migrations, and seeds the region data.

```bash
php artisan nusantara:install
```

**Step 3: Query it**

```php
use MadeByClowd\Nusantara\Facades\Nusantara;

$province = Nusantara::findProvince('11');
$regencies = Nusantara::regenciesOf('11');
```

Done. All 38 provinces, every regency, district, and village are already in your database.

## Features

- **Works on any database.** SQLite, PostgreSQL, MySQL, SQL Server, or your own custom connection.
- **Rename anything.** Any table or column name can match your own schema.
- **Turn off what you don't need.** Skip columns like `elevation`, `timezone`, or `population` to save space.
- **Your own attribute names.** Call `$province->name` even if the real column is named `nama_provinsi`.
- **Caching built in.** Repeat queries don't hit the database twice.
- **Ready-made relationships.** Province to regency to district to village, all wired up.
- **Optional JSON API.** Drop-in endpoints for checkout or registration dropdowns.
- **Low-memory seeder.** Seeds the entire dataset using under 3MB of memory.
- **Map shapes on demand.** Download high-resolution boundary polygons only when you need them.
- **Reverse geocoding.** Find which region a coordinate falls in, or export any region as GeoJSON.
- **NIK & postal code tools.** Parse and validate Indonesian national ID numbers and postal codes.
- **AI-agent ready.** Ships a Laravel Boost guideline so AI coding assistants query this package correctly out of the box.

## Requirements

- **PHP**: `^8.2`
- **Laravel**: `^11.0`, `^12.0`, or `^13.0`

## Documentation

The [`docs/`](docs/README.md) folder covers every feature step by step:

- [Facade & Queries](docs/facade.md)
- [Models & Relations](docs/models-and-relations.md)
- [Configuration](docs/configuration.md)
- [Geographic Boundaries & Maps](docs/geographic-boundaries.md)
- [REST API](docs/rest-api.md)
- [AI Agent Integration](docs/ai-agent-integration.md)
- [Troubleshooting / FAQ](docs/troubleshooting.md)

## Testing

```bash
composer install
vendor/bin/pint --test     # code style
vendor/bin/phpstan analyse # static analysis
vendor/bin/phpunit         # test suite
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full contribution workflow.

## Credits

- Special thanks to [cahyadsn](https://github.com/cahyadsn) for curating and providing the raw Indonesia administrative data used as the source for this package's dataset.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
