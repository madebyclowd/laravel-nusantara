# Laravel Nusantara AI Agent Skill

This skill provides guidelines and rules for AI agents (Cursor, Claude Code, GitHub Copilot, etc.) interacting with the `madebyclowd/laravel-nusantara` package in this codebase.

---

## 🏛️ Core Guidelines & Rules

### 1. Do NOT Assume Default Table/Column Names
The database tables and columns in this package are fully customizable.
* Always refer to the configuration file at `config/nusantara.php`.
* Do NOT write raw SQL queries targeting hardcoded names like `provinces`, `regencies`, or `villages`.
* If writing custom queries, resolve the table name dynamically from the model instance (e.g., `(new Province)->getTable()`).

### 2. Leverage Logical Mapping Properties
All Eloquent models (`Province`, `Regency`, `District`, `Village`) utilize the `HasDynamicNusantaraFields` trait.
* You can safely access standard logical attributes (e.g., `$province->name`, `$village->postal_code`, `$province->capital`).
* The trait automatically maps these logical names to the custom database column names defined in `config/nusantara.php` at runtime.

### 3. Prefer Facade for Fetching Data (`Nusantara::*`)
Prefer using the unified `Nusantara` Facade for fetching and searching region data, as it incorporates built-in query caching:
* Fetch all provinces: `Nusantara::provinces()`
* Fetch regencies of a province: `Nusantara::regenciesOf($provinceId)`
* Fetch districts of a regency: `Nusantara::districtsOf($regencyId)`
* Fetch villages of a district: `Nusantara::villagesOf($districtId)`
* Search regions across all levels: `Nusantara::search($query)`

### 4. Relations and Deep Traversal
Use standard Laravel Eloquent relationships for traversing parent/child levels:
* Province to Regencies: `$province->regencies`
* Province to Districts: `$province->districts` *(Has-Many-Through)*
* Province to Villages: `$province->villages()->get()` *(Custom Join)*
* Regency to Districts: `$regency->districts`
* Regency to Villages: `$regency->villages` *(Has-Many-Through)*
* District to Villages: `$district->villages`
