---
bump: minor
type: Added
---

`Nusantara::findRegency()`, `findDistrict()`, and `findVillage()` now
automatically resolve legacy pre-split regional codes (e.g. Papua's
pre-2022 regency codes) to their current active equivalents when a direct
lookup misses, covering all six historical administrative splits since
2000 (Papua, Kalimantan Utara, Sulawesi Barat, Kepulauan Riau, Banten,
Gorontalo, Kepulauan Bangka Belitung).
