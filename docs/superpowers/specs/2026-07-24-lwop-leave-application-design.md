# LWOP (Leave Without Pay) Leave Application — Design

## Purpose

Employees currently cannot file a leave application if they lack sufficient leave credits — the system throws an "Insufficient credits" error. This feature adds a third tab, "LWOP", beside "Leave Application" and "Monetization" on the leave application page. It duplicates the leave application filing flow (same leave types, dates, attachments, approval chain) but deliberately skips the credit balance check and never deducts credits, so employees can file leave as without-pay when they have no balance.

## Data Model

- Add a new column `leave_applications.is_lwop` (boolean, default `0`).
- All other tables are reused unmodified: `leave_date_applied` (per-date leave_type_id/hours/credits_deducted), `leave_application_attachments`, `leave_application_approvals`, `leave_application_logs`.
- The flag lives at the application level (not per date-row) because the employee chooses LWOP by which tab/button they file through — every date in that application inherits LWOP status from its parent.
- Credit handling: when `is_lwop = 1`, no row in `leave_credits` is checked or decremented, and every corresponding `leave_date_applied.credits_deducted` is written as `0`. This is always a full skip — there is no partial-credit/partial-LWOP math. An LWOP filing never deducts credits even if the employee happens to have some balance available for that leave type.
- Eligible leave types: all active leave types (VL, SL, PL, etc.) — the same dropdown as the regular Leave Application tab. No new eligibility flag is added to `leave_types`.

## UI

**Tab nav** (`resources/views/leave_application.blade.php`): add a third tab button `tab-lwop` targeting `#pane-lwop`, positioned after the Monetization tab.

**Tab pane:** `#pane-lwop` mirrors the Leave Application pane's structure — a "File LWOP" button (`#btn_file_lwop`), its own history table (`#lwop_history_list`), and its own pagination container (`#lwop_pagination_container`). History is lazy-loaded on `shown.bs.tab`, matching how the Monetization tab already lazy-loads.

**Modal — reused, not duplicated:** The existing `#file_leave_modal` (from `modals/leave.blade.php`) is reused for both regular and LWOP filing. A hidden `is_lwop` input is set to `1` when the "File LWOP" button opens the modal (and `0` for the regular "File Leave" button). The modal title switches between "File Leave" and "File LWOP" based on this flag. No new modal file is created — this avoids duplicating and having to keep in sync the date-picker, leave-type dropdown, and attachment-upload markup.

**JS** (`resources/views/js/leave_application.blade.php`): add an LWOP block analogous to the existing Monetization block — own routes, a `getLwopHistory()` paginated loader, and a `shown.bs.tab` hook for lazy loading. The existing `handleSaveLeave()` submit handler is extended to accept/read the `is_lwop` flag and skip its client-side balance guard (currently at lines 395-412) when the flag is set, then include `is_lwop` in the AJAX payload.

## Backend Flow

**Routes** (`routes/web.php`):
- `POST /insert_lwop_applications` → `LeaveApplicationController@insertLeaveApplication` (same controller method as regular filing; distinguished by an `is_lwop` request param, avoiding a duplicate method that would re-implement the same validation).
- `GET /get_lwop_applications` → `LeaveApplicationController@index` (same method as regular listing, filtered by `is_lwop`).

**Controller** (`LeaveApplicationController::insertLeaveApplication`): reads `is_lwop` from the request body (boolean, default `false`), validates it, and passes it through to the service.

**Service** (`LeaveApplicationService::insertLeaveApplication` and `updateLeaveApplication`):
- Accepts an `$isLwop` flag.
- Wraps the existing "validate credit balance per type" block (the loop that throws `"Insufficient {$desc} credits..."`) in `if (!$isLwop) { ... }` — entirely skipped for LWOP.
- At the credit-decrement step, skips the `leave_credits.based_credit` decrement when `$isLwop`, and writes `leave_date_applied.credits_deducted = 0` for every row.
- Writes `leave_applications.is_lwop = $isLwop` on insert.
- `updateLeaveApplication()` receives the identical treatment since it repeats the same checks for edits.

**Index/list query:** `LeaveApplicationController@index` gets a `where('is_lwop', ...)` filter driven by which tab's AJAX call is hitting it — the Leave Application tab passes `is_lwop=0`, the LWOP tab passes `is_lwop=1`. This keeps the two history lists filtered separately rather than mixed with a badge.

## Approval Queue

No new table or controller. LWOP applications flow into the existing `leave_application_approvals` table and go through the same SH→DH→HR chain (`getLeaveForApproval`, `updateApprovalStatus`, `undoApproval` on `LeaveApplicationController`), same as regular leave applications.

**Visual tag:** The approval list view shows an "LWOP" badge/label on rows where `leave_applications.is_lwop = 1`, so approvers can see at a glance that no credits will be deducted for that filing.

**Rejection handling:** The existing credit-restore logic on rejection must skip restoring credits for LWOP rows, since none were deducted — a guard such as `if (!$application->is_lwop) { restore credits }`.

## Error Handling

No new failure modes are introduced. LWOP removes exactly one failure path (the insufficient-credit exception) from the existing flow. All other validation — valid leave type, valid date range, attachment rules, duplicate-date checks — is unchanged and applies identically to LWOP filings.

## Testing / Verification

No automated DB tests exist on this project (RefreshDatabase is unsafe here — it would wipe the live shared database). Verification is manual, via tinker + the actual UI:

1. File a test LWOP application for an employee with zero leave credits available for a given leave type; confirm it succeeds (no exception).
2. Confirm `leave_credits.based_credit` is untouched after the LWOP filing.
3. Confirm the filing appears only in the LWOP tab's history list, not in the regular Leave Application tab's list.
4. Confirm the filing flows correctly through SH→DH→HR approval, and that the approval list shows the LWOP badge.
5. Reject an LWOP filing and confirm no credit-restore attempt occurs (since none were deducted).
6. File a regular (non-LWOP) leave application and confirm the credit check and deduction still work exactly as before (no regression).
