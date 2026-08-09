---
bump: minor
type: Added
---

Added `Nusantara::parseNik()`/`isValidNik()` for parsing and structurally
validating Indonesian NIK (national ID) numbers — extracting the embedded
province/regency/district codes, gender, birth date, and sequence, with
lazy `district()`/`regency()`/`province()` resolution (including
transparent legacy pre-split region code handling) on the returned
`NikInfo` object. A `ValidNik` Laravel validation rule is also available.
