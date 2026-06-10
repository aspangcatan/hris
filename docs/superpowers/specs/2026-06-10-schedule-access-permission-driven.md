# Schedule Access Driven by `schedule.manage` Permission — Design Spec
**Date:** 2026-06-10
**Status:** Approved

---

## Problem

`ScheduleController` currently treats `session('access_rights') === 'schedule'` (i.e. `tdh_user.user_priv.level = 'schedule'`) as the "schedule manager" role:

- `/schedule_access` (`getScheduleManagerAssignments`) lists only users with `user_priv.level = 'schedule'` for section assignment.
- `saveScheduleManagerSections` validates the target user the same way.
- `getManagedSectionIdsForCurrentUser()`, `canManageEmployee()`, `getSchedules()`, `printSchedule()`, and `getManagedSections()` all branch on `$accessRights === 'schedule'` to apply section filtering.
- If a `schedule`-level user has no rows in `hris.schedule_user_sections`, the code falls back to their own `tdh_user.users.section`, so they always see at least their own section's employees.

This is inconsistent with the permission-driven model ([[2026-06-10-permission-priority-design]], [[2026-06-10-dynamic-sidebar-design]]): the `schedule.manage` permission code already exists and is granted to several levels (`admin`, `hr`, `hrh`, `dh`, `schedule`) and can be granted per-user, but none of this is consulted.

## Goal

1. `/schedule_access` lists users based on the **effective `schedule.manage` permission** (level default + per-user overrides), not `user_priv.level = 'schedule'`.
2. A `schedule.manage` holder with **no assigned sections** in `hris.schedule_user_sections` sees **zero employees** on Manage Schedule — assigning at least one section becomes a prerequisite for managing schedules. The current "fall back to own section" behavior is removed.

---

## Changes

### 1. New `PermissionService::getUserIdsWithPermission(string $code, bool $excludeAdminLevel = true): array`

Computes the set of user IDs that effectively hold a permission code, in bulk (no per-user `can()` loop):

1. Look up `permission_id` for `$code`. Return `[]` if not found.
2. Find levels granted this `permission_id` via `hris.access_level_permissions` → `$grantedLevels` (lowercased).
3. Load all `tdh_user.user_priv` rows where `syscode = 'hris'` → `(user_id, level)`.
4. Load `hris.user_permissions` rows for this `permission_id`, split into `$grantedUserIds` (type='grant') and `$revokedUserIds` (type='revoke').
5. A user is included if (`strtolower(level)` is in `$grantedLevels` OR `user_id` in `$grantedUserIds`) AND `user_id` not in `$revokedUserIds`.
6. If `$excludeAdminLevel` is true (default), drop users whose `level === 'admin'` — admins bypass section filtering entirely (see Change 4), so listing them for section assignment would be a no-op.
7. Return unique user IDs as `int[]`.

### 2. `getScheduleManagerAssignments()` (powers `/schedule_access`)

Replace the `tdh_user.user_priv` query (`where('up.level', 'schedule')`) with:

```php
$managerIds = app(PermissionService::class)->getUserIdsWithPermission('schedule.manage');

$managers = DB::table('tdh_user.users as u')
    ->leftJoin('tdh_user.designation as de', 'u.designation', '=', 'de.id')
    ->whereIn('u.id', $managerIds)
    ->selectRaw("... same name/designation projection ...")
    ->orderBy('u.lname')->orderBy('u.fname')
    ->get();
```

Response shape (`managers[]` with `id`, `name`, `designation`, `assigned_section_ids`, plus `sections[]`) is unchanged — no frontend changes needed.

### 3. `saveScheduleManagerSections()`

Replace the `$isScheduleManager` check (currently `user_priv.level = 'schedule'`) with:

```php
$isScheduleManager = in_array(
    $userId,
    app(PermissionService::class)->getUserIdsWithPermission('schedule.manage'),
    true
);
```

Same 422 "Selected user is not a schedule manager." response if false.

### 4. Remove the "own section" fallback

`getManagedSectionIdsForCurrentUser()` drops the `$includeOwnSectionFallback` parameter and the fallback block entirely:

```php
private function getManagedSectionIdsForCurrentUser(): array
{
    return DB::table('hris.schedule_user_sections')
        ->where('user_id', Auth::id())
        ->pluck('section_id')
        ->map(fn($id) => (int) $id)
        ->unique()
        ->values()
        ->toArray();
}
```

All 4 existing call sites use the default already, so no call-site signature changes are needed beyond this.

### 5. Replace `$accessRights === 'schedule'` with `permission_check('schedule.manage')`

In each of the following, the level check `=== 'schedule'` becomes `permission_check('schedule.manage')`, evaluated **after** the existing `admin` branch (which is unchanged and still bypasses section filtering):

- `canManageEmployee()`: `admin` → true; `elseif permission_check('schedule.manage')` → managed-sections check (empty → false); `else` → false.
- `getSchedules()`: `elseif (permission_check('schedule.manage'))` → existing managed-sections filtering logic (empty `$managedSections` → return empty schedule list).
- `printSchedule()`: same `elseif` swap, including the `$managedSections`-based division/section label branch (line ~453).
- `getManagedSections()`: same `elseif` swap for the sections list endpoint.

The final `else` branch (employees who hold neither `admin` nor `schedule.manage`, seeing only their own section) is unchanged.

---

## Known Behavior Changes

1. **Broader/narrower manager set on `/schedule_access`**: per `PermissionSeeder`, only the `admin` and `schedule` levels hold `schedule.manage` by default, and `admin` is excluded — so by default the listed set is unchanged (`schedule`-level users). The real difference is per-user overrides: a user of any level individually **granted** `schedule.manage` via the Admin Permission UI now appears in the list, and a `schedule`-level user who has had `schedule.manage` individually **revoked** no longer appears (and immediately falls through to the final `else` branch, seeing only their own section, with no manager-assignment workflow available).
2. **No employees until a section is assigned**: a `schedule.manage` holder (of any level) with no rows in `hris.schedule_user_sections` now sees an empty employee list / schedule grid on Manage Schedule, and `printSchedule` returns its existing "No managed sections assigned." 422. Previously `schedule`-level users would fall back to seeing their own section's employees by default.

---

## Out of Scope

- No changes to `PermissionSeeder`, `access_level_permissions`, or the `schedule.manage` catalog entry.
- No frontend/blade changes — `/schedule_access`'s JS already consumes `managers[]`/`sections[]` generically.
- No changes to `schedule_master_list` or `schedule_access` route middleware/admin-only gating.
