<?php

use MadeByClowd\Nusantara\Models\District;
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Models\Village;

return [
    /*
    |--------------------------------------------------------------------------
    | Load Package Migrations
    |--------------------------------------------------------------------------
    |
    | If set to true, the package will automatically load and run its migrations.
    | Set to false if you wish to publish and customize the migration file manually.
    |
    */
    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Define the database connection to be used by the package.
    | Set to null to use the default application connection.
    |
    */
    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | You can customize the table names used by the package to avoid conflicts
    | or conform to your database naming conventions.
    |
    */
    'tables' => [
        'provinces' => 'provinces',
        'regencies' => 'regencies',
        'districts' => 'districts',
        'villages' => 'villages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Foreign Keys Constraint
    |--------------------------------------------------------------------------
    |
    | Enable or disable foreign key constraints in the package tables.
    |
    */
    'enable_foreign_keys' => true,

    /*
    |--------------------------------------------------------------------------
    | Model Mappings
    |--------------------------------------------------------------------------
    |
    | Map the Eloquent models used for relations. This allows you to extend
    | or replace the default models with your own.
    |
    */
    'models' => [
        'province' => Province::class,
        'regency' => Regency::class,
        'district' => District::class,
        'village' => Village::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Columns Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the column names and toggle their inclusion.
    | - 'name': The actual column name in the database.
    | - 'enabled': Set to false to exclude the column from migration and seeding.
    |
    | NOTE: Primary keys and Foreign keys (id, province_id, regency_id, district_id)
    | must remain enabled to preserve table relationships.
    |
    */
    'columns' => [
        'provinces' => [
            'id' => ['name' => 'id', 'enabled' => true],
            'name' => ['name' => 'name', 'enabled' => true],
            'capital' => ['name' => 'capital', 'enabled' => true],
            'latitude' => ['name' => 'latitude', 'enabled' => true],
            'longitude' => ['name' => 'longitude', 'enabled' => true],
            'elevation' => ['name' => 'elevation', 'enabled' => true],
            'timezone' => ['name' => 'timezone', 'enabled' => true],
            'area' => ['name' => 'area', 'enabled' => true],
            'population' => ['name' => 'population', 'enabled' => true],
            'boundary' => ['name' => 'boundary', 'enabled' => false],
        ],

        'regencies' => [
            'id' => ['name' => 'id', 'enabled' => true],
            'province_id' => ['name' => 'province_id', 'enabled' => true],
            'name' => ['name' => 'name', 'enabled' => true],
            'capital' => ['name' => 'capital', 'enabled' => true],
            'latitude' => ['name' => 'latitude', 'enabled' => true],
            'longitude' => ['name' => 'longitude', 'enabled' => true],
            'elevation' => ['name' => 'elevation', 'enabled' => true],
            'timezone' => ['name' => 'timezone', 'enabled' => true],
            'area' => ['name' => 'area', 'enabled' => true],
            'population' => ['name' => 'population', 'enabled' => true],
            'boundary' => ['name' => 'boundary', 'enabled' => false],
        ],

        'districts' => [
            'id' => ['name' => 'id', 'enabled' => true],
            'regency_id' => ['name' => 'regency_id', 'enabled' => true],
            'name' => ['name' => 'name', 'enabled' => true],
            'latitude' => ['name' => 'latitude', 'enabled' => true],
            'longitude' => ['name' => 'longitude', 'enabled' => true],
            'boundary' => ['name' => 'boundary', 'enabled' => false],
        ],

        'villages' => [
            'id' => ['name' => 'id', 'enabled' => true],
            'district_id' => ['name' => 'district_id', 'enabled' => true],
            'name' => ['name' => 'name', 'enabled' => true],
            'postal_code' => ['name' => 'postal_code', 'enabled' => true],
            'latitude' => ['name' => 'latitude', 'enabled' => true],
            'longitude' => ['name' => 'longitude', 'enabled' => true],
            'boundary' => ['name' => 'boundary', 'enabled' => false],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Cache regional data to minimize database overhead on recurring queries.
    | - 'enabled': Toggle caching on or off.
    | - 'ttl': Time-to-live in seconds (e.g. 86400 for 24 hours).
    | - 'prefix': Cache key prefix.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
        'prefix' => 'nusantara',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional REST API Endpoints
    |--------------------------------------------------------------------------
    |
    | Expose pre-configured JSON API endpoints for checkout/dropdown widgets.
    | - 'enabled': Toggle REST API routes on or off.
    | - 'prefix': Route prefix for endpoints (e.g. api/nusantara).
    | - 'middleware': Middleware stack to protect these endpoints.
    |
    */
    'api' => [
        'enabled' => false,
        'prefix' => 'api/nusantara',
        'middleware' => ['api', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase 2 GIS Boundaries Configuration
    |--------------------------------------------------------------------------
    |
    | High-resolution boundaries (polygons) are downloaded on-demand.
    | - 'cdn_url': Base URL to pull gzipped datasets from.
    | - 'local_path': Local path directory to read files from (offline bypass).
    | - 'type': Storage type. Options: 'spatial' (native geometry) or 'text' (raw JSON coordinate arrays).
    | - 'spatial_index': Enable spatial index on columns (if 'type' is 'spatial').
    | - 'levels': Toggle seeding boundaries for each administrative level.
    |
    */
    'boundaries' => [
        'cdn_url' => 'https://github.com/madebyclowd/laravel-nusantara/releases/download',
        'local_path' => env('NUSANTARA_BOUNDARIES_LOCAL_PATH', null),
        'type' => 'spatial', // 'spatial' or 'text'
        'spatial_index' => true,
        'verify_checksum' => true,
        'levels' => [
            'provinces' => true,
            'regencies' => true,
            'districts' => false,
            'villages' => false,
        ],
    ],
];
