---
bump: minor
type: Added
---

`toGeoJson()` now decodes real native spatial `boundary` columns
(`config('nusantara.boundaries.type', 'spatial')`) into `Polygon`/
`MultiPolygon` GeoJSON on MySQL and PostgreSQL/PostGIS, instead of throwing.
Decode uses the optional new `brick/geo` dependency (declared as `suggest`,
not a hard `require` — this package still has no runtime dependency on it
unless you're using spatial storage and install it yourself) and is cached
per row, invalidating automatically when the boundary value changes. SQL
Server and SpatiaLite spatial columns still throw `\RuntimeException` for
now — not yet verified against those drivers. `text`-mode boundary storage
is unaffected.
