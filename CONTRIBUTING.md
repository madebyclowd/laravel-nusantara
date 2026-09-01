# Contributing

Thanks for considering a contribution to Laravel Nusantara.

## Development setup

```bash
git clone https://github.com/madebyclowd/laravel-nusantara.git
cd laravel-nusantara
composer install
```

## Running checks locally

```bash
vendor/bin/pint --test        # code style
vendor/bin/phpstan analyse    # static analysis
vendor/bin/phpunit            # test suite
```

All three run in CI on every push and pull request; a PR won't be merged unless they pass.

### Spatial suite (MySQL / PostgreSQL+PostGIS / SQL Server)

Changes touching `src/Support/Spatial/**`, `Geocoder`, `HasGeoBoundary`, `DownloadBoundariesCommand`'s
spatial write path, or the spatial exception types also run against real MySQL, PostgreSQL/PostGIS,
and SQL Server in a separate, path-filtered CI job (`.github/workflows/run-tests.yml`, `spatial`
job) — not the main SQLite-only matrix. To run that subset locally instead of finding out from a
pushed PR:

```bash
docker compose -f compose.spatial.yaml up -d --wait
vendor/bin/phpunit --testsuite=Spatial
php scripts/export-wkb-fixtures.php   # regenerates tests/fixtures/wkb/** — `git diff` should be empty
docker compose -f compose.spatial.yaml down -v
```

Tests in this subset skip themselves gracefully (not fail) when the containers aren't running, so
the main suite is unaffected either way. SQL Server requires the `pdo_sqlsrv`/`sqlsrv` PHP
extensions and Microsoft's ODBC driver locally (already present in CI) — that leg skips like any
other if they're missing. SpatiaLite isn't covered yet: it's a loadable SQLite extension, not a
service container, and needs a PHP build with PDO_SQLite extension-loading support.

## Pull requests

- Target the `main` branch.
- Add or update tests for any behavior change.
- Run `vendor/bin/pint` (without `--test`) to auto-fix style before committing.
- Keep PRs focused — one logical change per PR.
- If your change is user-facing, add a changeset: copy `.changes/TEMPLATE.md` to a new file in `.changes/` and fill it in — see [.changes/README.md](.changes/README.md). Skip it for internal-only changes (tests, CI, docs). Don't edit `CHANGELOG.md` by hand — it's generated from these when a release is cut.

## Releasing

Releasing is automatic once changesets have landed on `main`: `.github/workflows/version.yml`
aggregates pending `.changes/*.md` files into a `chore(release): vX.Y.Z` pull request that
updates `CHANGELOG.md`. Merging that PR tags the release, which triggers
`.github/workflows/release.yml` (SBOM + GitHub release) — no manual `git tag` needed.

## Reporting bugs

Include Laravel/PHP versions and a minimal reproduction.
