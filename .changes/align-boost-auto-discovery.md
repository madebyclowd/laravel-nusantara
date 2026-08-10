---
bump: patch
type: Fixed
---

Replaced the hand-rolled Boost skill publishing (custom publish tag, `boost:install`/`boost:update` event listener, `boost.json` mutation) with Laravel Boost's native auto-discovery of `resources/boost/`, matching the pattern used by `laravel-auto-sequence`. Added the missing `resources/boost/guidelines/laravel-nusantara.blade.php` guideline and a `laravel/boost` composer suggest entry. `nusantara:install` no longer prompts to publish AI agent skills — Boost picks the package up automatically.
