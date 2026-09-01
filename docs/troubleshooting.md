# Troubleshooting / FAQ

[← Back to docs index](README.md)

## `Nusantara::search('x')` returns empty results

Queries shorter than 2 characters always return empty, on purpose. This is not a bug, it stops a single keystroke from scanning the whole table. Wait for at least 2 characters before calling search.

## A `find*()` method returns `null` instead of a model

That ID does not exist, and it is not a recognized legacy code either. `findRegency()`, `findDistrict()`, and `findVillage()` all transparently resolve pre-split historical codes, so if you're passing a code from an old dataset and still getting `null`, double check the code itself against the current data.

## `toGeoJson()` or `findByCoordinate()` throws a `RuntimeException`

Two common causes:

1. **The `boundary` column isn't populated.** Run `php artisan nusantara:download-boundaries` after enabling the column, see [Geographic Boundaries & Maps](geographic-boundaries.md).
2. **You're on SQLite + SpatiaLite.** Reading boundaries back out (either method) isn't supported on that driver yet. Everything else in the package works fine on SQLite, this is the one gap.

## `RuntimeException: Security Exception: Hash verification failed`

The downloaded boundary file's checksum didn't match. This means the download was corrupted or tampered with. Delete any partial download and run `nusantara:download-boundaries` again. If it keeps happening, check your network path (proxies sometimes rewrite binary responses).

## Migration fails with an error about primary/foreign keys

`id`, `province_id`, `regency_id`, and `district_id` must stay `'enabled' => true` in `config/nusantara.php`. Turning any of them off breaks the relationships between the four tables, so the package refuses to migrate rather than leave you with a broken schema. See [Configuration](configuration.md).

## `isValidNik()` / `isValidPostalCode()` return `true` but the region lookup fails

Both are format checks only. `isValidNik()` checks length, digits, and the embedded date, not whether the embedded region codes point to a real region. Use `$nik->district()` (or `->regency()`, `->province()`) to actually resolve the region, they return `null` if nothing matches.

## Column renamed in config, but `$model->column_name` still shows the old value

Dynamic attribute mapping only affects the model's logical property name (e.g. `$province->name`). If you rename `name` to `nama_provinsi` in config, use either `$province->name` or `$province->nama_provinsi`, both work. See [Models & Relations](models-and-relations.md).

## Still stuck?

Open an issue on [GitHub](https://github.com/madebyclowd/laravel-nusantara/issues) with your `config/nusantara.php`, the database driver you're using, and the exact error message.
