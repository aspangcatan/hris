# Leave Balance Guards — Design Spec

**Date:** 2026-06-03  
**Author:** Asnaui Pangcatan

## Problem

1. The leave type dropdown in the filing modal shows all active types regardless of remaining balance, letting users select a type they have no credits for.
2. The backend credit check reads only `based_credit` instead of `based_credit - used_credit`, so manual `used_credit` overrides set by HR are ignored.

## Goal

- The dropdown only shows leave types the user can actually use (balance > 0).
- A pre-submit JS check prevents submitting if hours filed would exceed available credits for any leave type.
- The backend service enforces the same rule using the correct balance (`based_credit - used_credit`).

---

## Changes

### 1. Frontend — Dropdown Filter

**File:** `resources/views/js/leave_application.blade.php`  
**Function:** `buildLeaveTypeOptions()`

Filter `LEAVE_TYPES` before mapping to `<option>` elements:

```js
function buildLeaveTypeOptions(selectedId) {
    return LEAVE_TYPES
        .filter(lt => parseFloat(lt.balance) > 0)
        .map(lt =>
            `<option value="${lt.id}" ${lt.id === selectedId ? 'selected' : ''}>${lt.description}</option>`
        ).join('');
}
```

The credit balance display panel on the main page (`#credit_balances`) is unaffected — all types are still shown there so users can see their zero balances.

---

### 2. Frontend — Pre-submit Guard

**File:** `resources/views/js/leave_application.blade.php`  
**Location:** inside the submit handler, before the AJAX call

Algorithm:
1. Walk every row in `#dates_table_body`, read `leave_type_id` and `hours`.
2. Group rows by `leave_type_id`, sum hours per group.
3. Convert needed hours to credits: `(totalHours / 8) * 1.25`.
4. Look up `balance` from `LEAVE_TYPES` (in memory); convert to max hours: `balance * 8 / 1.25`.
5. If credits needed > balance for any type, block submission and show `toastr.error`:
   - _"Insufficient [Type Name] credits. You have [X] hrs available but are filing [Y] hrs."_

No extra API call required.

---

### 3. Backend — Service Fix

**File:** `app/Services/LeaveApplicationService.php`  
**Methods:** `insertLeaveApplication()` and `updateLeaveApplication()`

Current (wrong):
```php
$available = DB::table('leave_credits')
    ->where('user_id', Auth::id())
    ->where('leave_id', $leaveId)
    ->value('based_credit');
```

Fixed:
```php
$credit    = DB::table('leave_credits')
    ->where('user_id', Auth::id())
    ->where('leave_id', $leaveId)
    ->first(['based_credit', 'used_credit']);
$available = $credit ? ($credit->based_credit - $credit->used_credit) : null;
```

The existing null check and insufficient-credits exception need no other changes — they will now use the correct balance figure.

---

## Scope

| Layer | File | Change |
|-------|------|--------|
| JS dropdown | `resources/views/js/leave_application.blade.php` | Filter `buildLeaveTypeOptions` |
| JS pre-submit | `resources/views/js/leave_application.blade.php` | Add balance check before AJAX call |
| PHP service | `app/Services/LeaveApplicationService.php` | Fix `$available` in insert + update |

No schema changes. No new files.

---

## Credit Conversion Formula

| Direction | Formula |
|-----------|---------|
| Hours → Credits | `(hours / 8) * 1.25` |
| Credits → Hours | `credits * 8 / 1.25` |

(1.25 = `CREDIT_RATE_PER_DAY` constant in the service)
