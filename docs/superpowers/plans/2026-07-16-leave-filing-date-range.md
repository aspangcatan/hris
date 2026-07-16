# Leave Filing Date Range Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an employee filing a leave application generate multiple date rows at once from a start date, end date, leave type, and duration, instead of clicking "Add Date" and picking each date individually — automatically skipping weekends and declared holidays.

**Architecture:** A new "Add Date Range" button next to the existing "Add Date" button toggles a small inline panel (leave type, start date, end date, hours). Generation is pure client-side JS: it walks each calendar day in the range, skips Sat/Sun and any date matching the existing Calendar module's holiday list (`GET /calendar-events`, fetched once and cached), and calls the *existing* `addRow(selectedLeaveTypeId)` function once per remaining day — so every generated row is a completely ordinary, independently editable row, identical to one added via "Add Date." No backend or database changes.

**Tech Stack:** Laravel 8 Blade + jQuery (no build step, no JS test framework — this codebase has none). Verification is a manual browser pass plus a temporary Blade-render assertion test (added, run, then deleted), matching this repo's established pattern for UI-only changes.

**Reference spec:** `docs/superpowers/specs/2026-07-16-leave-filing-date-range-design.md`

---

### Task 1: Add Date Range button, panel, and generation logic

**Files:**
- Modify: `resources/views/modals/leave.blade.php:48-66` (the `file_leave_modal`'s "Date applied for leave" section)
- Modify: `resources/views/js/leave_application.blade.php` — add `ROUTE_CALENDAR_EVENTS` const (near line 11), add `HOLIDAY_DATES` cache var (near line 28), add new functions after `removeRow()` (currently ending at line 534), and update `clearForm()` (currently lines 904-917)

- [ ] **Step 1: Add the panel markup**

In `resources/views/modals/leave.blade.php`, find (lines 48-66):

```html
                <div class="mt-4" id="leave_form">
                    <!-- Date Applied for Leave -->
                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="fw-bold">Date applied for leave</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">Add Date</button>
                    </div>

                    <table class="table table-bordered table-sm mt-2" id="dates_table">
                        <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Duration (hrs)</th>
                            <th style="width: 5%">Action</th>
                        </tr>
                        </thead>
                        <tbody id="dates_table_body">
                        </tbody>
                    </table>

                    <hr/>
```

Replace with:

```html
                <div class="mt-4" id="leave_form">
                    <!-- Date Applied for Leave -->
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

                    <table class="table table-bordered table-sm mt-2" id="dates_table">
                        <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Duration (hrs)</th>
                            <th style="width: 5%">Action</th>
                        </tr>
                        </thead>
                        <tbody id="dates_table_body">
                        </tbody>
                    </table>

                    <hr/>
```

- [ ] **Step 2: Add the calendar-events route constant and holiday cache variable**

In `resources/views/js/leave_application.blade.php`, find (line 11):

```javascript
    const ROUTE_CREDIT_BAL     = "{{ route('getCreditBalances') }}";
```

Replace with:

```javascript
    const ROUTE_CREDIT_BAL     = "{{ route('getCreditBalances') }}";
    const ROUTE_CALENDAR_EVENTS = "{{ route('calendar.events') }}";
```

Find (line 28):

```javascript
    // Available leave types with balance > 0 — populated by loadCreditBalance()
    const LEAVE_TYPES = [];
```

Replace with:

```javascript
    // Available leave types with balance > 0 — populated by loadCreditBalance()
    const LEAVE_TYPES = [];

    // Holiday dates (YYYY-MM-DD strings), fetched lazily on first "Add Date Range" use
    let HOLIDAY_DATES = null;
```

- [ ] **Step 3: Add the date-range functions**

In `resources/views/js/leave_application.blade.php`, find the end of `removeRow()` (currently lines 528-534):

```javascript
    function removeRow(button) {
        if ($("#dates_table_body tr").length <= 1) {
            toastr.error("Must specify at least one date for leave", "Information");
            return;
        }
        $(button).closest("tr").remove();
    }
```

Immediately after it (before the `/** ------------------------ * Init Leave Types + Balances * ------------------------ */` comment block that currently follows), insert:

```javascript
    /** ------------------------
     *  Add Date Range
     * ------------------------ */
    function toggleDateRangePanel() {
        const $panel = $("#date_range_panel");
        if ($panel.hasClass("d-none")) {
            $("#range_leave_type").html('<option value="">-- Select Type --</option>' + buildLeaveTypeOptions(null));
            $panel.removeClass("d-none");
        } else {
            $panel.addClass("d-none");
        }
    }

    async function loadHolidayDates() {
        if (HOLIDAY_DATES !== null) return HOLIDAY_DATES;
        try {
            const events = await fetchData(ROUTE_CALENDAR_EVENTS);
            HOLIDAY_DATES = events.map(e => e.start);
        } catch {
            HOLIDAY_DATES = [];
        }
        return HOLIDAY_DATES;
    }

    // Minimum allowed start date for the range panel, scoped to its own
    // selected leave type (not the whole table's rows, unlike getMinLeaveDate()).
    function getRangeMinDate() {
        const lid = parseInt($("#range_leave_type").val());
        const lt = LEAVE_TYPES.find(t => t.id === lid);
        const d = new Date();
        d.setDate(d.getDate() + (lt ? lt.days_prior : 0));
        return d.toISOString().split('T')[0];
    }

    $(document).on('change', '#range_leave_type', function () {
        const minDate = getRangeMinDate();
        $("#range_start_date").attr('min', minDate);
        if ($("#range_start_date").val() && $("#range_start_date").val() < minDate) $("#range_start_date").val('');
    });

    $(document).on('change', '#range_start_date', function () {
        $("#range_end_date").attr('min', $(this).val());
    });

    async function generateDateRange() {
        const leaveTypeId = parseInt($("#range_leave_type").val());
        const startVal = $("#range_start_date").val();
        const endVal = $("#range_end_date").val();
        const hours = $("#range_hours").val();

        if (!leaveTypeId) { toastr.error("Please select a leave type for the range", "Required"); return; }
        if (!startVal || !endVal) { toastr.error("Please specify both a start and end date", "Required"); return; }
        if (endVal < startVal) { toastr.error("End date must be on or after the start date", "Required"); return; }

        const holidays = await loadHolidayDates();

        let added = 0;
        const cursor = new Date(startVal + 'T00:00:00');
        const end = new Date(endVal + 'T00:00:00');

        while (cursor <= end) {
            const iso = cursor.toISOString().split('T')[0];
            const day = cursor.getDay();
            const isWeekend = day === 0 || day === 6;
            const isHoliday = holidays.includes(iso);

            if (!isWeekend && !isHoliday) {
                addRow(leaveTypeId);
                const $row = $("#dates_table_body tr:last");
                $row.find("input[type='date']").val(iso);
                $row.find("select[name='leave_hours[]']").val(hours);
                added++;
            }

            cursor.setDate(cursor.getDate() + 1);
        }

        if (added === 0) {
            toastr.error("No working days in that range (all weekends/holidays)", "Nothing to add");
            return;
        }

        $("#range_start_date").val('');
        $("#range_end_date").val('');
        $("#date_range_panel").addClass("d-none");
        toastr.success(`Added ${added} date${added === 1 ? '' : 's'} to the application`, "Success");
    }

```

(Note the trailing blank line before the next comment block — preserve the file's existing spacing convention between function groups.)

- [ ] **Step 4: Reset the range panel when the filing form clears**

In `resources/views/js/leave_application.blade.php`, find `clearForm()` (currently lines 904-917):

```javascript
    function clearForm() {
        editing_leave_id = null;
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#leave_attachment").val('');
        $("#new_files_preview").empty();
        $("#existing_attachments").addClass('d-none');
        $("#existing_attachments_list").empty();
        $("#attachment_section").hide();
        renderDetailGroups();
        refreshCommutationVisibility();
    }
```

Replace with:

```javascript
    function clearForm() {
        editing_leave_id = null;
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#range_leave_type").val('');
        $("#date_range_panel").addClass("d-none");
        $("#leave_attachment").val('');
        $("#new_files_preview").empty();
        $("#existing_attachments").addClass('d-none');
        $("#existing_attachments_list").empty();
        $("#attachment_section").hide();
        renderDetailGroups();
        refreshCommutationVisibility();
    }
```

(`#range_start_date`/`#range_end_date` are already cleared by the existing `$("#leave_form input")...val("")` line above, since they're plain `<input>` elements inside `#leave_form` — only the `<select id="range_leave_type">` and the panel's visibility need explicit handling, since `<select>` isn't matched by the `input` selector.)

- [ ] **Step 5: Verify by rendering the page**

Add a temporary test, run it, then delete it (this repo has no JS test framework; UI-only changes are verified this way per established precedent — see `docs/superpowers/plans/2026-07-14-per-employee-rate-override.md` Task 5):

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TempLeaveDateRangeUiTest extends TestCase
{
    /** @test */
    public function the_leave_filing_page_renders_the_add_date_range_control()
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->app->forgetInstance(\App\Services\PermissionService::class);
        Auth::loginUsingId(1);

        $response = $this->get('/leave_application');
        $response->assertOk();
        $response->assertSee('Add Date Range', false);
        $response->assertSee('date_range_panel', false);
        $response->assertSee('generateDateRange', false);
        $response->assertSee('range_leave_type', false);
    }
}
```

Save it to `tests/Feature/TempLeaveDateRangeUiTest.php`, run:

Run: `php artisan view:clear && php artisan test tests/Feature/TempLeaveDateRangeUiTest.php`
Expected: `Tests: 1 passed`.

Then delete the file: `rm tests/Feature/TempLeaveDateRangeUiTest.php`

- [ ] **Step 6: Manual smoke test**

Since this is a client-side-only feature with real date-math and network-fetch behavior that a Blade-render assertion can't exercise, do a manual pass in the browser: open "File Leave," click "Add Date Range," pick a leave type and a date range spanning a weekend and (if one is configured in the Calendar module) a holiday, click "Add," and confirm: (a) only working-day rows appear, (b) each row is independently editable/removable afterward, (c) the panel clears and hides after a successful add, (d) submitting the application still works normally with the generated rows.

- [ ] **Step 7: Commit**

```bash
git add resources/views/modals/leave.blade.php resources/views/js/leave_application.blade.php
git commit -m "feat: add date-range option to leave filing, skipping weekends and holidays"
```

---

### Task 2: Final check

**Files:** none (verification only)

- [ ] **Step 1: Confirm no stray temp test files remain**

Run: `git status --short`
Expected: no untracked `Temp*Test.php` files under `tests/Feature/`, and no other unexpected modified files (aside from any pre-existing local-only files already present before this work started).

- [ ] **Step 2: Run the existing leave-related backend regression suite**

Run: `php artisan test tests/Feature/LeaveDateDisapprovalTest.php`
Expected: all tests still pass — this feature makes no backend changes, so this is a pure safety check that nothing was accidentally touched outside the two frontend files.
