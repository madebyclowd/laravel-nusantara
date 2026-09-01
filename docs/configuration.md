# Configuration

[← Back to docs index](README.md)

You don't need to publish `config/nusantara.php` at all. Every setting below already has a working default. Only publish it if you actually want to change one:

```bash
php artisan vendor:publish --tag=nusantara-config
```

```php
return [
    // Automatically load migrations from the package.
    // Set to false if you want to publish and edit them yourself.
    'load_migrations' => true,

    // Database connection to use. Null means "use the app's default connection".
    'connection' => null,

    // Rename any table to match your own naming conventions.
    'tables' => [
        'provinces' => 'provinces',
        'regencies' => 'regencies',
        'districts' => 'districts',
        'villages'  => 'villages',
    ],

    // Turn foreign key constraints on or off between the four tables.
    'enable_foreign_keys' => true,

    // Point these at your own model classes if you need to extend one.
    // See "Extending a model" in models-and-relations.md.
    'models' => [
        'province' => \MadeByClowd\Nusantara\Models\Province::class,
        'regency'  => \MadeByClowd\Nusantara\Models\Regency::class,
        'district' => \MadeByClowd\Nusantara\Models\District::class,
        'village'  => \MadeByClowd\Nusantara\Models\Village::class,
    ],

    // Cache lookups made through the Nusantara facade.
    'cache' => [
        'enabled' => true,
        'ttl'     => 86400, // seconds
        'prefix'  => 'nusantara',
    ],

    // Optional JSON API for dropdowns/search boxes. See rest-api.md.
    'api' => [
        'enabled'    => false,
        'prefix'     => 'api/nusantara',
        'middleware' => ['api', 'throttle:60,1'],
    ],

    // On-demand map boundary downloads. See geographic-boundaries.md.
    'boundaries' => [
        'cdn_url' => 'https://data.clowdlab.com',
        'local_path' => env('NUSANTARA_BOUNDARIES_LOCAL_PATH', null),
        'version' => null, // pin a specific dataset version, or null for the latest
        'type' => 'spatial', // 'spatial' (native geometry column) or 'text' (raw JSON, no extension needed)
        'spatial_index' => true,
        'verify_checksum' => true,
        'levels' => [
            'provinces' => true,
            'regencies' => true,
            'districts' => false,
            'villages'  => false,
        ],
    ],

    // Turn columns on or off per level, and rename any of them.
    'columns' => [
        'provinces' => [
            'id'         => ['name' => 'id', 'enabled' => true],
            'name'       => ['name' => 'name', 'enabled' => true], // e.g. rename to 'nama'
            'capital'    => ['name' => 'capital', 'enabled' => true],
            'latitude'   => ['name' => 'latitude', 'enabled' => true],
            'longitude'  => ['name' => 'longitude', 'enabled' => true],
            'elevation'  => ['name' => 'elevation', 'enabled' => true],
            'timezone'   => ['name' => 'timezone', 'enabled' => true],
            'area'       => ['name' => 'area', 'enabled' => true],
            'population' => ['name' => 'population', 'enabled' => true],
            'boundary'   => ['name' => 'boundary', 'enabled' => false], // map shape, off by default
        ],
        // 'regencies', 'districts', and 'villages' follow the same shape
    ],
];
```

> [!IMPORTANT]
> Primary keys and foreign keys (`id`, `province_id`, `regency_id`, `district_id`) must stay `'enabled' => true`. Turning any of them off breaks the relationships between tables, and the package fails fast with an exception during migration if you try.
