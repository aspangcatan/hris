# Employee Permission Descriptions & Sidebar Gating — Design

## Problem

The `employee.view` and `employee.edit` permissions exist in `hris.permissions` but their descriptions don't reflect their intended meaning, and the sidebar link to the Employee (Personnel) page is gated on `employee.edit` instead of `employee.view`. This means `employee.view`-only levels (currently `dh` and `standard`, per `PermissionSeeder`) have no way to reach the page even though the route itself only requires `employee.view`.

## Intended Semantics

- `employee.view` — "View other employees' PDS (read-only)". All users can always view/edit their own `/profile` regardless of this permission; `employee.view` governs access to *other* employees' records.
- `employee.edit` — "Edit other employees' PDS". Required to save changes to someone else's record.

## Changes

1. **`database/seeders/PermissionSeeder.php`**
   - Update the `description` field for `employee.view` to "View other employees' PDS (read-only)".
   - Update the `description` field for `employee.edit` to "Edit other employees' PDS".
   - No changes to `$levelDefaults` — existing level grants (`dh`/`standard` get `employee.view`; `hr`/`hrh` get both) are unchanged.

2. **`resources/views/layouts/sidebar.blade.php`**
   - Change the "HR Management → Employee" nav link gate from `@permission('employee.edit')` ... `@endpermission` to `@permission('employee.view')` ... `@endpermission`.

3. **No other code changes.**
   - `/employee` route is already gated on `employee.view` (`routes/web.php`) — correct as-is.
   - `EmployeeController::insertUpdateEmployee()` already enforces: self-edits always allowed; edits to other users' records require `employee.edit` (`$can_update_others`). No change needed.

## Resulting Behavior

- `employee.view`-only users (e.g. `dh`, `standard`): see the "Employee" link, can open the Personnel list and any employee's PDS modal to view it. Attempting to save changes to someone else's record returns the existing "Unauthorized employee update request" error from `insertUpdateEmployee()`. The modal itself is not made read-only in this change (out of scope).
- `employee.edit` users (e.g. `hr`, `hrh`, `admin`): unchanged — full view + edit access to other employees' PDS.

## Testing

- Update/add a test asserting the `hris.permissions` descriptions for `employee.view` and `employee.edit` match the new wording (e.g. extend an existing seeder or permission test, or add a small assertion to `PermissionServiceLevelMatrixTest`/a new lightweight test if none fits).
- Manual: log in as a user with only `employee.view` (e.g. a `standard`-level user) and confirm the "Employee" sidebar link now appears and the page loads.

## Out of Scope

- Read-only rendering of the employee modal (all 12 tabs) for `employee.view`-only users — deferred per user decision.
- Re-evaluating which levels get `employee.view`/`employee.edit` by default.
