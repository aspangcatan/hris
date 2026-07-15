# Per-Date Leave Disapproval Flagging — Design

## Problem

The leave application approval workflow (SH → DH → HR, see `LeaveApprovalService`) only supports approving or rejecting an entire application. There's no way to approve a multi-date leave application while denying specific individual dates within it (e.g. an employee applies for 5 days of Sick Leave, but 2 of those days had coverage conflicts).

The existing `leave_date_applied.status` enum (`active`/`cancelled`) only supports employee-initiated post-approval cancellation via the separate Leave Cancellation module (`LeaveCancellationService`) — it has no concept of an approver denying a date mid-approval.

## Goal

Let the Supervisor (SH) suggest specific dates for disapproval while still approving the application overall. Let the Division Head (DH) give the final verdict on flagged dates — upholding, overriding, or flagging additional dates DH catches themselves. HR is unaffected and continues to approve/reject the whole application as it does today. Credits for any date that ends up disapproved are restored to the employee.

## Data model

Four new columns on `leave_date_applied` (migration `add_disapproval_flags_to_leave_date_applied`):

| Column | Type | Default | Purpose |
|---|---|---|---|
| `sh_flagged_disapprove` | boolean | `0` | SH's suggestion. Set when SH flags a date. Persists after DH acts — it's the record undo reverts back to. |
| `sh_disapproval_remark` | varchar(255), nullable | `null` | SH's reason for flagging. |
| `final_disapproved` | boolean | `0` | The operative flag. Only DH ever sets it. Drives credit restoration and the "Disapproved" badge shown everywhere else in the app. |
| `dh_disapproval_remark` | varchar(255), nullable | `null` | DH's reason — whether upholding SH's flag, overriding it, or flagging a date SH didn't touch. |

`final_disapproved` is the single source of truth for "is this date actually denied." `sh_flagged_disapprove` is purely an advisory trail that DH sees pre-filled and can freely override.

No new tables. No `flagged_by`/`flagged_at` user-attribution columns — matches the rest of this approval chain, where `leave_application_approvals.approver_id` already identifies an org unit (section/division), not a specific user, so per-user attribution for date flags would be inconsistent with the existing model.

## Flow

### SH stage

`LeaveApprovalService::updateApprovalStatus()` gains an optional parameter, `array $disapprovedDates = []` (each entry: `{date_applied_id, remark}`), used only when `$effectiveRole === 'SH'` and the outcome is `approved`.

For each `date_applied_id` in the set: set `sh_flagged_disapprove = 1`, `sh_disapproval_remark = $remark`. Dates not in the set keep `sh_flagged_disapprove = 0`. `final_disapproved` is untouched — SH's action never restores or deducts credits.

If SH rejects the whole application, the existing `restoreCredits()` path runs unchanged (restores every date), and per-date flags become moot.

### DH stage

Same `updateApprovalStatus()` parameter, used when `$effectiveRole === 'DH'` and the outcome is `approved`. The submitted `$disapprovedDates` set represents DH's **complete final state** for every date DH wants disapproved — not a diff.

Inside the existing `DB::transaction()` in `updateApprovalStatus()`, before updating the DH approval row:

```
for each date_applied row on this application:
    wasDisapproved = current final_disapproved
    willBeDisapproved = date's id is in $disapprovedDates

    if !wasDisapproved && willBeDisapproved:
        set final_disapproved = 1, dh_disapproval_remark = remark
        restore credits_deducted to leave_credits.based_credit (same pattern as restoreCredits())

    if wasDisapproved && !willBeDisapproved:
        set final_disapproved = 0, dh_disapproval_remark = remark (or null)
        re-deduct credits_deducted from leave_credits.based_credit
```

This handles DH's first pass and any future re-submission symmetrically — no separate "credits already restored" flag needed, since the diff is always computed against the current `final_disapproved` value at submit time.

If DH rejects the whole application, existing `restoreCredits()` runs unchanged, independent of per-date state.

### HR stage

No changes. HR continues to approve/reject the whole application via the existing path. HR's view of the application shows disapproved dates as read-only (see UI section) but HR cannot change them.

### Undo

`LeaveApprovalService::undoApproval()` currently only reverts the `leave_application_approvals` row for the undone stage back to `pending` and (if HR) reverts `leave_applications.status`.

Extend it: when the role being undone is `DH`, for every date on the application where `final_disapproved = 1`:
- Re-deduct the previously restored credits from `leave_credits.based_credit`.
- Reset `final_disapproved = 0` and `dh_disapproval_remark = null`.
- Leave `sh_flagged_disapprove`/`sh_disapproval_remark` untouched, so when DH redoes their review, SH's original suggestion is still there.

Undoing SH does not need any per-date handling, since SH's action never touches `final_disapproved` or credits.

## UI

### Approval modal (`resources/views/modals/leave.blade.php`)

The "Dates Applied" table gets a 4th column. Its content depends on whether the current viewer is currently eligible to act as SH or DH on this specific application (reuse `LeaveApprovalService::resolveCurrentApprovalRoleForLeave()`, already exposed to the frontend via the existing `getLeaveApplication` response shape):

- **Eligible SH/DH viewer, application at their stage:** a checkbox per row. For the SH view, pre-checked from `sh_flagged_disapprove`. For the DH view, pre-checked from `sh_flagged_disapprove OR final_disapproved` — this shows SH's suggestion on DH's first pass, and correctly re-shows it after a DH undo (since undo clears `final_disapproved` but preserves `sh_flagged_disapprove`). A small text input for the remark appears when a row is checked.
- **Everyone else** (HR, the employee's own view, "Approved by This Stage"/"Approved in All Stages" tabs, any date already resolved): a read-only "Disapproved" badge when `final_disapproved` is true — same visual pattern as the existing `is_cancelled` badge in `js/leave_application.blade.php`.

### JS (`resources/views/js/leave_approval.blade.php`)

`handleApproval()` gathers the checked date IDs and their remarks into an array and includes it in the existing `updateApprovalStatus` POST body as `disapproved_dates`. No changes needed for reject — disapproved-dates only apply on an `approved` outcome.

## Backend changes summary

- `database/migrations/xxxx_add_disapproval_flags_to_leave_date_applied.php` — new migration, 4 columns as above.
- `LeaveApprovalService::updateApprovalStatus()` — new `array $disapprovedDates = []` param; new private helper `applyDateDisapprovals(int $leaveId, string $role, array $disapprovedDates): void` containing the SH/DH logic above.
- `LeaveApprovalService::undoApproval()` — extended with the DH-credit-reversal block above.
- `LeaveApplicationController::updateApprovalStatus()` — validates and passes through `$request->disapproved_dates` (nullable array of `{date_applied_id, remark}`).
- `LeaveApplicationService::findById()` — the `dates` select (line ~296) adds `lda.sh_flagged_disapprove`, `lda.sh_disapproval_remark`, `lda.final_disapproved`, `lda.dh_disapproval_remark` so the frontend has what it needs to render checkboxes/badges.

## Out of scope

- HR gaining any per-date power (explicitly ruled out).
- A distinct `leave_applications.status` value for "partially disapproved" (explicitly ruled out — status stays application-level; nuance lives in the dates).
- Per-user attribution of who flagged/disapproved a date (matches existing org-unit-level approval model, not user-level).

## Testing

Feature tests in `tests/Feature/` (new file, e.g. `LeaveDateDisapprovalTest.php`):

1. SH flags a date while approving → `sh_flagged_disapprove=1` on that date, `final_disapproved` stays `0`, no credit change, SH stage status = `approved`.
2. DH upholds an SH-flagged date → `final_disapproved=1`, credits restored, DH stage status = `approved`.
3. DH overrides an SH-flagged date (submits without it in `disapproved_dates`) → `final_disapproved` stays `0`, no credit change.
4. DH flags a new date SH never flagged → `final_disapproved=1` for that date, credits restored.
5. DH undoes their approval after disapproving a date → `final_disapproved=0`, credits re-deducted, `sh_flagged_disapprove` unchanged from before.
6. SH or DH rejects the whole application → all dates' credits restored via existing `restoreCredits()`, regardless of any per-date flags set.
