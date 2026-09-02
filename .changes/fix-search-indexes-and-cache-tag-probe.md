---
bump: patch
type: Fixed
---

Added database indexes on every `name` column and `villages.postal_code`
(new migration, applied automatically for `load_migrations => true` installs
and via `php artisan migrate` for published-migration installs) to remove
full table scans on `searchFuzzy()` and `Nusantara::resolvePostalCode()`
against the 83,000+ row `villages` table. Also stopped throwing and catching
a `BadMethodCallException` on every single cached query to detect whether
the configured cache store supports tags — that capability is now probed
once per driver and memoized.
