---
bump: minor
type: Added
---

Added `Nusantara::resolvePostalCode()`/`isValidPostalCode()` — resolves
every village matching a postal code (eager-loaded with its district,
regency, and province), validating the 5-digit, non-zero-leading format.
A `ValidPostalCode` Laravel validation rule is also available.
