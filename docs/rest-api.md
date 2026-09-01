# REST API

[← Back to docs index](README.md)

If you're building a frontend widget (a checkout dropdown, a search box), you can turn on ready-made JSON endpoints instead of writing your own controller.

Turn it on in `config/nusantara.php`:

```php
'api' => [
    'enabled' => true,
],
```

## Endpoints

### Get provinces

`GET /api/nusantara/provinces`

Returns a JSON array of provinces.

### Get regencies of a province

`GET /api/nusantara/regencies?province_id={id}`

`province_id` is required, a string, exactly 2 characters.

### Get districts of a regency

`GET /api/nusantara/districts?regency_id={id}`

`regency_id` is required, a string, exactly 4 characters.

### Get villages of a district

`GET /api/nusantara/villages?district_id={id}`

`district_id` is required, a string, exactly 6 characters.

### Search

`GET /api/nusantara/search?q={query}`

`q` is required, a string, between 2 and 50 characters. Returns results grouped by level:

```json
{
    "provinces": [],
    "regencies": [],
    "districts": [],
    "villages": []
}
```

## Protecting the endpoints

By default every route runs through `['api', 'throttle:60,1']`. Change it in the `api.middleware` config key, for example to require authentication:

```php
'api' => [
    'enabled' => true,
    'middleware' => ['api', 'auth:sanctum', 'throttle:60,1'],
],
```
