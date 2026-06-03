# Leave Delete & Approver Guard — Design Spec

**Date:** 2026-06-03

## Context

Leave applications have two distinct removal/cancellation paths:

| Path | Condition | Existing? |
|------|-----------|-----------|
| Leave Cancellation (via `LeaveCancellationController`) | Application is **fully approved** (all 3 signatories) | Yes |
| **Delete button** | Application is **not yet fully approved** | New (this spec) |

---

## Task 1 — Delete (not-yet-approved applications)

### Condition
Allow deletion when `leave_applications.status !== 'Approved'` — i.e., still pending in the approval chain or rejected.

### Backend

**`LeaveApplicationController::delete()`** — rewrite to:
1. Look up the application by `id` AND `user_id = Auth::id()` (owns it).
2. If not found → 404 JSON error.
3. If `status === 'Approved'` → block with error "Cannot delete an approved application. Use the leave cancellation form instead."
4. Inside `DB::transaction`:
   - Restore credits: read `leave_date_applied` grouped by `leave_type_id`, increment `based_credit` per type (same logic as `LeaveApprovalService::restoreCredits`). Skip restoration if status is already `'Rejected'` (credits were restored at rejection time).
   - Hard-delete the `leave_applications` row (cascade removes `leave_date_applied`, `leave_details`, `leave_application_approvals`).
5. Return `{ code: 200, message: "Leave application successfully deleted." }`

**Route** — add to `routes/web.php`:
```
Route::delete('/leave-applications/{id}', 'LeaveApplicationController@delete')->name('leave-applications.destroy');
```

### Frontend

**Index response** — add `is_deletable` to each row in `LeaveApplicationController::index()`:
```php
'is_deletable' => strtolower($appRow->status) !== 'approved',
```

**JS (`leave_application.blade.php`)**:
- Add `ROUTE_DELETE_LEAVE` constant pointing to the named route.
- In `getLeaves()`, add a "Cancel Application" item to the dropdown (shown only when `it.is_deletable`).
- Add `cancelApplication(id)` function: show a `confirm()` dialog, then call DELETE via `fetch`, then call `getLeaves()` and `loadCreditBalance()` to refresh the UI.

---

## Task 2 — Approver Guard

**`LeaveApprovalService::updateApprovalStatus()`** — add existence check at the very top of the method (before role resolution):

```php
$app = DB::table('hris.leave_applications')->where('id', $leave_id)->first(['id']);
if (!$app) {
    throw new \Exception('Leave application not found.');
}
```

The controller's existing try/catch already returns this as a JSON error to the approver's UI.

---

## Scope

| Layer | File | Change |
|-------|------|--------|
| Route | `routes/web.php` | Add DELETE route |
| Controller | `LeaveApplicationController.php` | Rewrite `delete()`, add `is_deletable` to index |
| Service | `LeaveApprovalService.php` | Add existence guard in `updateApprovalStatus()` |
| JS | `resources/views/js/leave_application.blade.php` | Route constant, Cancel menu item, `cancelApplication()` |

No schema changes. No new files.
