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
