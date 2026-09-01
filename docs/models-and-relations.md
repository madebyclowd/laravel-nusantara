# Models & Relations

[← Back to docs index](README.md)

The package ships four Eloquent models: `Province`, `Regency`, `District`, and `Village`. They handle table names, keys, and connections dynamically based on your `config/nusantara.php`, so you never hardcode any of it.

## Relationships

```php
use MadeByClowd\Nusantara\Models\Province;
use MadeByClowd\Nusantara\Models\Regency;
use MadeByClowd\Nusantara\Models\District;

$province = Province::find('11');

$regencies = $province->regencies;              // HasMany
$districts = $province->districts;               // HasManyThrough
$villages  = $province->villages()->get();        // custom optimized join query

$province = $regencies->first()->province;        // BelongsTo
$districts = $regencies->first()->districts;       // HasMany
$villages  = $regencies->first()->villages;         // HasManyThrough

$regency  = $districts->first()->regency;          // BelongsTo
$villages = $districts->first()->villages;          // HasMany

$district = $villages->first()->district;           // BelongsTo
```

## Dynamic attribute mapping

If you rename a column in your config (for example `name` becomes `nama_provinsi`), you can still read it under its normal logical name. Both work:

```php
// config/nusantara.php: 'name' => ['name' => 'nama_provinsi']

$province = Province::find('11');

echo $province->name;          // "Aceh" (mapped automatically)
echo $province->nama_provinsi; // "Aceh" (the real column, also works)
```

This is handled by the `HasDynamicNusantaraFields` trait, already applied to all four models.

## Extending a model

All four models extend `MadeByClowd\Nusantara\Models\AbstractRegionModel`, which sets up the table, keys, and connection, and exposes `resolveModel()`, `resolveColumn()`, and `resolveTable()` helpers for building your own config-aware relations.

To add your own relations or accessors, extend the concrete class and point your config at it:

```php
use MadeByClowd\Nusantara\Models\Province as BaseProvince;

class Province extends BaseProvince
{
    public function localBusinesses()
    {
        return $this->hasMany(\App\Models\Business::class);
    }
}
```

```php
// config/nusantara.php
'models' => [
    'province' => \App\Models\Province::class,
    // ...
],
```

Only extend `AbstractRegionModel` directly if you are replacing a model wholesale.
