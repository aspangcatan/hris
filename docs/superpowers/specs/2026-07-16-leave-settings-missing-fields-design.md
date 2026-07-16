# Leave Settings Missing Fields — Design

**Goal:** The Leave Settings module (`settings/leave_management.blade.php` + its modal/JS/controller) only lets an admin set `description`, `code`, `status`, `monthly_earned`, and `monetizable` on a leave type. Three columns that already exist live in the `leave_types` table — `days_prior`, `commutation`, `default_credit` — are readable elsewhere in the app but have no admin UI to set them; they can currently only be changed by direct DB edit.

**Confirmed live DB schema** (`Schema::getColumnListing('leave_types')`): `code`, `commutation`, `days_prior`, `default_credit`, `description`, `id`, `monetizable`, `monthly_earned`, `status`. No migration is needed — the columns already exist. (Note: the migration file `database/migrations/2026_07_08_120726_create_leave_types_table.php` names the column `monetize`, but the live DB column is actually `monetizable`, matching the existing controller/JS code — the migration file is stale/inaccurate for that one column, not a bug in the app.)

**Field semantics** (established from existing consumers):
- `days_prior` (int, nullable) — minimum number of days in advance a leave date of this type must be filed. Read in `LeaveController::getCreditBalances`, `LeaveApplicationService`, and client-side in `leave_application.blade.php` (`getMinLeaveDate()`, `getRangeMinDate()`) to compute the earliest selectable date.
- `commutation` (int 0/1, nullable) — whether selecting this leave type auto-checks "commutation requested" on the leave filing form. Read in `LeaveController::getCreditBalances` and `leave_application.blade.php`'s `refreshCommutationVisibility()`.
- `default_credit` (decimal(10,3), nullable) — not consumed anywhere else in the codebase today. Included per user decision, likely intended for a future bulk-credits default.

## Changes

**`resources/views/modals/leave_management.blade.php`** — add three `form-floating` fields after the existing Monetizable field:
- Days Prior: `<input type="number" id="days_prior" min="0" step="1">`
- Commutation: `<select id="commutation">` with the same Yes(1)/No(0) options as Monetizable
- Default Credit: `<input type="number" id="default_credit" min="0" step="0.001">`

**`resources/views/settings/leave_management.blade.php`** — add three `<th>` columns ("Days Prior", "Commutation", "Default Credit") to the table header, positioned between "Monetizable" and the action-icon columns.

**`app/Http/Controllers/LeaveController.php` — `insertUpdateLeaveType()`** — extend the `updateOrInsert` array:
```php
'days_prior'      => $request->days_prior ?? 0,
'commutation'     => $request->commutation ?? 0,
'default_credit'  => $request->default_credit,
```

**`resources/views/js/leave_management.blade.php`**:
- `store()`: read `#days_prior`, `#commutation`, `#default_credit` and include them in the PUT payload.
- `editLeave(id)`: populate the three fields from the row's `data-value` attributes.
- `getLeaveTypes()`: render three new `<td data-value="...">` cells (Commutation rendered as Yes/No label, like Monetizable; Days Prior and Default Credit rendered as their raw numeric value, defaulting to 0 when null).
- `$("#btn_add_leave").click(...)`: reset the three fields to defaults (`days_prior` → `0`, `commutation` → `"0"`, `default_credit` → `""`) alongside the existing resets.

## Testing

No backend logic changes beyond one controller method's insert/update payload — verify by:
1. Temporary Blade-render assertion test (repo convention) confirming the new field IDs and labels appear on the settings page.
2. Manual smoke test: add a new leave type with all fields set, confirm it saves and reloads correctly; edit an existing leave type and confirm the new fields populate and persist; confirm `days_prior`/`commutation` continue to drive leave-filing behavior correctly for a type edited through the new UI (regression check against existing consumers).
