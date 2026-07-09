# Payroll Assignatories Settings — Design Spec

**Date:** 2026-07-09

## Problem

Payroll's approval workflow (added in commit `b837898`) is a hardcoded 3-phase chain: SH (section head, derived from the preparer's org structure) → DH (division head, likewise derived) → HR (a literal hardcoded user id, `15`), baked directly into `PayrollController::submitForApproval()`. There is no way for an admin to change the number of phases, add/remove phases, or reassign who approves at each phase without a code change.

The nearly-identical Overtime approval chain (`overtime_request_approvals` / `OvertimeServices`) has the same hardcoded-3 structure but a cleaner, generic "current step = pending row with no earlier pending `order_no`" gating pattern, which this design reuses instead of payroll's per-role SQL.

## Goal

An admin settings page ("Payroll Assignatories") where the number of approval phases, their display labels, and which specific user fills each phase are fully configurable. This is a clean cutover — no in-flight payrolls need to keep working under the old SH/DH/HR scheme, and it does not touch printed payslip/PDF signature blocks (approval gating only).

## Data model

### New table: `payroll_assignatories` (settings)

```php
$table->bigIncrements('id');
$table->unsignedTinyInteger('order_no');
$table->string('label');                 // e.g. "Section Head", "Comptroller"
$table->unsignedInteger('user_id');       // tdh_user.users.id
$table->boolean('is_active')->default(true);
$table->timestamps();
```

No DB-level uniqueness on `order_no` — it's a sortable int renumbered by array position on every save (same convention as `payroll_tax_rules.sort_order`). No minimum row count enforced at save time (an admin can legitimately clear the list temporarily); the "must have ≥1 active phase" rule is enforced at *submission* time instead, in `submitForApproval()`.

### `payroll_approvals` (existing table) — modified

The `role` column (`enum('SH','DH','HR')`) becomes a plain string column:

```php
$table->string('label');   // replaces enum('role', ['SH','DH','HR'])
```

Each row is a **snapshot** taken at submission time: `label` and `approver_id` are copied from `payroll_assignatories` as configured *at that moment*. Editing the assignatories list later never retroactively changes an already-submitted payroll's chain — this avoids a payroll's approval chain silently changing mid-flight if settings are edited while it's pending.

`order_no`, `approver_id`, `status`, `remarks`, `acted_at` are unchanged.

Migration approach: since this is a clean cutover with no in-flight data to preserve, the migration drops and recreates the `role` column as `label` (or renames + widens it) directly, rather than adding a parallel column.

## Backend changes

### Settings endpoints (new, in `PayrollController`)

- `GET /payroll-assignatories` — returns the active list ordered by `order_no`.
- `POST /payroll-assignatories` — accepts the full list `[{label, user_id}, ...]`, validates each row has a non-empty `label` and a `user_id` that exists in `tdh_user.users`, renumbers `order_no` by array position, and replaces the active set transactionally (same all-or-nothing bulk-save pattern as `savePayrollTaxRules()`).
- Both gated by `middleware('permission:payroll.settings')`, matching the tax-rules settings routes.
- Page route: `GET /settings/payroll-assignatories` → `view('settings.payroll_assignatories')`, same gating.

### `submitForApproval()` rewrite

Remove the hardcoded SH/DH-derivation and HR=15 block entirely. Instead:

1. Load active `payroll_assignatories` ordered by `order_no`.
2. If empty, fail with a 422 and a message telling the admin to configure at least one assignatory first.
3. Otherwise, insert one `payroll_approvals` row per assignatory: `label` and `approver_id` snapshotted from the assignatory, `order_no` from its position, `status = 'pending'`.
4. Set payroll `status = 'for_approval'` as before.

### Approval gating rewrite

Replace `getUserPendingApproval()` and `getPendingApprovalMapForCurrentUser()`'s hardcoded per-role SQL (SH matches `approver_id = user->section`, DH requires SH already approved, HR requires DH already approved) with the generic pattern already used by `OvertimeServices::reviewFiling()`:

> The "current step" for a payroll is the pending `payroll_approvals` row with the lowest `order_no` among rows still `pending` (equivalently: no earlier-`order_no` row for that payroll is still `pending`). A user can act on it only if `approver_id` matches their user id.

This naturally supports any number of phases instead of being wired to exactly 3, and removes the SH/DH org-structure-derivation logic from the approval-checking path entirely (since `approver_id` is just a snapshotted user id now, not a role to resolve).

`approvalAction()` itself (reject cascades to all other pending rows + sets payroll `status = 'rejected'`; approve flips payroll to `status = 'approved'` when `pendingCount === 0`) stays conceptually the same, just reading `label` instead of `role` for messages/remarks.

`releasePayroll()` is unchanged — still gated on `status === 'approved'`.

### Frontend

`payroll.blade.php`: `"Approve (${item.pending_approval_role})"` becomes `"Approve (${item.pending_approval_label})"` — same mechanism, no longer a fixed 3-value set.

## Settings UI

New Blade view `resources/views/settings/payroll_assignatories.blade.php`, following the same shell/pattern as `resources/views/settings/payroll_tax_rules.blade.php`: a card with an editable table, one row per phase:

- **Order** — numeric input, drives sort (matches the plain-numeric `sort_order` field tax rules already uses; no drag-and-drop).
- **Label** — text input (e.g. "Section Head", "Comptroller").
- **Assigned user** — a searchable select over `tdh_user.users` (reusing whatever employee-search/picker pattern already exists elsewhere in the app, e.g. DTR's employee search).
- **Remove row** button per row, plus an **Add phase** button.

One **Save** button performs the bulk replace via `POST /payroll-assignatories`, matching the tax-rules all-or-nothing save UX. Plain Blade + vanilla JS/fetch, no new frontend framework, consistent with the rest of the app.

## Testing

### New: `PayrollAssignatoryTest` (Feature)

- Settings CRUD round-trip: save a list, reload, confirm it matches.
- Reordering renumbers `order_no` correctly on save.
- Validation rejects a phase with an empty label or a `user_id` that doesn't exist.
- Route permission gating (mirrors `PermissionLevelControllerTest`'s admin-only checks) — non-privileged users get rejected.

### New coverage in the payroll approval tests

- `submitForApproval()` creates one snapshot `payroll_approvals` row per configured active assignatory, in order, with the correct `label`/`approver_id`/`order_no`.
- `submitForApproval()` fails with a 422 when the assignatories list is empty.
- A later-phase approver cannot act before an earlier phase is approved (mirrors `OvertimeApprovalTest::dh_cannot_approve_before_sh_approves`).
- Rejection at any phase cascades `rejected` to all other pending rows and flips the payroll to `status = 'rejected'`.
- Full-chain approval (all phases approved) flips the payroll to `status = 'approved'`.

### Existing regression to update

`PayrollCutoffTest::full_payroll_lifecycle_moves_through_approval_chain_to_released` currently seeds section/division heads and asserts against the hardcoded SH/DH/HR chain (HR = user 15). Since this is a clean cutover, this test needs rewriting to seed `payroll_assignatories` config in its setup instead of relying on org-structure derivation — updated as part of this plan rather than left broken.

## Out of scope

- Per-division/per-section approval chains (one global chain only, per user decision).
- Printed signature blocks on payslips/payroll PDFs (approval gating only, no print tie-in).
- Preserving or migrating any in-flight `payroll_approvals` rows created under the old SH/DH/HR scheme (clean cutover).
