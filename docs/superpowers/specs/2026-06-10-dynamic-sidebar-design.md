# Dynamic Sidebar Driven by Permissions — Design Spec
**Date:** 2026-06-10
**Status:** Approved

---

## Problem

`resources/views/layouts/sidebar.blade.php` gates every nav item using `session('access_rights')` (the user's level), `session('is_section_head')`, and `session('is_division_head')`. None of this consults `PermissionService`. A per-user permission grant or revoke (via the Admin Permission UI) has no effect on what the sidebar shows — a user could have a link hidden despite holding the permission, or shown despite an admin revoking it.

## Goal

Make the sidebar reflect each user's *effective* permissions (level defaults + per-user overrides), consistent with [[2026-06-10-permission-priority-design]]. Items that have a clean corresponding permission code switch to permission-based checks. A small set of true admin-console pages with no corresponding code keep their existing level-based checks (hybrid approach — see "Out of Scope").

---

## Infrastructure

### 1. Bind `PermissionService` as a singleton

`app/Providers/AppServiceProvider.php` already defines an unused `@permission`/`@endpermission` Blade directive:
```php
Blade::directive('permission', function (string $expression) {
    return "<?php if(app(\App\Services\PermissionService::class)->can(\Illuminate\Support\Facades\Auth::id(), {$expression})): ?>";
});
```
Each call does `app(PermissionService::class)`, which — since the class isn't bound — returns a **new instance** every time, so `effectiveCodes()`'s per-instance cache never helps. With ~20 checks on the sidebar, that's ~20x redundant DB queries per page load.

Add to `AppServiceProvider::register()`:
```php
$this->app->singleton(PermissionService::class);
```
Now all `app(PermissionService::class)` calls within a request share one instance, and `effectiveCodes()` is computed once per user per request.

### 2. New helper: `permission_check(string $code): bool`

The existing `@permission`/`@endpermission` directive wraps a single `if`, fine for gating one item. Several `nav-heading` elements need to show if **any** of their child items are visible — an OR across multiple permission codes (and sometimes a hybrid level check). For that, add a small global helper function:

```php
// app/helpers.php
if (!function_exists('permission_check')) {
    function permission_check(string $code): bool
    {
        return app(\App\Services\PermissionService::class)->can(\Illuminate\Support\Facades\Auth::id(), $code);
    }
}
```

Register via composer autoload:
```json
"autoload": {
    "files": ["app/helpers.php"],
    ...
}
```
(run `composer dump-autoload` after).

Used like: `@if(permission_check('payroll.view') || permission_check('payroll.settings') || permission_check('payroll.manage'))`.

---

## Mapping Table

| Nav item | Current gate | New condition |
|---|---|---|
| PDS | none | none (always shown) |
| SALN | none | `@permission('saln.view_own')` |
| SALN Admin | `hr,HRH,admin` | `@permission('saln.admin')` |
| DTR Viewer | none | `@permission('dtr.view_own')` |
| Employee DTR | `admin/schedule/dh` + headship | **unchanged** (hybrid — `DTRController::canViewEmployeeDtrForUser` is not yet permission-based; converting the link without converting the controller would show a link that leads to an empty/restricted page) |
| Pass Slips | none | `@permission('passslip.file')` |
| Pass Slip Approval | `SH,DH,HRH,hr,admin` | `@permission('passslip.approve')` |
| Hospital Order Filing | none | `@permission('hospital_order.file')` |
| Hospital Order Approval | `admin` + headship | `@permission('hospital_order.approve')` |
| Overtime Filing | none | `@permission('overtime.file')` |
| Overtime Approval | `admin` + headship | `@permission('overtime.approve')` |
| Manage Schedule | `admin,schedule` | `@permission('schedule.manage')` |
| Schedule Master List | `admin` | **unchanged** (hybrid) |
| Schedule Access | `admin` | **unchanged** (hybrid) |
| Leave Application | none | `@permission('leave.file')` |
| Leave Approval | `admin,HRH,SH,DH,hr` | `@permission('leave.approve')` |
| Leave Cancellation | none | `@permission('leave.file')` |
| Cancellation Approval | `admin,HRH,SH,DH,hr` | `@permission('leave.approve')` |
| All Cancellations | `admin,HRH,hr` | **unchanged** (hybrid) |
| Employee (HR Mgmt) | `hr,HRH,admin` | `@permission('employee.edit')` |
| Payroll | `hr,HRH,admin` | `@permission('payroll.view')` |
| Payroll Settings | `hr,HRH,admin` | `@permission('payroll.settings')` |
| Earnings/Deductions | `hr,HRH,admin` | `@permission('payroll.manage')` |
| Taxation | `hr,HRH,admin` | `@permission('payroll.settings')` |
| Cutoff Summary | `hr,HRH,admin` | `@permission('payroll.view')` |
| Quarterly Leave Balances | `hr,HRH,admin` | `@permission('reports.quarterly_leave')` |
| Calendar | `admin,calendar` | **unchanged** (hybrid) |
| Hospital Order Create | `admin` | **unchanged** (hybrid) |
| Access Rights | `admin` | **unchanged** (hybrid) |
| Leave Settings | `admin,calendar` | `@permission('leave.manage_types')` |
| Bulk Leave Credits | `admin,calendar` | `@permission('leave.manage_credits')` |
| Mobile DTR / Position Applied | dead code (`@if(false)` / `d-none`) | unchanged |

### Heading visibility

Each `nav-heading` is wrapped in an `@if` that is true if **any** of its section's items would be visible (OR of the conditions above, mixing `permission_check()` calls with the existing hybrid level checks where applicable). Sections affected:

- **General**: always visible (PDS has no gate).
- **DTR Management**: always visible (`dtr.view_own` covers all levels with DTR access; Employee DTR's hybrid check is additionally OR'd in).
- **Pass Slip Management**: `passslip.file OR passslip.approve`.
- **Hospital Order Management**: `hospital_order.file OR hospital_order.approve`.
- **Overtime Management**: `overtime.file OR overtime.approve`.
- **Schedule Management**: `schedule.manage OR (hybrid admin check for Master List/Access)`.
- **Leave Management**: `leave.file OR leave.approve`.
- **HR Management**: `employee.edit`.
- **Payroll Management**: `payroll.view OR payroll.settings OR payroll.manage`.
- **Reports**: `reports.quarterly_leave`.
- **Settings**: `(hybrid admin/calendar check for Calendar/Hospital Order Create/Access Rights) OR leave.manage_types OR leave.manage_credits`.

---

## Known Behavior Changes

These follow directly from making the sidebar permission-driven and are considered correct outcomes, not bugs:

1. **`schedule`/`it` levels** see a sparser sidebar — they lose SALN, Pass Slip, Hospital Order, Overtime, and Leave Management sections, since their `access_level_permissions` (per `PermissionSeeder`) don't include those codes. Admins can grant individual permissions via the Admin Permission UI if a specific user needs them.
2. **`calendar` level** has no entries at all in `PermissionSeeder`'s level defaults, so it loses most ungated items except Calendar (hybrid) and PDS. If real users hold this level, an admin will need to grant permissions individually via the Permission UI.
3. **`dh` level** gains visibility of "Payroll", "Cutoff Summary", and "Quarterly Leave Balances" — it already holds `payroll.view`/`reports.quarterly_leave` per the catalog; the old gate just never reflected that.
4. **Section/division heads with `standard` level** now correctly see "Leave Approval"/"Cancellation Approval" — they hold `leave.approve` via the headship auto-grant ([[2026-06-10-permission-priority-design]]), but the old gate checked for a `level === 'SH'` value that doesn't actually exist, so this was a latent bug.

---

## Out of Scope

- "Employee DTR", "Schedule Master List", "Schedule Access", "All Cancellations", "Calendar", "Hospital Order Create", and "Access Rights" remain on their existing level-based checks because either (a) there's no corresponding permission code, or (b) the underlying controller logic isn't permission-based yet (a larger effort flagged separately, see [[2026-06-10-permission-priority-design]] Out of Scope).
- No new permission codes are added to the catalog.
- No changes to `PermissionSeeder` or `access_level_permissions`.
