---
bump: minor
type: Added
---

Added `Nusantara::findByCoordinate()` for reverse geocoding a lat/lng
coordinate down to the containing village (or a higher level via the
`$level` parameter), and a `toGeoJson()` method on all four region models
exporting a GeoJSON Feature (Polygon/MultiPolygon boundary, or a Point
fallback from latitude/longitude). Coordinate lookups currently require
text-mode boundary storage (the package's default fallback) — native
spatial-column storage is not yet supported by `findByCoordinate()`.
