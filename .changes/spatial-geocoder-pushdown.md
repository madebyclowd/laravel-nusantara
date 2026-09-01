---
bump: minor
type: Added
---

`findByCoordinate()` now runs a real DB-side `ST_Contains()` containment
query against native spatial `boundary` columns on MySQL and
PostgreSQL/PostGIS, using the spatial index instead of pulling every
candidate row into PHP and ray-casting. This was the gap left open when
spatial-column support was originally added (v1.2.0 required `text`-mode
storage for coordinate lookups) — that limitation is now resolved for these
two drivers. SQL Server and SpatiaLite spatial columns still throw
`\RuntimeException` for now — not yet verified against those drivers.
`text`-mode boundary storage is unaffected.
