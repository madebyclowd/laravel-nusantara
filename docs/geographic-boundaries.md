# Geographic Boundaries & Maps

[← Back to docs index](README.md)

Map shapes (polygons) for every region are not bundled inside the package. They are downloaded on demand, so your app only pays the storage cost for the levels you actually need.

## Downloading boundaries

1. Turn on the `boundary` column for the levels you want, in `config/nusantara.php`:

   ```php
   'columns' => [
       'provinces' => [
           // ...
           'boundary' => ['name' => 'boundary', 'enabled' => true],
       ],
   ]
   ```

2. Run the download command:

   ```bash
   php artisan nusantara:download-boundaries
   ```

This downloads the shape files, checks them against a SHA-256 checksum, and writes them into your database. If you need to run this offline, point `NUSANTARA_BOUNDARIES_LOCAL_PATH` at a local copy of the files instead of downloading them.

## Spatial vs text storage

`config('nusantara.boundaries.type')` controls how the shapes get stored:

- **`spatial`** (default): writes a real `geometry` column using your database's own spatial type. Lets you run fast, database-side lookups (see "Reverse geocoding" below). Needs one of the databases listed in the table below.
- **`text`**: stores the raw shape as a JSON array in a plain `LONGTEXT` column. Works on any database, no spatial extension needed, but lookups happen in PHP instead of the database.

## Minimum database version

| Database | Minimum version | Notes |
|---|---|---|
| MySQL | 5.6+ | |
| MariaDB | 5.5+ | |
| PostgreSQL + PostGIS | Any supported PostGIS version | Verified against PostgreSQL 17 + PostGIS 3.5 in CI. |
| SQL Server | 2008+ | Uses the native `geometry` type. Verified against SQL Server 2022. |
| SQLite + SpatiaLite | Not supported yet | See note below. |

SpatiaLite is not supported yet for reading boundaries back out (reverse geocoding or GeoJSON export). Writing still works on SpatiaLite under `type => 'text'`.

## Reverse geocoding

Find which region contains a coordinate:

```php
$village = Nusantara::findByCoordinate(lat: 2.931, lng: 97.484, level: 'village');
```

This needs the `boundary` column enabled and populated at every level up to `$level`. Under `spatial` storage, MySQL, PostgreSQL/PostGIS, and SQL Server run the containment check inside the database itself (fast, even at scale). PostgreSQL also uses a spatial index automatically; MySQL and SQL Server don't have one yet, both work correctly, just without the index speed-up.

SpatiaLite boundary columns throw a clear `RuntimeException` instead of silently returning the wrong answer.

## GeoJSON export

Turn any region into a standard GeoJSON Feature:

```php
$geojson = $village->toGeoJson();
```

Under `text` storage this always works. Under `spatial` storage, MySQL, PostgreSQL/PostGIS, and SQL Server decode the real shape into a `Polygon`/`MultiPolygon` Feature (cached per row so repeat calls are cheap). This needs the optional `brick/geo` package:

```bash
composer require brick/geo
```

SpatiaLite boundary columns throw the same `RuntimeException` as reverse geocoding, for the same reason.
