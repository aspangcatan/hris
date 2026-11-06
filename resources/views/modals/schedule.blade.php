<div class="modal fade premium-modal" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-clock text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Update Schedule</div>
                <input type="hidden" id="selectedEmployeeId">
                <input type="hidden" id="selectedDay">
                <input type="hidden" id="selectedCode">

                <label for="scheduleSelect" class="form-label fw-semibold">Select Schedule:</label>
                <select class="form-select" id="scheduleSelect"></select>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-modal-save" id="btn_save_schedule">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Advance Schedule Builder (#advanceScheduleModal) - theme-v2 tokens, prefix asb- */
    .asb-modal .modal-dialog { max-width: min(1140px, calc(100vw - 2rem)); }
    .asb-modal .modal-body { display: flex; flex-direction: column; gap: 1rem; }
    .asb-month {
        display: inline-flex; align-items: center; gap: 0.375rem; margin-left: auto;
        padding: 0.3rem 0.75rem; border-radius: 999px; background: var(--primary-fixed);
        color: var(--primary); font-size: 0.8125rem; font-weight: 700; white-space: nowrap;
    }

    .asb-card { display: flex; flex-direction: column; height: 100%; padding: 1rem 1.125rem; background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
    .asb-card + .asb-card { margin-top: 1rem; }
    .asb-card-head { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.875rem; }
    .asb-step {
        display: grid; place-items: center; flex-shrink: 0; width: 1.75rem; height: 1.75rem; border-radius: 50%;
        background: var(--primary); color: #fff; font: 700 0.8125rem/1 var(--font-head);
    }
    .asb-card-head h6 { margin: 0; font: 700 0.9375rem/1.4 var(--font-head); color: var(--on-surface); }
    .asb-card-head p { margin: 0.125rem 0 0; font-size: 0.8125rem; color: var(--outline); }

    .asb-label { display: block; margin-bottom: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: var(--on-surface-variant); }

    /* Employees */
    .asb-search { position: relative; }
    .asb-search .bi { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--outline); pointer-events: none; }
    .asb-search .form-control { padding-left: 2.25rem; }
    .asb-emp-bar {
        display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
        margin: 0.75rem 0 0.5rem; font-size: 0.8125rem;
    }
    .asb-emp-bar .form-check { display: flex; align-items: center; gap: 0.5rem; margin: 0; padding: 0; }
    .asb-emp-bar .form-check-input { float: none; margin: 0; }
    .asb-emp-bar .form-check-label { font-weight: 600; color: var(--on-surface) !important; cursor: pointer; }
    .asb-count { padding: 0.15rem 0.6rem; border-radius: 999px; background: var(--surface-low); color: var(--on-surface-variant); font-weight: 600; white-space: nowrap; }
    .asb-count.is-none { background: var(--danger-container); color: var(--on-danger-container); }

    .asb-emp-list { flex: 1; min-height: 14rem; max-height: 26rem; overflow-y: auto; border: 1px solid var(--line); border-radius: 0.75rem; }
    .asb-emp-list table { margin: 0; }
    .asb-emp-list tbody td { padding: 0.5rem 0.75rem; border-color: var(--line); }
    .asb-emp-list .advance-employee-row { cursor: pointer; }
    .asb-emp-list .advance-employee-row:hover td { background: var(--surface-low); }
    .asb-emp-list .advance-employee-row:has(.advance-employee-check:checked) td { background: var(--primary-soft); }
    .asb-emp-list .advance-employee-row td:first-child { width: 2.5rem; text-align: center; }
    .asb-emp-name { font-weight: 600; color: var(--on-surface); line-height: 1.3; }
    .asb-emp-role { font-size: 0.75rem; color: var(--outline); }

    /* Date range */
    .asb-range { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .asb-quick { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.625rem; }
    .asb-chip {
        padding: 0.25rem 0.7rem; border: 1px solid var(--outline-variant); border-radius: 999px; background: var(--surface);
        font-size: 0.75rem; font-weight: 600; color: var(--on-surface-variant);
    }
    .asb-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
    .asb-error { margin-top: 0.5rem; font-size: 0.8125rem; font-weight: 600; color: var(--danger); }
    .asb-error:empty { display: none; }

    /* Weekly pattern */
    .asb-fill {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;
        padding: 0.625rem 0.75rem; border: 1px dashed var(--outline-variant); border-radius: 0.75rem; background: var(--surface-low);
    }
    .asb-fill .form-select { flex: 1 1 12rem; min-width: 0; width: auto; }
    .asb-fill-label { font-size: 0.8125rem; font-weight: 600; color: var(--on-surface); }
    .asb-days { display: grid; grid-template-columns: repeat(auto-fill, minmax(14.5rem, 1fr)); gap: 0.5rem; }
    .asb-day {
        display: flex; align-items: center; gap: 0.625rem; padding: 0.5rem 0.625rem;
        border: 1px solid var(--line); border-radius: 0.75rem; background: var(--surface);
    }
    .asb-day-name { width: 5.5rem; flex-shrink: 0; font-size: 0.875rem; font-weight: 700; color: var(--on-surface); }
    .asb-day-name small { display: block; font-size: 0.6875rem; font-weight: 500; color: var(--outline); }
    .asb-day .form-select { flex: 1; min-width: 0; }
    .asb-day.is-weekend { background: var(--surface-low); }
    .asb-day.is-clear { border-style: dashed; }
    .asb-day.is-clear .form-select { color: var(--outline); }
    .asb-note { display: flex; gap: 0.5rem; margin-top: 0.75rem; font-size: 0.8125rem; color: var(--on-surface-variant); }
    .asb-note .bi { color: var(--on-warning-container); }

    /* Footer summary */
    .asb-footer { justify-content: space-between; }
    .asb-summary { display: flex; flex-wrap: wrap; gap: 0.375rem 1rem; font-size: 0.8125rem; color: var(--on-surface-variant); min-width: 0; }
    .asb-summary strong { color: var(--on-surface); }
    .asb-summary .asb-clear-count strong { color: var(--on-warning-container); }

    @media (max-width: 575.98px) {
        .asb-range { grid-template-columns: 1fr; }
        .asb-month { display: none; }
        .asb-summary { display: none; }
    }
</style>

<div class="modal fade asb-modal" id="advanceScheduleModal" tabindex="-1" aria-labelledby="advanceScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="bi bi-calendar-week"></i></div>
                    <div>
                        <h5 class="modal-title" id="advanceScheduleModalLabel">Advance Schedule Builder</h5>
                        <p class="modal-sub">Give many employees the same weekly schedule in one go.</p>
                    </div>
                </div>
                <span class="asb-month"><i class="bi bi-calendar3"></i><span id="advance_month_label"></span></span>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    {{-- Step 1: employees --}}
                    <div class="col-lg-5">
                        <section class="asb-card">
                            <div class="asb-card-head">
                                <span class="asb-step">1</span>
                                <div>
                                    <h6>Choose employees</h6>
                                    <p>Everyone is selected. Untick anyone who should keep their current schedule.</p>
                                </div>
                            </div>

                            <div class="asb-search">
                                <i class="bi bi-search"></i>
                                <input type="text" id="advance_employee_search" class="form-control" placeholder="Search name or designation" aria-label="Search employee">
                            </div>

                            <div class="asb-emp-bar">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="advance_select_all_employees">
                                    <label class="form-check-label" for="advance_select_all_employees">Select all</label>
                                </div>
                                <span class="asb-count" id="advance_selected_count">0 selected</span>
                            </div>

                            <div class="asb-emp-list">
                                <table class="table align-middle employee-table">
                                    <tbody id="advance_employee_table_body">
                                    <tr><td colspan="3" class="text-center text-muted">No employees loaded yet.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <div class="col-lg-7">
                        {{-- Step 2: dates --}}
                        <section class="asb-card">
                            <div class="asb-card-head">
                                <span class="asb-step">2</span>
                                <div>
                                    <h6>Pick the dates</h6>
                                    <p>Only dates inside this range are changed. It must stay within the month you're viewing.</p>
                                </div>
                            </div>
                            <div class="asb-range">
                                <div>
                                    <label for="advance_start_date" class="asb-label">From</label>
                                    <input type="date" id="advance_start_date" class="form-control range-input" required>
                                </div>
                                <div>
                                    <label for="advance_end_date" class="asb-label">To</label>
                                    <input type="date" id="advance_end_date" class="form-control range-input" required>
                                </div>
                            </div>
                            <div class="asb-quick">
                                <button type="button" class="asb-chip" data-asb-range="month">Whole month</button>
                                <button type="button" class="asb-chip" data-asb-range="today">Today to month end</button>
                                <button type="button" class="asb-chip" data-asb-range="first">1st – 15th</button>
                                <button type="button" class="asb-chip" data-asb-range="second">16th – month end</button>
                            </div>
                            <div id="advance_range_error" class="asb-error range-error"></div>

                             {{-- Step 3: weekly pattern --}}
                        <div class="mt-5">
                            <div class="asb-card-head">
                                <span class="asb-step">3</span>
                                <div>
                                    <h6>Set the weekly pattern</h6>
                                    <p>Pick a schedule for each day of the week. It repeats on every matching date in the range.</p>
                                </div>
                            </div>

                            <div class="asb-fill">
                                <span class="asb-fill-label">Quick fill</span>
                                <select class="form-select form-select-sm" id="advance_quick_schedule" aria-label="Schedule to fill">
                                    <option value="">No schedule (clear)</option>
                                </select>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-asb-fill="weekdays">Mon – Fri</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-asb-fill="weekend">Sat – Sun</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-asb-fill="all">Every day</button>
                            </div>

                            <div class="asb-days">
                                @php
                                    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                                @endphp
                                @foreach($days as $day)
                                    <div class="asb-day {{ in_array($day, ['Saturday','Sunday']) ? 'is-weekend' : '' }}">
                                        <label class="asb-day-name" for="advance_day_{{ strtolower($day) }}">
                                            {{ $day }}
                                            @if(in_array($day, ['Saturday','Sunday']))<small>Weekend</small>@endif
                                        </label>
                                        <select class="form-select form-select-sm advance-schedule-select" id="advance_day_{{ strtolower($day) }}" data-day="{{ $day }}">
                                            <option value="">No schedule (clear)</option>
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                            <div class="asb-note">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span>A day set to <strong>No schedule (clear)</strong> removes any existing schedule on those dates for the selected employees.</span>
                            </div>
                        </section>
                        </section>

                       
                    </div>
                </div>
            </div>

            <div class="modal-footer asb-footer">
                <div class="asb-summary" id="advance_summary"></div>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-save" id="save_advance_schedule" disabled>
                        <i class="bi bi-check2-circle me-1"></i>Apply schedule
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
