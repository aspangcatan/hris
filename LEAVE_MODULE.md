# Leave Application Module — Reference Guide

## Stack
- Backend: Laravel 8 / PHP 7.4 (MySQL via `hris` database)
- Frontend: Blade templates + Bootstrap 5 + jQuery + Toastr

---

## Database Tables

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `leave_types` | id, description, code, status, monthly_earned | Leave type definitions |
| `leave_credits` | user_id, leave_id, based_credit, used_credit | Credit balances per employee per type |
| `leave_applications` | id, user_id, commutation\*, status, timestamps | One record per filing (batch or single) |
| `leave_details` | id, leave_application_id, leave_id, detail\*, extra\*, credits_deducted | One row per leave type selected in a filing |
| `leave_date_applied` | id, leave_application_id, date_applied, hours | Dates applied per application |
| `leave_application_approvals` | id, leave_application_id, role, approver_id, order_no, status, remarks, approved_at, timestamps | One approval chain per application |
| `leave_application_logs` | leave_application_id, origin, destination, type, remarks | Audit trail |

\* `commutation`, `detail`, `extra` are all **nullable**.

### Credit Formula
```
Balance        = based_credit - used_credit
Deduct points  = (total_hours / 8) / N     where N = number of leave types selected
```

---

## Normalized Schema Design (implemented May 2025)

When the user selects **N leave types** on one filing:

1. The system creates **one `leave_applications` record** (no leave_id on it).
2. The system creates **N `leave_details` rows** — one per selected leave type — each storing `leave_id`, `detail`, `extra`, and `credits_deducted`.
3. All dates go into `leave_date_applied` once, linked to the single application.
4. One approval chain is created in `leave_application_approvals` linked to that single application.
5. On **rejection**, credits are restored from `leave_details.credits_deducted` per leave type.
6. The leave history shows one row per application with all leave type descriptions joined (`"VL, SL"`).

> Example: 2 days (16 hrs) filed under VL + SL →
> - App #1 (leave_applications): commutation = "Not Requested", status = "pending"
> - Detail #1 (leave_details): leave_id = VL, credits_deducted = 1.0
> - Detail #2 (leave_details): leave_id = SL, credits_deducted = 1.0
> - Dates: 2 rows in leave_date_applied pointing to App #1
> - Approvals: SH → DH → HR chain pointing to App #1

### Migration History
| File | Change |
|------|--------|
| `2026_05_25_000001_improve_leave_applications_schema.php` | Made `detail`, `commutation` nullable; added `group_id` to `leave_applications` |
| `2026_05_25_000002_add_group_id_to_leave_application_approvals.php` | Added `group_id` to `leave_application_approvals` |
| `2026_05_25_000003_restructure_leave_applications.php` | Created `leave_details` table; migrated data; dropped `leave_id`, `detail`, `extra`, `group_id` from `leave_applications`; dropped `group_id` from `leave_application_approvals` |

---

## Key Files

### Backend

| File | Responsibility |
|------|---------------|
| `app/Http/Controllers/LeaveApplicationController.php` | Validates `leave_ids[]` array, delegates to service; `index()` builds combined descriptions from `leave_details` |
| `app/Http/Controllers/LeaveController.php` | `getCreditBalances` — returns active leave types with balance data |
| `app/Services/LeaveApplicationService.php` | Creates one `leave_applications` + N `leave_details` rows per filing; proportional credit deduction |
| `app/Services/LeaveApprovalService.php` | Approval workflow; restores credits from `leave_details` on rejection |

### Frontend

| File | Responsibility |
|------|---------------|
| `resources/views/leave_application.blade.php` | Main page: credit balance cards + leave history table |
| `resources/views/modals/leave.blade.php` | Filing modal + view modal |
| `resources/views/js/leave_application.blade.php` | All JS logic for the leave application page |

---

## API Endpoints (Leave)

| Method | Route Name | Purpose |
|--------|-----------|---------|
| GET | `getCreditBalances` | Fetch all active leave types + balances for logged-in user |
| POST | `insertLeaveApplication` | File a new leave (accepts `leave_ids[]` array) |
| GET | `getLeaveApplications` | Paginated leave history for logged-in user |
| GET | `leave-applications.show` | Single application detail (approval steps + dates) |
| POST | `update/approval_status` | Approve or reject a leave at current step |
| GET | `printLeaveForm` | Print-friendly leave application PDF |

---

## Leave Filing Form — Section Logic

### A. Type of Leave
- Checkboxes populated from `getCreditBalances` API.
- **Only leave types with `balance > 0` are shown** (filter in JS `loadCreditBalance()`).
- Credit Balances card on the main page still shows **all active types** regardless of balance.

### B. Details of Leave
- Groups are **rendered dynamically** from `LEAVE_DETAIL_CONFIG` in JS — there is no hardcoded HTML for detail groups in the Blade file.
- Each group uses its own `name` attribute (matching `groupId`) so radios across groups are independent.
- Groups are shown/hidden via `refreshDetailsVisibility()` based on which leave types are checked in A.
- On submit, details from **all enabled groups** are collected (config-driven loop) and joined with ` | `.

**To add a new leave type with custom details** — add one entry to `LEAVE_DETAIL_CONFIG` in `js/leave_application.blade.php`. No HTML changes required.

| Keyword match | groupId |
|---------------|---------|
| "vacation leave" / "special privilege" | `detail_vacation` |
| "sick leave" | `detail_sick` |
| "special leave benefits for women" | `detail_special_women` |
| "study leave" | `detail_study` |

### C. Date Applied
- Duration options: **4, 8, 10, 12, 16, 24 hours** (default: 8)
- Multiple dates supported; at least one required.

### D. Commutation
- Radio: Not Requested / Requested

---

## Approval Workflow

```
Employee files → SH (order 1) → DH (order 2) → HR (order 3)
```

- Each step must be approved before the next can act.
- Any step can reject. Rejection reads `leave_details.credits_deducted` and restores each leave type's `based_credit`.
- Final HR approval sets `leave_applications.status = 'Approved'`.

### Role Resolution
| Session value | Role |
|---------------|------|
| `access_rights = ADMIN` | Can act as SH |
| `is_section_head = 1` AND `user.section = approver_id` | SH |
| `is_division_head = 1` AND `user.division = approver_id` | DH |
| `access_rights IN ('HR', 'HRH')` | HR |

---

## JS Key Functions

| Function | What it does |
|----------|-------------|
| `init_leave()` | Refreshes balances, populates `LEAVE_TYPES` (balance > 0), renders modal section A |
| `loadCreditBalance()` | Fetches API; renders main-page credit cards (all types); populates `LEAVE_TYPES` (balance > 0 only) |
| `refreshDetailsVisibility()` | Enables detail groups for checked leave types; uses `detail-disabled` CSS class |
| `addRow()` | Adds a date row with hours options 4/8/10/12/16/24 |
| `handleSaveLeave()` | Validates form; collects details from all enabled groups; POSTs `leave_ids[]` + dates |
| `viewApplication(id)` | Loads application detail + approval step statuses into view modal |

---

## Common Gotchas

- **Blade route calls in JS**: All `{{ route('...') }}` calls are declared as string constants at the top of the JS file (`ROUTE_INSERT_LEAVE`, etc.) using double-quoted route names to avoid VS Code false-positive parse errors.
- **`leave_applications` has no `leave_id`** — the leave type(s) are in `leave_details`.
- **No `group_id` anywhere** — each filing is a single `leave_applications` row; multi-type is handled by multiple `leave_details` rows.
- **`getCreditBalances` filters `status = 'active'`** — inactive leave types are excluded from the API entirely.
- **Credit restoration on rejection** reads `leave_details.credits_deducted` — the exact amount deducted at filing time — so partial deductions are always restored precisely.
