# AI Agent Integration (Laravel Boost)

[← Back to docs index](README.md)

This package ships two files that teach AI coding assistants how to use it correctly:

- `resources/boost/guidelines/laravel-nusantara.blade.php`, a written guideline
- `resources/boost/skills/laravel-nusantara/SKILL.md`, an agent skill

If [Laravel Boost](https://github.com/laravel/boost) is installed in your app, both are picked up automatically. Nothing to publish, nothing to configure.

## What this gives your AI assistant

1. How dynamic attribute mapping works, so it doesn't hardcode the wrong column name.
2. The actual database columns and table names your app is configured with.
3. Why it should never hardcode table names in custom queries, and what to call instead.
4. The full facade API: search, NIK parsing, postal codes, legacy region-code resolution, reverse geocoding, and GeoJSON export.

If you don't use Laravel Boost, you can still read either file directly, they're plain markdown/blade and written for a human just as much as for an AI.
