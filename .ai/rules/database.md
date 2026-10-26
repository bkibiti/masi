---
paths:
  - 'database/**'
---

# Database

## Database is not migration-managed
The `abg` database was not built from migrations. Do not create migrations or run `php artisan migrate`; `database/legacy-migrations` is reference only. Schema changes must be agreed with the user and applied manually.
