# Changelog

All notable changes to `laravel-nusantara` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

New entries are generated from `.changes/*.md` changesets — see
[.changes/README.md](.changes/README.md) — not edited here by hand.

Releases prior to this file's introduction (up to v1.1.9) are documented in
the [GitHub Releases](https://github.com/madebyclowd/laravel-nusantara/releases) history.

## [1.2.2] - 2026-08-11

### Fixed
- Replaced the hand-rolled Boost skill publishing (custom publish tag, `boost:install`/`boost:update` event listener, `boost.json` mutation) with Laravel Boost's native auto-discovery of `resources/boost/`, matching the pattern used by `laravel-auto-sequence`. Added the missing `resources/boost/guidelines/laravel-nusantara.blade.php` guideline and a `laravel/boost` composer suggest entry. `nusantara:install` no longer prompts to publish AI agent skills — Boost picks the package up automatically.

## [1.2.1] - 2026-08-10

### Changed
- Updated the Laravel Boost skill to document scoped/fuzzy search, legacy region-code fallback, NIK parsing, postal code resolution, reverse geocoding, and GeoJSON export.

## [1.2.0] - 2026-08-09

### Added
- Added `Nusantara::findByCoordinate()` for reverse geocoding a lat/lng
  coordinate down to the containing village (or a higher level via the
  `$level` parameter), and a `toGeoJson()` method on all four region models
  exporting a GeoJSON Feature (Polygon/MultiPolygon boundary, or a Point
  fallback from latitude/longitude). Coordinate lookups currently require
  text-mode boundary storage (the package's default fallback) — native
  spatial-column storage is not yet supported by `findByCoordinate()`.
- `Nusantara::findRegency()`, `findDistrict()`, and `findVillage()` now
  automatically resolve legacy pre-split regional codes (e.g. Papua's
  pre-2022 regency codes) to their current active equivalents when a direct
  lookup misses, covering all six historical administrative splits since
  2000 (Papua, Kalimantan Utara, Sulawesi Barat, Kepulauan Riau, Banten,
  Gorontalo, Kepulauan Bangka Belitung).
- Added `Nusantara::parseNik()`/`isValidNik()` for parsing and structurally
  validating Indonesian NIK (national ID) numbers — extracting the embedded
  province/regency/district codes, gender, birth date, and sequence, with
  lazy `district()`/`regency()`/`province()` resolution (including
  transparent legacy pre-split region code handling) on the returned
  `NikInfo` object. A `ValidNik` Laravel validation rule is also available.
- Added `Nusantara::resolvePostalCode()`/`isValidPostalCode()` — resolves
  every village matching a postal code (eager-loaded with its district,
  regency, and province), validating the 5-digit, non-zero-leading format.
  A `ValidPostalCode` Laravel validation rule is also available.
- `Nusantara::search()` gains `$offset` and `$scope` parameters — `$scope`
  restricts the search to a single region level instead of always querying
  all four. Added `Nusantara::searchFuzzy()`, an explicit opt-in
  typo-tolerant fallback (Levenshtein distance over a bounded candidate set)
  for when `search()` finds nothing — it is never triggered automatically.

[1.2.0]: https://github.com/madebyclowd/laravel-nusantara/compare/v1.1.9...v1.2.0

[1.2.1]: https://github.com/madebyclowd/laravel-nusantara/compare/v1.2.0...v1.2.1

[1.2.2]: https://github.com/madebyclowd/laravel-nusantara/compare/v1.2.1...v1.2.2
