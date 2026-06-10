# Permission Priority Over Level/Role Access — Design Spec
**Date:** 2026-06-10
**Status:** Approved

---

## Problem

`PermissionService::effectiveCodes()` already correctly layers per-user `user_permissions` overrides on top of `access_level_permissions` (revoke always wins, grant adds on top of level). However, two controllers bypass this entirely with an `OR` against session-based role flags:

**`OvertimeController::canReviewOvertimeFilings()`**
```php
return $this->permissions->can(Auth::id(), 'overtime.approve')
    || (bool) session('is_section_head')
    || (bool) session('is_division_head')
    || Auth::id() == 325;
```

**`HospitalOrderController::canReviewHospitalOrderFilings()`**
```php
return $this->permissions->can(Auth::id(), 'hospital_order.approve')
    || (bool) session('is_section_head')
    || (bool) session('is_division_head');
```

`session('is_section_head')` / `session('is_division_head')` are set at login based on whether the user is the `head` of a row in `tdh_user.section` / `tdh_user.division` — independent of `permissions`. Because of the `OR`, if an admin **revokes** `overtime.approve` or `hospital_order.approve` from a section/division head via the Admin Permission UI, that head can still approve filings — the revoke has no effect. This contradicts the intent that explicit per-user permission settings (grant or revoke) should be the final word, taking priority over level/role-derived access.

`OvertimeController` also has a hardcoded `Auth::id() == 325` bypass with no audit trail.

---

## Goal

Per-user permission grants/revokes (managed via the Admin Permission UI / `PermissionService`) become the sole and final authority for `overtime.approve` and `hospital_order.approve`. Session role flags (`is_section_head`, `is_division_head`) and the hardcoded user-ID bypass no longer factor into these checks.

---

## Changes

### 1. Controller checks simplified

```php
// app/Http/Controllers/OvertimeController.php
private function canReviewOvertimeFilings(): bool
{
    return $this->permissions->can(Auth::id(), 'overtime.approve');
}
```

```php
// app/Http/Controllers/HospitalOrderController.php
private function canReviewHospitalOrderFilings(): bool
{
    return $this->permissions->can(Auth::id(), 'hospital_order.approve');
}
```

The `Auth::id() == 325` bypass is removed entirely. If that user still needs `overtime.approve`, an admin grants it via the Permission UI (auditable, like any other grant).

### 2. Login-time auto-grant sync for new heads

Background: `PermissionSeeder` originally auto-granted `leave.approve`, `overtime.approve`, `passslip.approve`, and `hospital_order.approve` (as `user_permissions` rows with `type='grant'`) to every user who was a section/division head **at seed time**. This is a one-time operation — anyone who becomes a head afterward never receives these rows. Today that's masked by the `OR session(...)` fallback; once removed, new heads would lose approval ability until an admin notices and grants it manually.

Add a new method to `PermissionService`:

```php
public function syncHeadshipGrants(int $userId, bool $isSectionHead, bool $isDivisionHead): void
```

Behavior:
- Permission codes considered: `leave.approve`, `overtime.approve`, `passslip.approve`, `hospital_order.approve` (same set as the original seeder logic).
- If `$isSectionHead || $isDivisionHead` is true:
  - For each code, check if a `user_permissions` row already exists for `(user_id, permission_id)` (either `grant` or `revoke`).
  - If **no** row exists, insert a `grant` row and write a `permission_audit_logs` entry: `performer_id = $userId`, `target_user_id = $userId`, `action = "auto-granted {code} via section/division headship"`, `details = json_encode(['code' => $code, 'type' => 'auto-grant'])`.
  - If a row already exists (grant or revoke), leave it untouched — admin decisions are never overwritten.
- If neither flag is true: do nothing. (No auto-revoke if a user stops being a head — admins handle removal manually via the Permission UI. This is a deliberate scope limit.)
- Clear the in-memory `effectiveCodes` cache for `$userId` after any insert (reuse existing `unset($this->cache[$userId])` pattern).

### 3. Wire into login flow

In `UserController`'s login method, immediately after the existing:
```php
session([
    'is_section_head' => DB::table('tdh_user.section')->where('head', Auth::id())->exists(),
    'is_division_head' => DB::table('tdh_user.division')->where('head', Auth::id())->exists(),
]);
```
add:
```php
app(PermissionService::class)->syncHeadshipGrants(
    Auth::id(),
    session('is_section_head'),
    session('is_division_head')
);
```

---

## Net Effect

- Admin grant/revoke via the Permission UI is the final word for `overtime.approve` and `hospital_order.approve`, matching the existing behavior of `leave.approve`/`passslip.approve` (which already had no session-flag fallback).
- New section/division heads still receive sensible default approval permissions automatically (via login sync instead of a one-time seed), without overriding any existing admin override.
- The hardcoded `Auth::id() == 325` bypass is removed; any continued access for that user goes through the auditable permission system.

---

## Out of Scope

- DTR view scoping, Payroll approval routing, and Schedule management still use `session('access_rights')`/role checks with no permission-code equivalent at all. Extending permission coverage to those areas is a separate, larger effort.
- Auto-revoking `leave.approve`/`overtime.approve`/etc. when a user stops being a section/division head is not handled — admins manage that manually.
