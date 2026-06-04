# Bulk Leave Credit Assignment — Design Spec

**Date:** 2026-06-04

## Overview

Admin selects multiple employees, specifies one or more leave types with a credit value each, and applies them all at once. Each employee's `based_credit` is incremented by the entered value. A permanent snapshot (batch) is saved for audit and printing.

---

## Database

### `leave_credit_batches`
| Column | Type |
|--------|------|
| `id` | bigIncrements |
| `created_by` | unsignedBigInteger |
| `notes` | text nullable |
| timestamps | |

### `leave_credit_batch_items`
One row per employee × leave type.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `batch_id` | unsignedBigInteger | |
| `user_id` | unsignedBigInteger | |
| `firstname` | string | denormalized |
| `lastname` | string | denormalized |
| `designation_name` | string nullable | |
| `division_name` | string nullable | |
| `section_name` | string nullable | |
| `employee_type` | string nullable | |
| `leave_type_id` | unsignedBigInteger | |
| `leave_type_code` | string | e.g. SL |
| `credits_added` | decimal(10,3) | value admin entered |
| `balance_after` | decimal(10,3) | new balance post-addition |

---

## Credit Logic

`leave_credits.based_credit += credits_added` per employee per leave type.
If no `leave_credits` row exists for that employee + leave type, one is created with `based_credit = credits_added`.

---

## Backend

**Controller:** `LeaveBulkCreditController`
- `getEmployees(Request)` — filtered by division_id, section_id, employee_type
- `store(Request)` — validate, increment credits, save batch + items
- `printBatch($id)` — load batch + items, return print view

**Routes:**
```
GET  /bulk-leave-credits              → page view
GET  /bulk-leave-credits/employees    → getEmployees (JSON)
POST /bulk-leave-credits              → store
GET  /bulk-leave-credits/{id}/print   → printBatch
```

---

## Admin UI

**Filters:** Division | Section | Employee Type → Load button
**Employee table:** checkbox | Name | Division | Section | Type + Select All
**Credits section:** dynamic rows (Leave Type + Credits input) + Add Row
**Submit** → saves batch, shows Print link in success toast

---

## Print View

Columns: Employee | Designation | Division | Section | Employee Type | Credits Added | Balance

Credits Added / Balance cells aggregate all leave types per employee row:
`SL = 3.212, VL = 2.000`

---

## Navigation

New sidebar link under Settings: **Bulk Leave Credits** → `/bulk-leave-credits`
