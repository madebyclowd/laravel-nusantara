---
bump: patch
type: Fixed
---

`toGeoJson()` threw a `TypeError` ("Return value must be of type string, resource
returned") for every native spatial `boundary` column on PostgreSQL/PostGIS —
PDO's `pgsql` driver returns `bytea` columns as a stream resource, not a
string, and `HasGeoBoundary::fetchBoundaryWkb()` wasn't converting it before
returning. Only surfaced once this was actually run against a live
PostgreSQL instance rather than skipped for lack of one. MySQL was
unaffected.
