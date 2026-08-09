---
bump: minor
type: Added
---

`Nusantara::search()` gains `$offset` and `$scope` parameters — `$scope`
restricts the search to a single region level instead of always querying
all four. Added `Nusantara::searchFuzzy()`, an explicit opt-in
typo-tolerant fallback (Levenshtein distance over a bounded candidate set)
for when `search()` finds nothing — it is never triggered automatically.
