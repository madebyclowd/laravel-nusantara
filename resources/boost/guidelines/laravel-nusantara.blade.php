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

### Pitfalls

- `search()` does not fall back to fuzzy matching automatically — call `searchFuzzy()` explicitly.
- `parseNik()`/`isValidNik()` validate NIK structure only; embedded region codes are resolved lazily
  and separately via `$info->district()`/`regency()`/`province()`.
- `findByCoordinate()` requires the `boundary` column enabled at every level down to (and including)
  the target `$level`, not just the target level — otherwise it throws `\RuntimeException`.
- `resolvePostalCode()`/`isValidPostalCode()` require `nusantara.columns.villages.postal_code.enabled`.

See the `laravel-nusantara` Agent Skill (installed alongside this guideline) for the full facade/API
reference, config customization examples, and verification checklist.
