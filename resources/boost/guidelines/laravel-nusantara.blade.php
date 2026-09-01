## laravel-nusantara

Indonesian administrative region data (provinces, regencies, districts, villages) with full schema
freedom — every table and column name is remappable via `config/nusantara.php`, resolved dynamically
through `HasDynamicNusantaraFields` rather than hardcoded.

### Core conventions

- Always use logical attribute names (`name`, `capital`, `population`, `postal_code`, ...) on models —
  never hardcode a raw column name, since `config('nusantara.columns.*')` may remap it.
- Prefer the `Nusantara` facade over direct Eloquent queries for reads — it applies tag-safe caching
  that direct model queries bypass.
- Never call `->update([...])` on a region model directly — none declare `$fillable`/`$guarded`, so it
  throws `MassAssignmentException`. Use `->forceFill([...])->save()`.
- `findRegency()`/`findDistrict()`/`findVillage()` transparently resolve historical/legacy region-code
  prefixes (pre-2022 Papua splits, pre-2012 Kaltara, pre-2004 Sulbar, pre-2000 Banten/Gorontalo/Kepri/
  Babel) — no extra call needed.

### Operational commands

- `php artisan nusantara:install` — interactive wizard for config/migrations/migrate/seed.
- `php artisan nusantara:download-boundaries` — required before using `boundary` columns or reverse
  geocoding (`findByCoordinate()`).

### Spatial storage (`config('nusantara.boundaries.type', 'spatial')`)

Both `toGeoJson()` and `findByCoordinate()` work against real spatial geometry on **MySQL,
PostgreSQL/PostGIS, and SQL Server** — decode/containment happen server-side (`ST_AsBinary()`/
`.STAsBinary()` + `brick/geo` for decode, `ST_Contains()`/`.STContains()` for pushdown), not by
pulling every candidate row into PHP. Only PostgreSQL's `boundary` column carries a spatial index
today — MySQL can't (spatial indexes there require `NOT NULL`, and `boundary` is nullable by
design) and SQL Server's needs an explicit `BOUNDING_BOX` parameter this package doesn't emit yet —
both still do a real DB-side scan, just an unindexed one. **SpatiaLite is not covered yet** — both
methods throw `\RuntimeException` for a spatial column on that driver, not because spatial storage
itself is unsupported there, just because this package's decode/pushdown hasn't been verified
against it (this environment's PHP build also has no PDO_SQLite loadable-extension support at all).

If a boundary looks stale after `nusantara:download-boundaries --force`: `toGeoJson()`'s decoded
result is cached per row, keyed by a hash of the raw boundary value, so it invalidates automatically
when the column changes — check `HasNusantaraCaching`'s cache store/prefix config first, not just the
DB column, before assuming the downloader itself is broken.

### Pitfalls

- `search()` does not fall back to fuzzy matching automatically — call `searchFuzzy()` explicitly.
- `parseNik()`/`isValidNik()` validate NIK structure only; embedded region codes are resolved lazily
  and separately via `$info->district()`/`regency()`/`province()`.
- `findByCoordinate()` requires the `boundary` column enabled at every level down to (and including)
  the target `$level`, not just the target level — otherwise it throws `\RuntimeException`. On a
  spatial column it also throws `\RuntimeException` for SpatiaLite (see "Spatial storage" above) —
  MySQL/PostgreSQL/SQL Server all use real DB-side pushdown.
- `resolvePostalCode()`/`isValidPostalCode()` require `nusantara.columns.villages.postal_code.enabled`.
- `toGeoJson()` decodes native spatial `boundary` columns via the optional `brick/geo` package,
  verified on MySQL, PostgreSQL/PostGIS, and SQL Server — SpatiaLite and a missing `brick/geo` both
  throw `\RuntimeException`; corrupt WKB throws `MalformedWkbException` instead of returning wrong
  data. See "Spatial storage" above for the caching note.

See the `laravel-nusantara` Agent Skill (installed alongside this guideline) for the full facade/API
reference, config customization examples, and verification checklist.
