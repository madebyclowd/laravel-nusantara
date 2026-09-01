---
bump: minor
type: Added
---

`toGeoJson()` and `findByCoordinate()` now work against native spatial
`boundary` columns on SQL Server, alongside the existing MySQL and
PostgreSQL/PostGIS support — real WKB decode via the optional `brick/geo`
package and a real DB-side `STContains()` containment query, not a
row-by-row PHP ray-cast. SQL Server's `boundary` column isn't
spatial-indexed yet (its spatial index needs an explicit `BOUNDING_BOX`
parameter this package doesn't emit), so containment queries there are an
unindexed scan — correct and still far faster than pulling every row into
PHP, just not index-accelerated the way PostgreSQL's is. SpatiaLite is
still not supported — no environment exists to verify it against.
