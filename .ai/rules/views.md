---
paths:
  - 'resources/views/**'
---

# Views

## Bootstrap admin layout with modal CRUD, not Tailwind
Pages `@extends("layouts.master")`, a Bootstrap 4 + jQuery + DataTables theme loaded from `public/assets` via `asset()`, not Vite or Tailwind. A CRUD screen is `index.blade.php` plus `create`/`edit`/`delete` modal partials, and includes `partials.notification` for flash alerts.

## Permission naming
Spatie permissions are named as Title Case phrases such as `'Manage Users'` or `'Edit Sales'`, checked with `@can('Manage Users')`. Reuse an existing permission name before adding a new one.
