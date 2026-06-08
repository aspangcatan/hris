# Overtime Multi-Level Approval + CTO Credits Design

**Date:** 2026-06-08
**Module:** Overtime

---

## Overview

Two features added to the overtime module:

1. **Overtime Hours column** — computed display column (Time To − Time From) shown in both the employee filing table and the approver review table.
2. **Multi-level approval chain (SH → DH → HR)** — replaces the current single-reviewer flow. Once all three levels approve, the filing employee earns Compensatory Time Off (CTO) leave credits calculated from overtime minutes via `minute_conversions`.

---

## 1. Database

### New table: `overtime_request_approvals`

Mirrors the shape of `leave_application_approvals`.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | auto-increment |
| `overtime_request_id` | bigint unsigned | references `overtime_requests.id` |
| `approver_id` | int nullable | actual user ID of the approver |
| `role` | enum('SH','DH','HR') | step in the chain |
| `order_no` | tinyint unsigned | 1=SH, 2=DH, 3=HR |
| `status` | enum('pending','approved','rejected') | default `pending` |
| `remarks` | text nullable | |
| `approved_at` | timestamp nullable | |
| `created_at` | timestamp nullable | |
| `updated_at` | timestamp nullable | |

### `overtime_requests` table — no schema changes

Existing `status`, `reviewed_by`, `reviewed_at` stay. `reviewed_by`/`reviewed_at` are populated by the final HR approval for audit trail.

### Approver ID resolution (on filing submission)

| Role | Source |
|---|---|
| SH | `tdh_user.section.head` WHERE `section.id = employee.section` |
| DH | `tdh_user.division.head` WHERE `division.id = employee.division` |
| HR | User ID 15 (hardcoded, same as leave applications) |

If a section or division head cannot be resolved (null), that step's `approver_id` is stored as `null` and auto-skipped (treated as approved) during the review flow.

---

## 2. Filing Submission

`OvertimeServices::fileForSelf()` is updated to run inside a DB transaction:

1. Duplicate-check (existing logic).
2. Insert into `overtime_requests`.
3. Resolve SH, DH, HR approver IDs.
4. Insert 3 rows into `overtime_request_approvals` (order_no 1, 2, 3).

---

## 3. Approval Flow

### Who sees the review queue

| Viewer | Condition |
|---|---|
| SH | `role = 'SH'`, `status = 'pending'`, `approver_id = Auth::id()` |
| DH | `role = 'DH'`, `status = 'pending'`, SH step is `approved`, `approver_id = Auth::id()` |
| HR | `role = 'HR'`, `status = 'pending'`, SH + DH steps are both `approved`, `approver_id = Auth::id()` |
| Admin | All `overtime_requests` with any approval step still pending |

### On Approve (any level)

1. Update the matching `overtime_request_approvals` row: `status = 'approved'`, `approved_at = now()`, `remarks`.
2. If the role is **HR** (final step):
   - Award CTO credits (see Section 4).
   - Set `overtime_requests.status = 'approved'`, `reviewed_by = Auth::id()`, `reviewed_at = now()`.
3. If the role is **SH** or **DH**: `overtime_requests` stays `pending` (awaiting next step).

### On Reject (any level)

1. Update the matching `overtime_request_approvals` row: `status = 'rejected'`, `approved_at = now()`, `remarks`.
2. Set `overtime_requests.status = 'rejected'` immediately — chain stops.

### Idempotency

Before awarding CTO credits, verify `overtime_requests.status = 'pending'`. If already `approved`, skip. Prevents double-award on duplicate calls.

---

## 4. CTO Credit Award

Triggered only when HR approves (final step) and `overtime_requests.status` is still `pending`.

### Formula

```
totalMinutes = time_to − time_from (in minutes)
fullHours    = floor(totalMinutes / 60)
remaining    = totalMinutes % 60

credits = (fullHours × minute_conversions[60].points_deduction)
        + (remaining > 0 ? minute_conversions[remaining].points_deduction : 0)
```

Example: 90 min OT → 1 full hour (0.125) + 30 remaining minutes (minute_conversions[30]) = total credits.

### Upsert into `leave_credits`

- `leave_id = 16` (CTO, from `leave_types`)
- `user_id` = filing employee's user ID
- If row exists: `based_credit += credits`
- If no row: insert with `based_credit = credits`, `used_credit = 0`

---

## 5. UI Changes

### `overtime_file.blade.php` (employee view)

- **Overtime Hours column** — added between Time Range and Reason. Computed in JS:
  ```js
  const mins = moment(timeTo, 'HH:mm:ss').diff(moment(timeFrom, 'HH:mm:ss'), 'minutes');
  const h = Math.floor(mins / 60), m = mins % 60;
  // display: "2h 30m" or "30m"
  ```
- **Status column** enriched — shows chain position: `Pending (SH)` / `Pending (DH)` / `Pending (HR)` / Approved / Rejected. The API response includes a `pending_step` field (SH/DH/HR or null) to drive this.

### `overtime_approval.blade.php` (reviewer view)

- **Overtime Hours column** — same JS computation as above.
- **Queue filtered server-side** — each reviewer only sees filings at their current step.
- **Admin view** — sees all pending filings across all steps; includes a **Level** column (SH / DH / HR) showing which step is currently awaiting action.

---

## 6. API / Route Changes

| Method | Route | Change |
|---|---|---|
| GET | `overtime/my-filings` | Response includes `pending_step` (SH/DH/HR/null) |
| GET | `overtime/filings-for-review` | Filters by caller's role + approval step |
| POST | `overtime/filings/{id}/approve` | Approves caller's step; awards CTO on HR final |
| POST | `overtime/filings/{id}/reject` | Rejects at caller's step; stops chain |

The existing `canReviewOvertimeFilings()` check in the controller is extended to also allow HR (user ID 15).

---

## 7. Out of Scope

- Retroactive CTO credits for overtime filings approved under the old single-approver flow.
- Cancellation / recall of an approved overtime filing.
- Notification emails on each approval step.
