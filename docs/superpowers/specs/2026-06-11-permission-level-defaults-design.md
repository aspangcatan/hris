# Permission Level Defaults — Admin Page Design

## Problem

`hris.access_level_permissions` maps `user_priv.level` (e.g. `hr`, `dh`, `schedule`, `standard`) to the set of permission codes granted to everyone with that level by default. This table is currently only editable via `database/seeders/PermissionSeeder.php` — there is no UI for an admin to adjust level-wide defaults without editing seeder code and re-running it.

This adds an admin page to view and edit `hris.access_level_permissions` directly, as a companion to the existing per-user Access Rights page (`/admin/permissions`).

## Scope

- New route, controller, view, and `PermissionService` methods to manage level-wide permission defaults.
- Out of scope: per-user overrides (already handled by `/admin/permissions`), audit logging of level changes, managing levels that have no corresponding `tdh_user.user_priv` (`syscode='hris'`) rows (`hrh`, `it`).

## Architecture

- New controller: `App\Http\Controllers\Admin\PermissionLevelController`
  - Same admin-only middleware pattern as `Admin\PermissionController`: `strtolower(session('access_rights')) !== 'admin'` → 403.
- New routes (added to the existing `# ADMIN — Access Rights Management` block in `routes/web.php`):
  - `GET /admin/permission-levels` → `index()` — returns view `admin/permission_levels.blade.php`
  - `GET /admin/permission-levels/data` → `data()` — returns JSON matrix (see Data Shape)
  - `POST /admin/permission-levels/update` → `update(Request $request)` — toggles one (level, permission) cell
- New sidebar link "Permission Levels" added next to "Access Rights" in `resources/views/layouts/sidebar.blade.php`, inside the existing `@if(strtolower((string) session('access_rights')) === 'admin')` block.
- New `PermissionService` methods:
  - `getLevelPermissionMatrix(): array` — returns permissions grouped by module, each with a `levels => [level => bool]` map
  - `setLevelPermission(string $level, string $code, bool $granted): void` — inserts or deletes the corresponding row in `hris.access_level_permissions`

## Levels

The level columns shown are the distinct lowercase values of `tdh_user.user_priv.level` where `syscode = 'hris'`. As of this writing that is:

```
admin, dh, hr, schedule, sh, standard
```

- `admin` is shown as a locked/read-only column: all checkboxes checked and disabled in the UI, and the backend rejects (422) any `update()` request where `level === 'admin'`.
- `hrh` and `it` currently have rows in `hris.access_level_permissions` (from the seeder) but no matching `user_priv` rows, so they will not appear as columns on this page. Their existing rows are left untouched — this is a known limitation; if a user is ever assigned one of these levels, this page will need to be revisited to include it.
- The list of valid (non-admin) levels for `update()` validation is: `dh, hr, schedule, sh, standard`.

## Data Shape

`GET /admin/permission-levels/data` response:

```json
{
  "levels": ["admin", "dh", "hr", "schedule", "sh", "standard"],
  "modules": {
    "dtr": [
      {
        "id": 1,
        "code": "dtr.view_own",
        "description": "View own DTR",
        "levels": { "admin": true, "dh": true, "hr": true, "schedule": true, "sh": false, "standard": true }
      }
    ]
  }
}
```

- `modules` is keyed by `hris.permissions.module`, each value an array of permission rows ordered by `code` (matching the existing `getForAdminUI` ordering convention).
- `levels` map is built by checking, for each permission, which of the level columns have a matching `(level, permission_id)` row in `hris.access_level_permissions`. `admin` is always `true` for every permission (hardcoded, not read from the table — admin implicitly has everything regardless of what's seeded).

`POST /admin/permission-levels/update` request body:

```json
{ "level": "hr", "code": "leave.manage_credits", "granted": false }
```

Validation:
- `level` required, must be one of `dh, hr, schedule, sh, standard` (admin excluded — return 422 "Cannot modify admin level" if `level === 'admin'`)
- `code` required, must exist in `hris.permissions`
- `granted` required boolean

On success, returns `{ "message": "Updated." }`.

## PermissionService Methods

```php
public function getLevelPermissionMatrix(): array
{
    $levels = DB::table('tdh_user.user_priv')
        ->where('syscode', 'hris')
        ->select(DB::raw('LOWER(level) as level'))
        ->distinct()
        ->pluck('level')
        ->all();

    sort($levels);

    $grants = DB::table('hris.access_level_permissions')
        ->get(['level', 'permission_id'])
        ->map(fn($row) => strtolower($row->level) . ':' . $row->permission_id)
        ->flip();

    $modules = DB::table('hris.permissions')
        ->orderBy('module')
        ->orderBy('code')
        ->get()
        ->map(function ($p) use ($levels, $grants) {
            $levelMap = [];
            foreach ($levels as $level) {
                $levelMap[$level] = $level === 'admin'
                    ? true
                    : $grants->has("{$level}:{$p->id}");
            }
            return [
                'id' => $p->id,
                'module' => $p->module,
                'code' => $p->code,
                'description' => $p->description,
                'levels' => $levelMap,
            ];
        })
        ->groupBy('module')
        ->toArray();

    return [
        'levels' => $levels,
        'modules' => $modules,
    ];
}

public function setLevelPermission(string $level, string $code, bool $granted): void
{
    $permId = DB::table('hris.permissions')->where('code', $code)->value('id');
    if (!$permId) {
        throw new \InvalidArgumentException("Unknown permission code: {$code}");
    }

    if ($granted) {
        DB::table('hris.access_level_permissions')->updateOrInsert(
            ['level' => $level, 'permission_id' => $permId],
            ['level' => $level, 'permission_id' => $permId, 'created_at' => now(), 'updated_at' => now()]
        );
    } else {
        DB::table('hris.access_level_permissions')
            ->where('level', $level)
            ->where('permission_id', $permId)
            ->delete();
    }
}
```

The controller's `data()` action returns `getLevelPermissionMatrix()` directly as JSON — its `['levels' => ..., 'modules' => ...]` shape matches the response described above.

## UI

`resources/views/admin/permission_levels.blade.php`:

- Same page chrome as `admin/permissions.blade.php`: `@extends('master')`, breadcrumb "Home → Access Rights → Permission Levels", admin-only (enforced by controller middleware; page is also only linked from sidebar when `access_rights === 'admin'`).
- Single table, `table-responsive` wrapper for horizontal scroll:
  - Header row: "Permission" column + one column per level (`Admin`, `DH`, `HR`, `Schedule`, `SH`, `Standard`).
  - Body rows grouped by module, with a module header row (reusing the same `MODULE_INFO` label/icon map as `admin/permissions.blade.php`) followed by one row per permission showing its description.
  - Each level cell (except `Admin`) contains a checkbox/switch reflecting `levels[level]`. The `Admin` column shows a checked, disabled checkbox for every row.
- Toggling a checkbox immediately `POST`s to `/admin/permission-levels/update` with `{ level, code, granted }`.
  - On success: leave checkbox in its new state.
  - On failure (non-2xx): revert the checkbox to its previous state and show a brief error (e.g. `alert()` or a toast, matching whatever's lightest-weight — no existing toast component in this codebase, so `alert()` is acceptable here).
- No page-wide "Save" button — every toggle is persisted immediately, matching the per-user Access Rights page's UX.

## Testing

- `tests/Feature/PermissionLevelControllerTest.php`:
  - Non-admin session gets 403 on all three routes.
  - `GET /admin/permission-levels/data` returns expected shape (`levels`, `modules` keys; `admin` level is `true` for a sample permission regardless of `access_level_permissions` contents).
  - `POST /admin/permission-levels/update` with `level=hr, granted=true` for a permission not currently granted to `hr` creates the row in `hris.access_level_permissions`; with `granted=false` for a currently-granted permission deletes it.
  - `POST /admin/permission-levels/update` with `level=admin` returns 422.
  - `POST /admin/permission-levels/update` with unknown `code` or invalid `level` returns 422.
  - Test-DB setup: as noted in project memory, `hris.permissions` / `hris.access_level_permissions` rows must be inserted in `setUp()` and cleaned up in `tearDown()` since `PermissionSeeder` doesn't run on the test DB. Also insert a `tdh_user.user_priv` row with `syscode='hris', level='hr'` (and similar for other test levels) so `getLevelPermissionMatrix()`'s level-discovery query finds them — clean up in `tearDown()`.
- `tests/Unit/PermissionServiceLevelMatrixTest.php` (or add to an existing service test file):
  - `getLevelPermissionMatrix()` correctly reflects `access_level_permissions` rows and always returns `true` for `admin`.
  - `setLevelPermission()` grant/revoke round-trip (insert then delete the row, verify via direct `DB::table` query).

## Out of Scope / Future Work

- Audit logging for level-default changes (per user decision, skipped for now).
- Managing `hrh` / `it` levels (no current `user_priv` rows reference them).
- Bulk/save-all UX — each toggle is an independent immediate save.
