# Leave Filing: Add Date Range — Design

## Problem

The leave filing form (`resources/views/modals/leave.blade.php`'s `file_leave_modal`, driven by `resources/views/js/leave_application.blade.php`) only lets an employee add one date row at a time via the "Add Date" button (`addRow()`). Filing a multi-week leave means clicking "Add Date" and picking a date individually for every single working day — tedious and error-prone for anything longer than a few days.

## Goal

Add a second option, "Add Date Range," that generates multiple date rows at once from a start date, end date, leave type, and duration — automatically skipping weekends and declared holidays, since no leave credit should be consumed for a day nobody would be working anyway. The existing single-date "Add Date" flow is untouched; this is purely additive.

## UI

In `resources/views/modals/leave.blade.php`, next to the existing "Add Date" button:

```html
<div class="d-flex align-items-center justify-content-between mt-4">
    <div class="fw-bold">Date applied for leave</div>
    <div>
        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="toggleDateRangePanel()">Add Date Range</button>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">Add Date</button>
    </div>
</div>

<div id="date_range_panel" class="border rounded p-3 mt-2 bg-light d-none">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Leave Type</label>
            <select class="form-control form-control-sm" id="range_leave_type"></select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Start Date</label>
            <input type="date" class="form-control form-control-sm" id="range_start_date">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">End Date</label>
            <input type="date" class="form-control form-control-sm" id="range_end_date">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Hours/Day</label>
            <select class="form-control form-control-sm" id="range_hours">
                <option value="4">4</option>
                <option value="8" selected>8</option>
                <option value="10">10</option>
                <option value="12">12</option>
                <option value="16">16</option>
                <option value="24">24</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-primary w-100" onclick="generateDateRange()">Add</button>
        </div>
    </div>
    <div class="small text-muted mt-2">Weekends and declared holidays are skipped automatically.</div>
</div>
```

The panel is hidden by default (`d-none`), toggled by "Add Date Range." Options for `#range_leave_type` are built from the same `LEAVE_TYPES` array already populated by `loadCreditBalance()`, via the existing `buildLeaveTypeOptions()` helper.

## Behavior (`resources/views/js/leave_application.blade.php`)

**Holiday data:** fetched once per filing session (not on every range generation) from the existing `GET /calendar-events` route (`CalendarController::getHolidays()`, already used by the Calendar module — no backend changes needed). Cached in a module-level array of ISO date strings (`start` field from each event). Fetched lazily on first "Add Date Range" click, not on page load, to avoid an unnecessary request for employees who never use the feature.

**Min-date enforcement:** `#range_start_date` gets the same kind of `min` attribute treatment as individual date rows, but scoped to the range panel's own leave type selection rather than the whole table's (the existing `getMinLeaveDate()` scans every `.date-leave-type` select already in the table, which isn't the right scope here). A small dedicated calculation — look up `days_prior` for the currently-selected `#range_leave_type` in `LEAVE_TYPES`, add that many days to today — sets `#range_start_date`'s `min` whenever `#range_leave_type` changes. `#range_end_date`'s `min` is bound to `#range_start_date`'s current value on change, so an end date before the start can't be picked.

**Generation (`generateDateRange()`):**
1. Validate: leave type selected, start date set, end date set and ≥ start date. If any is missing, show a toastr warning and stop.
2. Walk each calendar day from start to end inclusive.
3. Skip Saturday/Sunday (`date.getDay()` 0 or 6).
4. Skip any day whose ISO date string matches a cached holiday date.
5. For each remaining day, call the existing `addRow(selectedLeaveTypeId)` (already accepts an optional pre-selected leave type) to append a row to `#dates_table_body`, then set that new row's date input to the current day and its hours `<select>` to `#range_hours`'s value.
6. If zero rows were generated (e.g. a weekend-only range, or a range entirely covered by holidays), show a toastr warning ("No working days in that range") and do not touch the table.
7. On success, clear and hide the range panel (`#range_start_date`/`#range_end_date` reset, panel re-hidden) so it's ready for a second range if the employee wants to add another block of dates (e.g. non-contiguous weeks) — the generated rows remain in the table.

Every generated row is a completely ordinary row afterward — same `date-leave-type`/date input/hours `<select>`/Remove button as a row added via "Add Date." An employee can still edit or remove any individual generated row (e.g. changing one day to a half-day) exactly as they can today. No new row state, no locking, no batch-only editing.

## Explicitly out of scope

- No backend changes — `insertLeaveApplication`/`updateLeaveApplication` already accept an array of `{date_applied, hours, leave_type_id}` regardless of how the rows were added client-side.
- No change to `addRow()`, `removeRow()`, or the existing "Add Date" button's behavior.
- No server-side date-range validation — this is a client-side convenience; the existing per-application validation (min 1 date, credit balance checks, attachment requirements, etc.) already runs unchanged against whatever rows exist in the table at submit time.
- No change to how leave type/hours are stored per date — they remain per-row fields in `leave_date_applied`, exactly as today; the range tool is just a bulk way of setting the same per-row values via one action instead of one date at a time.

## Testing

No backend logic changes, so no new PHPUnit feature tests are needed (matches this codebase's existing pattern of having no JS test framework and relying on backend feature tests for business logic — see `docs/superpowers/plans/2026-07-14-per-employee-rate-override.md` Task 5/6 precedent, which used a temporary `assertSee`-based Blade-render check instead of a full JS test). Verification for this feature is a manual browser pass plus a temporary render-check test (added, run, then deleted) confirming the new button/panel markup is present in the rendered filing page.
