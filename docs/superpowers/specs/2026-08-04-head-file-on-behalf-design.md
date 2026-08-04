# Section/Division Head: File Pass Slip / Hospital Order on Behalf of Employees

Date: 2026-08-04

## Problem

Section Heads and Division Heads can currently only *approve* Pass Slips and
Hospital Order filings for the employees under them — both features are
hardcoded to self-filing (`user_id = Auth::id()` in `PassSlipService` and
`HospitalOrderServices`). Heads need the ability to file these on behalf of
their own employees (e.g. an employee is unavailable, or the head is
initiating the filing directly).

## Current State (relevant prior art)

- `tdh_user.section` / `tdh_user.division` each have a `head` column (user
  id). `tdh_user.users.section` / `.division` place an employee under a
  section/division. This is the sole org-hierarchy mechanism in the app —
  there is no separate supervisor/immediate-head table.
- `UserController::authenticate()` sets `session(['is_section_head' => ...,
  'is_division_head' => ...])` at login and calls
  `PermissionService::syncHeadshipGrants()`, which auto-grants
  `leave.approve`, `overtime.approve`, `passslip.approve`,
  `hospital_order.approve` to heads.
- `PassSlipService::getManagedSectionIds()` and
  `HospitalOrderServices::applyManagedEmployeeScope()` already resolve "which
  employees does this head manage" (division head → all sections under their
  division; section head → their section only). Both are reused, unchanged,
  by this feature.
- Pass Slips use a single-step approval (`pass_slips.status` +
  `approver_id`/`approved_at`). Hospital Order filings use the same shape on
  `hospital_order_employees` (`status`, `reviewed_by`/`reviewed_at`).
- Closest existing "actor files for someone else" precedent:
  `LeaveBulkCreditController`, which writes `created_by` (the admin) against
  N `user_id` rows (the targets). This feature follows the same
  actor-vs-subject split.

## Design

### Data model

Add a nullable `filed_by` column (unsigned int, no FK constraint — matches
the existing untyped-FK convention of `approver_id`/`reviewed_by`/
`created_by`) to:

- `pass_slips`
- `hospital_order_employees`

Semantics: `NULL` = self-filed (all existing rows and all normal filings
remain unaffected). When set, it holds the id of the head who filed on the
employee's behalf; `user_id`/`userid` continues to mean "whose record this
is" (the subject), preserving all existing approval-scoping queries
untouched.

### Permissions

Add two new permission codes to `PermissionSeeder`:

- `passslip.file_on_behalf`
- `hospital_order.file_on_behalf`

Auto-grant both inside the existing `PermissionService::syncHeadshipGrants()`
alongside the current auto-grants, so any Section/Division Head gets them
automatically and they remain independently revocable from the `*.approve`
permissions.

### Auto-approval

Filings created on behalf of an employee are written directly as approved —
no pending-review step:

- `status = 'approved'`
- `approver_id` (pass slip) / `reviewed_by` (hospital order) = the filing
  head's id
- `approved_at` / `reviewed_at` = now()

Rationale: the head is already the approver for their own section/division
today, so routing an on-behalf filing through the pending queue would just
mean the same person approves their own submission — auto-approval is
simpler and equivalent in trust level.

### Filing flow

**Employee picker** — added to `pass_slip.blade.php` and
`hospital_order_file.blade.php`, rendered only when
`session('is_section_head')` or `session('is_division_head')` is true:

- Select2 dropdown, options = "Myself" (default) + the head's managed
  employees.
- Managed-employee list comes from a new endpoint reusing
  `getManagedSectionIds()` / `applyManagedEmployeeScope()`, following the
  same query shape as `LeaveBulkCreditController::getEmployees()`.
- Default "Myself" means the existing self-filing path is untouched when a
  head files for themselves.

**Backend:**

- `PassSlipService::store()` accepts an optional `$targetUserId`. When
  provided and different from `Auth::id()`, the service re-validates
  server-side (never trusting the submitted dropdown value alone) that the
  target is within the caller's managed section/division via the existing
  scope-resolution methods, then writes `user_id = target`, `filed_by =
  Auth::id()`, plus the auto-approval fields above. If the target is not in
  scope, reject with a validation error.
- `HospitalOrderServices` gains a `fileForEmployee($hoId, $targetUserId,
  $filedDate, $timeFrom, $timeTo)` counterpart to `fileForSelf()`, with the
  same scope re-validation and auto-approval semantics.

### Visibility

- The employee's own list (`getUserPassSlips()` / `getMyFilings()`) stays
  scoped by `WHERE user_id = Auth::id()` — since on-behalf rows are written
  with the employee's own id as `user_id`, no query change is needed for
  them to already appear there. Rows where `filed_by IS NOT NULL` display a
  "Filed by {Head Name}" badge.
- The head's existing managed-section/division review views (used today for
  approving pending filings) also show a "Filed by {Head Name}" badge on
  on-behalf rows they submitted, so they have a record of what they filed.

### Edit/Delete authorization

- `PassSlipService::destroy()`: an employee can delete only rows where
  `filed_by IS NULL`. Rows with `filed_by` set can only be deleted by the
  matching `filed_by` user (the filing head) or an admin.
- `HospitalOrderServices::deleteMyFiling()`: same rule, layered on top of the
  existing pending/rejected-only delete restriction (on-behalf rows are
  `approved`, so in practice only the filing head or an admin can void them,
  via a head/admin-facing delete path — not the employee-facing one).

### Downstream consumers (DTR / Payroll)

No changes required. `DTRServices` and `PayrollServices` already consume
pass slips/hospital order filings by `status = 'approved'`, which on-behalf
records satisfy immediately upon creation.

## Out of scope

- Admin/HR filing on behalf of arbitrary employees (this feature is strictly
  a Section/Division Head capability, scoped to their managed
  section/division).
- Multi-step approval or notification workflows for on-behalf filings
  (auto-approved, no chain).
- Changes to Leave or Overtime modules (Pass Slip and Hospital Order only).

## Testing plan

Per TDD: service-level tests for `PassSlipService::store()` and
`HospitalOrderServices::fileForEmployee()` covering — self-file unaffected,
in-scope on-behalf file succeeds with correct `filed_by`/auto-approval
fields, out-of-scope target is rejected, non-head user cannot pass a
`$targetUserId` at all, employee cannot delete an on-behalf row, filing head
can delete their own on-behalf row, badge/visibility query includes
on-behalf rows in the employee's own list.
