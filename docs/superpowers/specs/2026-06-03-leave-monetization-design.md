# Leave Monetization — Design Spec

**Date:** 2026-06-03

## Overview

Employees can file a request to convert unused leave credits into cash. HR computes the actual payout manually; the system handles the request workflow, credit deduction, and approval chain.

Two distinct removal paths:
- **Leave Cancellation** (`LeaveCancellationController`) — for fully approved leave applications
- **Monetization Delete** — cancel a pending monetization request (restores credits)

---

## Database

### 1. `leave_types` — add column
| Column | Type | Default |
|--------|------|---------|
| `monetizable` | tinyint(1) | 0 |

### 2. `leave_monetizations`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `user_id` | unsignedBigInteger | |
| `leave_type_id` | unsignedBigInteger | FK leave_types |
| `days_requested` | decimal(8,3) | |
| `credits_deducted` | decimal(10,3) | days × 1.25 |
| `status` | enum(pending,approved,rejected) | default pending |
| timestamps | | |

### 3. `leave_monetization_approvals`
Mirrors `leave_application_approvals` structure.
| Column | Type |
|--------|------|
| `id` | bigIncrements |
| `leave_monetization_id` | unsignedBigInteger |
| `approver_id` | unsignedBigInteger |
| `role` | enum(SH, DH, HR) |
| `status` | enum(pending, approved, rejected) |
| `remarks` | text nullable |
| `approved_at` | timestamp nullable |
| `order_no` | unsignedTinyInteger |
| timestamps | |

---

## Credit Flow

| Event | Action |
|-------|--------|
| Filed | Deduct `credits_deducted` from `leave_credits.based_credit` |
| Rejected by any approver | Restore credits, set status = rejected |
| Deleted by employee | Restore credits (only if status = pending), hard-delete |
| Approved by HR (final) | Credits stay deducted |

---

## Backend

### New Files
- `app/Services/LeaveMonetizationService.php`
- `app/Http/Controllers/LeaveMonetizationController.php`
- `app/Http/Controllers/LeaveMonetizationApprovalController.php`

### Routes
```
GET    /leave-monetizations                   LeaveMonetizationController@index
POST   /leave-monetizations                   LeaveMonetizationController@store
DELETE /leave-monetizations/{id}              LeaveMonetizationController@delete
GET    /monetizable-leave-types               LeaveMonetizationController@monetizableTypes
GET    /get-monetization-approvals            LeaveMonetizationApprovalController@getForApproval
POST   /update-monetization-approval-status   LeaveMonetizationApprovalController@updateApprovalStatus
```

---

## Employee UI (`leave_application.blade.php`)

- Bootstrap tabs wrap existing leave card: **Leave Applications** | **Monetizations**
- Credit balance panel stays above tabs (shared)
- Monetizations tab: "+ Monetize" button + history table (Leave Type | Days Requested | Filed Date | Status | Actions)
- Delete action shown only when `is_deletable = true` (status = pending)

### New modal: `modals/monetize_leave.blade.php`
- Leave type dropdown (monetizable types with balance > 0)
- Balance hint: "Available: X days" (updates on type change)
- Days requested input (decimal, max = available days)
- Submit / Close

---

## Approver UI (`leave_approval.blade.php`)

- Bootstrap tabs: **Leave Applications** | **Monetization Requests**
- Monetization tab reuses same filter buttons (Pending / Approved by Stage / All)
- Table: Employee | Leave Type | Days Requested | Filed Date | Actions
- New modal: `modals/monetize_approve.blade.php` — remarks field + Approve / Reject buttons

---

## Scope

| Layer | File | Change |
|-------|------|--------|
| Migration | new | Add `monetizable` to leave_types |
| Migration | new | Create leave_monetizations |
| Migration | new | Create leave_monetization_approvals |
| Service | new | LeaveMonetizationService |
| Controller | new | LeaveMonetizationController |
| Controller | new | LeaveMonetizationApprovalController |
| Routes | web.php | Add 6 new routes |
| View | leave_application.blade.php | Wrap in tabs |
| View | new | modals/monetize_leave.blade.php |
| JS | leave_application.blade.php | Monetization section |
| View | leave_approval.blade.php | Wrap in tabs |
| View | new | modals/monetize_approve.blade.php |
| JS | leave_approval.blade.php | Monetization approval section |
