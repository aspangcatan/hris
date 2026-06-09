# Dynamic Access Rights — Design Spec
**Date:** 2026-06-09
**Status:** Approved

---

## Problem

The current access control system relies on a single `user_priv.level` value per user (e.g. `admin`, `hr`, `dh`, `standard`). Levels are coarse — there are real cases where an SH-level user needs a capability beyond what SH normally allows, or should be blocked from something SH normally can do. There is no way to express this without changing the user's level entirely.

The `permissions`, `user_permissions`, `access_level_permissions`, and `permission_audit_logs` tables already exist in the DB but are entirely unused. This feature activates them.

---

## Goal

Add a dynamic, per-user permission layer on top of the existing level system. Admin can grant extra permissions to any user, or revoke permissions their level would normally give them. All changes are audited.

---

## Data Layer

### Tables (existing, used as-is)

**`permissions`** — catalog of all available permission codes, seeded once.

| column | type | notes |
|--------|------|-------|
| id | bigIncrements | |
| module | string | e.g. `dtr`, `leave`, `payroll` |
| code | string (unique) | e.g. `dtr.edit` |
| description | string | human-readable label |

**`access_level_permissions`** — default grants per level. Seeded to match current implicit behavior so no user loses access during rollout.

| column | type |
|--------|------|
| id | bigIncrements |
| level | string |
| permission_id | unsignedBigInteger |

**`user_permissions`** — per-user overrides (additive grants and revocations).

| column | type | notes |
|--------|------|-------|
| id | bigIncrements | |
| user_id | unsignedBigInteger | |
| permission_id | unsignedBigInteger | |
| type | enum(`grant`, `revoke`) | |
| unique | (user_id, permission_id) | |

**`permission_audit_logs`** — written on every grant/revoke action.

| column | type | notes |
|--------|------|-------|
| id | bigIncrements | |
| performer_id | unsignedBigInteger | the admin who made the change |
| target_user_id | unsignedBigInteger | user whose permission changed |
| action | string | e.g. `granted dtr.edit`, `revoked leave.approve` |
| details | text | JSON snapshot |

### Effective Permission Resolution

```
level_defaults = access_level_permissions WHERE level = user.level
user_revokes   = user_permissions WHERE user_id = X AND type = 'revoke'
user_grants    = user_permissions WHERE user_id = X AND type = 'grant'

effective = (level_defaults UNION user_grants) MINUS user_revokes
```

A revoke always wins over a grant from the level. An explicit grant always adds to the level.

### Permission Catalog (seed data)

| module | code | description |
|--------|------|-------------|
| dtr | `dtr.view_own` | View own DTR |
| dtr | `dtr.view_others` | View other employees' DTR |
| dtr | `dtr.edit` | Edit/correct DTR entries |
| dtr | `dtr.print` | Print DTR |
| employee | `employee.view` | View employee profiles |
| employee | `employee.edit` | Edit employee PDS data |
| leave | `leave.file` | File leave applications |
| leave | `leave.approve` | Approve leave applications |
| leave | `leave.manage_credits` | Add/edit leave credits |
| leave | `leave.manage_types` | Manage leave types |
| payroll | `payroll.view` | View payroll records |
| payroll | `payroll.manage` | Create/edit payrolls |
| payroll | `payroll.settings` | Manage payroll settings |
| overtime | `overtime.file` | File overtime requests |
| overtime | `overtime.approve` | Approve overtime requests |
| passslip | `passslip.file` | File pass slips |
| passslip | `passslip.approve` | Approve pass slips |
| schedule | `schedule.view` | View schedules |
| schedule | `schedule.manage` | Manage schedules |
| saln | `saln.view_own` | View own SALN |
| saln | `saln.admin` | Administer all SALNs |
| hospital_order | `hospital_order.file` | File hospital orders |
| hospital_order | `hospital_order.approve` | Approve/manage hospital orders |
| reports | `reports.quarterly_leave` | Access quarterly leave balance reports |

### Level Defaults Seed

| level | permissions granted |
|-------|---------------------|
| `admin` | all 24 permissions |
| `hr` / `hrh` | all except `dtr.edit`, `schedule.manage`, `payroll.settings` |
| `dh` | `dtr.view_others`, `dtr.print`, `employee.view`, `leave.approve`, `overtime.approve`, `passslip.approve`, `hospital_order.approve`, `leave.file`, `overtime.file`, `passslip.file`, `saln.view_own`, `schedule.view`, `payroll.view` |
| `schedule` | `dtr.view_others`, `dtr.print`, `dtr.edit`, `schedule.view`, `schedule.manage` |
| `it` | `dtr.view_others`, `dtr.edit`, `dtr.print` |
| `standard` (SH and default) | `dtr.view_own`, `dtr.print`, `employee.view`, `leave.file`, `overtime.file`, `passslip.file`, `saln.view_own`, `schedule.view`, `hospital_order.file` |

> SH-specific capabilities (leave.approve, overtime.approve, passslip.approve, hospital_order.approve) are granted dynamically via `user_permissions` for users who are section heads, rather than encoding them into the level — preserving the flexibility goal.
>
> **Rollout note:** The `PermissionSeeder` will include a one-time step that queries `tdh_user.section` and `tdh_user.division` to find current section/division heads and auto-grants them the four approval permissions. This prevents access loss for existing SH/DH users during the transition.

---

## PermissionService

**File:** `app/Services/PermissionService.php`

### Methods

```php
can(int $userId, string $code): bool
```
Resolves effective permissions. Result is cached per request (keyed by `userId`) to avoid repeated DB hits on the same request.

```php
getEffectivePermissions(int $userId): Collection
```
Returns all effective permission codes for a user as a flat collection of strings.

```php
getForAdminUI(int $userId): Collection
```
Returns the full catalog with per-row state for rendering the admin toggle UI:
```php
[
  'code'        => 'dtr.edit',
  'module'      => 'dtr',
  'description' => 'Edit/correct DTR entries',
  'from_level'  => true,   // granted by the user's level default
  'granted'     => false,  // explicitly granted via user_permissions
  'revoked'     => false,  // explicitly revoked via user_permissions
  'effective'   => true,   // final resolved value
]
```

```php
setUserPermission(int $adminId, int $targetUserId, string $code, string $type): void
```
Inserts or updates a `user_permissions` row (`type` = `'grant'` or `'revoke'`). Writes to `permission_audit_logs`.

```php
removeUserPermission(int $adminId, int $targetUserId, string $code): void
```
Deletes the `user_permissions` row (restores level default). Writes to `permission_audit_logs`.

---

## CheckPermission Middleware

**File:** `app/Http/Middleware/CheckPermission.php`  
**Kernel alias:** `permission`

Usage on routes:
```php
Route::get('/employee', ...)->middleware('permission:employee.view');
Route::post('/employee/{id}', ...)->middleware('permission:employee.edit');
```

On failure:
- JSON requests → `403 { "error": "Unauthorized" }`
- Web requests → redirect to `/dashboard` with a session error flash

**Migration strategy:** Existing inline `in_array(session('access_rights'), [...])` checks are replaced module by module during implementation. The `access_level_permissions` seed mirrors current behavior, so no user loses access during the rollout. No existing protection is removed until the middleware is in place.

---

## Blade Directive

Registered in `AppServiceProvider::boot()`:

```blade
@permission('dtr.edit')
    <button>Edit DTR</button>
@endpermission
```

Resolves via `PermissionService::can(Auth::id(), $code)`.

---

## Admin UI

**Route:** `GET /admin/permissions` (gated: `access_rights = 'admin'`)  
**Controller:** `app/Http/Controllers/Admin/PermissionController.php`

### Layout

**Left panel — User list**
- Search box (filters by name)
- Each row: employee name + current level badge
- Clicking a row loads that user's permission panel on the right

**Right panel — Permission panel (loaded per selected user)**
- Permissions grouped by module (DTR, Leave, Payroll, etc.)
- Each permission row shows:
  - Description label
  - State badge: `Level Default` (grey) / `Granted` (green) / `Revoked` (red)
  - For permissions **in the level defaults**: three-way toggle — `Level Default` → `Revoked` → `Level Default`; or a separate `Grant` override is not applicable (level already grants it). A `Revoke` button removes it; clicking again restores it.
  - For permissions **not in the level defaults**: two-way toggle — `Not Granted` (grey) → `Granted` (green) → `Not Granted`.
  - Changes save immediately via AJAX (no save button)
- "Reset to level defaults" button — removes all `user_permissions` rows for this user
- Read-only audit log at the bottom — last 20 actions for the selected user (from `permission_audit_logs`): timestamp, action, performed by

### API Endpoints (AJAX)

| method | route | action |
|--------|-------|--------|
| GET | `/admin/permissions/users` | paginated employee list with level |
| GET | `/admin/permissions/users/{id}` | full permission state for one user |
| POST | `/admin/permissions/users/{id}` | set a permission (`code`, `type`) |
| DELETE | `/admin/permissions/users/{id}/reset` | reset to level defaults |

---

## Seeder

`database/seeders/PermissionSeeder.php` — seeds `permissions` and `access_level_permissions`.  
Added to `DatabaseSeeder` with `$this->call(PermissionSeeder::class)`.  
Run via `php artisan db:seed --class=PermissionSeeder` (idempotent — uses `updateOrInsert`).

---

## Files Touched

| file | change |
|------|--------|
| `app/Services/PermissionService.php` | new |
| `app/Http/Middleware/CheckPermission.php` | new |
| `app/Http/Kernel.php` | register `permission` alias |
| `app/Providers/AppServiceProvider.php` | register `@permission` Blade directive |
| `app/Http/Controllers/Admin/PermissionController.php` | new |
| `routes/web.php` | add admin permission routes + middleware on existing routes |
| `resources/views/admin/permissions.blade.php` | new admin UI page |
| `database/seeders/PermissionSeeder.php` | new |
| Existing controllers (DTR, Employee, Leave, etc.) | replace inline checks with middleware / `PermissionService::can()` |
