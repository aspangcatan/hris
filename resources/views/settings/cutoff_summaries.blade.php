@extends('master')
@section('content')
    @include('layouts.saln_styles')
    <style>
        /* The type-warning modal opens on top of the Create Cutoff modal. */
        .cutoff-stacked-modal { z-index: 1065; }
        .modal-backdrop.cutoff-stacked-backdrop { z-index: 1060; }

        /* Cutoff modals use the global theme's modal look (white header with an
           icon tile, light body, btn-modal-close / btn-modal-save). Only the
           Create Cutoff steps and its employee table are styled here. */
        /* ---- Cutoff list page ---- */
        .cutoff-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .cutoff-toolbar .cutoff-search {
            flex: 1 1 18rem;
            max-width: 28rem;
        }

        .cutoff-status-tabs {
            display: inline-flex;
            gap: 0.25rem;
            padding: 0.25rem;
            border-radius: 0.75rem;
            background: var(--surface-low, #f2f3ff);
            border: 1px solid var(--line, #e6e8f2);
        }

        .cutoff-status-tabs button {
            border: 0;
            background: transparent;
            padding: 0.35rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--on-surface-variant, #444651);
        }

        .cutoff-status-tabs button.active {
            background: var(--surface, #fff);
            color: var(--primary, #00236f);
            box-shadow: 0 1px 2px rgba(19, 27, 46, 0.08);
        }

        .cutoff-list-count {
            margin-left: auto;
            font-size: 0.8125rem;
            color: var(--outline, #757682);
        }

        .cutoff-list-table thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--on-surface-variant, #444651);
            background: var(--surface-low, #f2f3ff);
            white-space: nowrap;
        }

        .cutoff-list-table td {
            vertical-align: middle;
        }

        .cutoff-list-title {
            font-weight: 600;
            color: var(--on-surface, #131b2e);
        }

        .cutoff-list-sub {
            font-size: 0.78rem;
            color: var(--outline, #757682);
        }

        .cutoff-row-actions {
            display: inline-flex;
            gap: 0.375rem;
        }

        .cutoff-step {
            background: var(--surface, #fff);
            border: 1px solid var(--line, #e6e8f2);
            border-radius: var(--radius, 0.875rem);
            padding: 1rem 1.125rem 1.125rem;
            margin-bottom: 1rem;
        }

        .cutoff-step-head {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.875rem;
        }

        .cutoff-step-no {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 999px;
            background: var(--primary-fixed, #dce1ff);
            color: var(--primary, #00236f);
            font-weight: 700;
            font-size: 0.8125rem;
        }

        .cutoff-step-title {
            font-weight: 700;
            color: var(--on-surface, #131b2e);
            line-height: 1.2;
        }

        .cutoff-step-sub {
            font-size: 0.8125rem;
            color: var(--outline, #757682);
        }

        .cutoff-modal .form-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--on-surface-variant, #444651);
            margin-bottom: 0.3rem;
        }

        .cutoff-count {
            flex-shrink: 0;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: var(--surface-low, #f2f3ff);
            color: var(--primary, #00236f);
            font-size: 0.8125rem;
            font-weight: 600;
        }

        .cutoff-search {
            position: relative;
        }

        .cutoff-search i {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            color: var(--outline, #757682);
            pointer-events: none;
        }

        .cutoff-search .form-control {
            padding-left: 2.25rem;
        }

        .cutoff-type-filter .dropdown-menu {
            max-height: 16rem;
            overflow-y: auto;
        }

        .cutoff-type-filter .form-check {
            padding: 0.3rem 0.5rem 0.3rem 2rem;
            border-radius: 0.5rem;
            margin: 0;
        }

        .cutoff-type-filter .form-check:hover {
            background: var(--surface-low, #f2f3ff);
        }

        .cutoff-type-filter .form-check-label {
            width: 100%;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .cutoff-steps .cutoff-step {
            height: 100%;
            margin-bottom: 0;
        }

        .cutoff-period-note {
            display: flex;
            gap: 0.5rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.625rem;
            background: var(--surface-low, #f2f3ff);
            color: var(--on-surface-variant, #444651);
            font-size: 0.8125rem;
            line-height: 1.35;
        }

        .cutoff-period-note i {
            color: var(--primary, #00236f);
            margin-top: 0.1rem;
        }

        /* Employees card: the table takes the room left in the dialog. */
        @media (min-width: 992px) {
            .cutoff-step-period {
                position: sticky;
                top: 0;
                height: auto !important;
            }
        }

        .cutoff-board .cutoff-table-shell {
            max-height: none;
        }

        .cutoff-table-shell {
            max-height: 58vh;
            overflow-y: auto;
            border: 1px solid var(--line, #e6e8f2);
            border-radius: 0.75rem;
        }

        .cutoff-table-shell thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--surface-low, #f2f3ff);
            color: var(--on-surface-variant, #444651);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--line, #e6e8f2);
        }

        .cutoff-table-shell tbody td {
            font-size: 0.875rem;
        }

        .cutoff-table-shell tbody tr:has(.cutoff-employee:checked) {
            background: var(--primary-soft, #eef0ff);
        }

        @media (max-width: 575.98px) {
            .cutoff-step-head { flex-wrap: wrap; }
            .cutoff-count { margin-left: 2.5rem !important; }
        }
    </style>

    <div class="page-head">
        <div>
            <h1>Cutoff Summary</h1>
            <p class="page-sub">Generate attendance totals per period. Review a draft, then finalize it so payroll and earned credits can use it.</p>
        </div>
        @permission('payroll.settings')
        <button type="button" class="btn btn-primary" id="btn_create_cutoff">
            <i class="bi bi-plus-lg me-1"></i> Create cutoff
        </button>
        @endpermission
    </div>

    <div class="cutoff-board">
        <div class="cutoff-toolbar">
            <div class="cutoff-search">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="cutoff_title_filter" placeholder="Search cutoff title">
            </div>
            <div class="cutoff-status-tabs" role="group" aria-label="Filter by status">
                <button type="button" class="active" data-status="">All</button>
                <button type="button" data-status="draft">Draft</button>
                <button type="button" data-status="for_review">For review</button>
                <button type="button" data-status="finalized">Finalized</button>
                <button type="button" data-status="closed">Closed</button>
            </div>
            <span class="cutoff-list-count" id="cutoff_list_count"></span>
        </div>

        <div class="cutoff-table-shell table-responsive">
            <table class="table align-middle mb-0 cutoff-list-table">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Period</th>
                    <th class="text-center">Employees</th>
                    <th class="text-center">Status</th>
                    <th class="text-end" style="width: 1%"></th>
                </tr>
                </thead>
                <tbody id="cutoff_period_body">
                <tr>
                    <td colspan="5" class="text-muted text-center py-4">No cutoff summaries found.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade cutoff-modal" id="create_cutoff_modal" tabindex="-1" aria-labelledby="createCutoffLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-calendar-range"></i></div>
                        <div>
                            <h5 class="modal-title" id="createCutoffLabel">Create Cutoff</h5>
                            <p class="modal-sub">Total each employee's attendance for a period. It starts as a draft you can review before finalizing.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" id="cutoff_feedback" role="alert"></div>

                    {{-- Period (narrow, left) and Employees (wide, right) side by side;
                         stacked on small screens. --}}
                    <div class="row g-3 cutoff-steps">
                    <div class="col-lg-4">
                    {{-- Step 1: period --}}
                    <section class="cutoff-step cutoff-step-period">
                        <div class="cutoff-step-head">
                            <span class="cutoff-step-no">1</span>
                            <div>
                                <div class="cutoff-step-title">Period</div>
                                <div class="cutoff-step-sub" id="cutoff_period_hint">Dates within the last two months only.</div>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="cutoff_title">Title</label>
                                <input type="text" class="form-control" id="cutoff_title" placeholder="e.g. October 1-31, 2026 HOPSS - IMISS">
                            </div>
                            <div class="col-6 col-lg-12">
                                <label class="form-label" for="cutoff_modal_start_date">Start date</label>
                                <input type="date" class="form-control" id="cutoff_modal_start_date">
                            </div>
                            <div class="col-6 col-lg-12">
                                <label class="form-label" for="cutoff_modal_end_date">End date</label>
                                <input type="date" class="form-control" id="cutoff_modal_end_date">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cutoff_payroll_date">Payroll date <span class="text-muted">(optional)</span></label>
                                <input type="date" class="form-control" id="cutoff_payroll_date">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cutoff_remarks">Remarks <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" id="cutoff_remarks" rows="2" maxlength="1000"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="cutoff-period-note">
                                    <i class="bi bi-info-circle"></i>
                                    <span>Prefilled with the whole of last month. Change the dates to pick the month before, a half-month or an early exit.</span>
                                </div>
                            </div>
                        </div>
                    </section>
                    </div>

                    <div class="col-lg-8">
                    {{-- Step 2: employees --}}
                    <section class="cutoff-step cutoff-step-employees">
                        <div class="cutoff-step-head">
                            <span class="cutoff-step-no">2</span>
                            <div>
                                <div class="cutoff-step-title">Employees</div>
                                <div class="cutoff-step-sub">Load an office, then tick who to include. Employees who left before this period are left out.</div>
                            </div>
                            <span class="cutoff-count ms-auto" id="cutoff_selected_count">0 selected</span>
                        </div>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="cutoff_division">Division</label>
                                <select class="form-select" id="cutoff_division"></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="cutoff_section">Section</label>
                                <select class="form-select" id="cutoff_section"></select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-primary w-100" id="btn_load_cutoff_employees">
                                    <i class="bi bi-people me-1"></i> Load employees
                                </button>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-8">
                                <div class="cutoff-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" id="cutoff_search_name" placeholder="Search loaded employees by name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                {{-- Multi-select: tick one or more types; nothing ticked = all types. --}}
                                <div class="dropdown cutoff-type-filter">
                                    <button type="button" class="form-select text-start" id="cutoff_type_toggle"
                                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" disabled>
                                        <span id="cutoff_type_label">All employee types</span>
                                    </button>
                                    <div class="dropdown-menu w-100 p-2" aria-labelledby="cutoff_type_toggle">
                                        <div class="d-flex justify-content-between px-1 pb-2 mb-1 border-bottom">
                                            <button type="button" class="btn btn-link btn-sm p-0" id="cutoff_type_all">Select all</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" id="cutoff_type_clear">Clear</button>
                                        </div>
                                        <div id="cutoff_type_options"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="cutoff-table-shell table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th style="width: 3rem" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="cutoff_select_all" title="Select all">
                                    </th>
                                    <th>Employee</th>
                                    <th>Designation</th>
                                    <th>Employee type</th>
                                </tr>
                                </thead>
                                <tbody id="cutoff_employee_body">
                                <tr>
                                    <td colspan="4" class="text-muted text-center">No employees loaded.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-modal-save" id="btn_save_cutoff">
                        <i class="bi bi-gear-wide-connected me-1"></i>Generate cutoff
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Shown over the Create Cutoff modal when the selection includes non-Permanent employees. --}}
    <div class="modal fade cutoff-modal cutoff-stacked-modal" id="cutoff_type_warning_modal" tabindex="-1"
         aria-labelledby="cutoffTypeWarningLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="cutoffTypeWarningLabel">Check the selected employees</h4>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Some selected employees are not <strong>Permanent</strong>:</p>
                    <div id="cutoff_type_warning_list"></div>
                    <p class="small text-muted mb-0 mt-2" id="cutoff_type_warning_note"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" id="btn_type_warning_back">Go back</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn_type_warning_keep">Keep &amp; generate</button>
                    <button type="button" class="btn btn-modal-save" id="btn_type_warning_remove">Remove &amp; generate</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade cutoff-modal" id="cutoff_detail_modal" tabindex="-1" aria-labelledby="cutoffDetailLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title" id="cutoffDetailLabel">Cutoff Summary Details</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="fw-semibold" id="cutoff_detail_title">Title</div>
                            <div class="small text-muted">
                                <span id="cutoff_detail_start">-</span> to <span id="cutoff_detail_end">-</span>
                            </div>
                            <div class="small text-muted" id="cutoff_detail_settings"></div>
                        </div>
                        <div class="text-end">
                            <div id="cutoff_detail_status"></div>
                            <div class="small text-muted" id="cutoff_detail_status_note"></div>
                        </div>
                    </div>
                    {{-- Draft: what to check before finalizing. Finalized/closed: why it can't be reopened. --}}
                    <div class="alert d-none" id="cutoff_precheck_panel" role="alert"></div>
                    {{-- Snapshot generation result: processed, excluded, items to review. --}}
                    <div class="d-none mb-3" id="cutoff_snapshot_panel"></div>
                    {{-- Leave changed after finalizing, and changes carried in from earlier cutoffs. --}}
                    <div class="d-none mb-3" id="cutoff_late_changes"></div>
                    <div class="table-responsive">
                        <table class="table align-middle text-center saln-table-premium">
                            <thead>
                            <tr>
                                <th>Employee</th>
                                <th title="The employee's own work days in this cutoff (rest days and holidays excluded)">Scheduled Days</th>
                                <th>Days Present</th>
                                <th>Days Absent</th>
                                <th>Late (mins)</th>
                                <th>Undertime (mins)</th>
                                <th>Hospital Orders</th>
                                <th>Overtime (mins)</th>
                                <th title="Personal pass slip minutes inside working hours - deducted from salary together with late/undertime">Personal Pass Slips (mins)</th>
                                <th>Approved Leave</th>
                                <th>LWOP Days</th>
                                <th title="What was saved for this employee, and anything to review">Snapshot</th>
                            </tr>
                            </thead>
                            <tbody id="cutoff_detail_body">
                            <tr>
                                <td colspan="12" class="text-muted text-center">No cutoff summaries found.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="btn_return_draft"
                            title="Send this cutoff back to draft so source records can be corrected">
                        <span class="btn-label"><i class="bi bi-arrow-counterclockwise"></i> Send back to draft</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-primary d-none" id="btn_generate_snapshot"
                            title="Copy this period's employee, attendance, leave and pay data into the cutoff">
                        <span class="btn-label"><i class="bi bi-camera"></i> Generate snapshot</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-outline-danger d-none" id="btn_reopen_cutoff"
                            title="Send this cutoff back to draft so it can be regenerated">
                        <i class="bi bi-unlock"></i> Reopen
                    </button>
                    <button type="button" class="btn btn-primary d-none" id="btn_finalize_cutoff"
                            title="Lock this cutoff so earned credits and payroll can use it">
                        <i class="bi bi-lock"></i> Finalize
                    </button>
                    <a class="btn btn-outline-primary d-none" id="btn_open_leave_credits" href="{{ route('leave_credits_earned') }}"
                       title="Earned credits are posted per month from finalized cutoffs">
                        <i class="bi bi-calendar-check"></i> Leave Credits Earned
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- One employee's saved snapshot. Opens over the details modal. --}}
    <div class="modal fade cutoff-modal cutoff-stacked-modal" id="cutoff_employee_modal" tabindex="-1"
         aria-labelledby="cutoffEmployeeLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h4 class="modal-title" id="cutoffEmployeeLabel">Employee Snapshot</h4>
                        <div class="small text-muted" id="cutoff_employee_sub"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cutoff_employee_items"></div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#snap_attendance" type="button" role="tab">Attendance</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#snap_leave" type="button" role="tab">Leave &amp; LWOP</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#snap_credits" type="button" role="tab">Earned Credits</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#snap_payroll" type="button" role="tab">Payroll</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="snap_attendance" role="tabpanel"></div>
                        <div class="tab-pane fade" id="snap_leave" role="tabpanel"></div>
                        <div class="tab-pane fade" id="snap_credits" role="tabpanel"></div>
                        <div class="tab-pane fade" id="snap_payroll" role="tabpanel"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Finalize: pre-check list + confirmation. Opens over the details modal. --}}
    <div class="modal fade cutoff-modal cutoff-stacked-modal" id="cutoff_finalize_modal" tabindex="-1"
         aria-labelledby="cutoffFinalizeLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="cutoffFinalizeLabel">Finalize Cutoff</h4>
                </div>
                <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                    <p class="mb-2">
                        Finalizing locks <strong id="cutoff_finalize_count">0</strong> employee row(s). The cutoff can't be
                        regenerated after this, and earned credits and payroll will read it as it is now.
                    </p>
                    <div id="cutoff_finalize_issues"></div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="cutoff_finalize_confirm">
                        <label class="form-check-label" for="cutoff_finalize_confirm" id="cutoff_finalize_confirm_label">
                            I reviewed this cutoff and it is ready to finalize.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btn_confirm_finalize" disabled>
                        <span class="btn-label">Finalize</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reopen: reason required; only while no payroll or posted credits use the cutoff. --}}
    <div class="modal fade cutoff-modal cutoff-stacked-modal" id="cutoff_reopen_modal" tabindex="-1"
         aria-labelledby="cutoffReopenLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="cutoffReopenLabel">Reopen Cutoff</h4>
                </div>
                <div class="modal-body">
                    <p class="mb-2">The cutoff goes back to draft and its rows are unlocked so it can be regenerated. The reason is kept in the cutoff's history.</p>
                    <label class="saln-info-label mb-1" for="cutoff_reopen_reason">Reason</label>
                    <textarea class="form-control" id="cutoff_reopen_reason" rows="3" maxlength="1000"
                              placeholder="e.g. Sick leave for Oct 14 was approved after finalizing"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btn_confirm_reopen">
                        <span class="btn-label">Reopen</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress of a long run (cutoff generation / snapshot), sent to the server in batches --}}
    <div class="modal fade cutoff-modal cutoff-stacked-modal" id="cutoff_progress_modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <h5 class="modal-title" id="cutoff_progress_title">Generating...</h5>
                            <p class="modal-sub">Keep this page open until it finishes.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between small mb-1">
                        <span id="cutoff_progress_step">Starting...</span>
                        <span class="fw-semibold" id="cutoff_progress_count">0 / 0</span>
                    </div>
                    <div class="progress" style="height: 12px;" role="progressbar" aria-label="Progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="cutoff_progress_bar" style="width: 0%"></div>
                    </div>
                    <div class="small text-muted mt-2" id="cutoff_progress_eta"></div>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
    <script>
        let cutoffPeriods = [];
        let currentCutoffDetail = {id: null, start: null, end: null, title: null};

        // -------------------------------------------------------------------
        // Long runs go to the server in batches, so no single request can time
        // out (about 0.5-0.9 s per employee). HR keeps the page open meanwhile.
        // -------------------------------------------------------------------
        const CUTOFF_BATCH_SIZE = 25;
        let cutoffRunActive = false;

        window.addEventListener("beforeunload", function (e) {
            if (cutoffRunActive) {
                e.preventDefault();
                e.returnValue = "";
            }
        });

        async function cutoffPostJson(url, method, body) {
            const res = await fetch(url, {
                method: method || "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}"},
                body: JSON.stringify(body || {}),
            });
            const isJson = (res.headers.get("content-type") || "").includes("application/json");
            const data = isJson ? await res.json() : null;
            if (!res.ok || !data || data.success === false) {
                const message = (data && (data.message || data.error))
                    || (res.status === 403 ? "You do not have permission to do this." : "")
                    || (res.status === 504 || res.status === 524 ? "The server took too long to answer." : "")
                    || `Request failed (${res.status}).`;
                const err = new Error(message);
                err.data = data;
                throw err;
            }
            return data;
        }

        function cutoffChunks(items, size) {
            const chunks = [];
            for (let i = 0; i < items.length; i += size) chunks.push(items.slice(i, i + size));
            return chunks;
        }

        function showCutoffProgress(title, total) {
            cutoffRunActive = true;
            $("#cutoff_progress_title").text(title);
            updateCutoffProgress(0, total, "Starting...", null);
            bootstrap.Modal.getOrCreateInstance(document.getElementById("cutoff_progress_modal")).show();
        }

        function updateCutoffProgress(done, total, step, startedAt) {
            const pct = total ? Math.round(done / total * 100) : 0;
            $("#cutoff_progress_bar").css("width", pct + "%").text(pct >= 8 ? pct + "%" : "");
            $("#cutoff_progress_count").text(`${done} / ${total}`);
            $("#cutoff_progress_step").text(step);
            let eta = "";
            if (startedAt && done > 0 && done < total) {
                const secondsLeft = Math.round((Date.now() - startedAt) / done * (total - done) / 1000);
                eta = secondsLeft >= 60 ? `About ${Math.ceil(secondsLeft / 60)} minute(s) left` : `About ${secondsLeft} second(s) left`;
            }
            $("#cutoff_progress_eta").text(eta);
        }

        function hideCutoffProgress() {
            cutoffRunActive = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById("cutoff_progress_modal")).hide();
        }

        /** Sends ids in batches; send(batch) returns how many it processed. Throws with .done on failure. */
        async function runCutoffBatches(ids, label, send) {
            const startedAt = Date.now();
            let done = 0;
            for (const batch of cutoffChunks(ids, CUTOFF_BATCH_SIZE)) {
                updateCutoffProgress(done, ids.length, label, startedAt);
                try {
                    const processed = await send(batch);
                    done += Number.isFinite(processed) ? Math.min(processed, batch.length) : batch.length;
                } catch (err) {
                    err.done = done;
                    throw err;
                }
            }
            updateCutoffProgress(ids.length, ids.length, label, startedAt);
            return done;
        }
        $(document).ready(function () {
            loadCutoffPeriods();

            $("#btn_create_cutoff").click(function () {
                resetCutoffFeedback();
                setCutoffModalMode(null);
                resetCreateCutoffForm();
                $("#create_cutoff_modal").modal("show");
            });

            $("#btn_load_cutoff_employees").click(function () {
                loadCutoffEmployees();
            });

            $("#cutoff_search_name").on("input", function () {
                filterCutoffEmployees();
            });

            $("#cutoff_select_all").change(function () {
                const checked = $(this).is(":checked");
                $("#cutoff_employee_body tr:visible .cutoff-employee").prop("checked", checked);
                updateCutoffSelectedCount();
            });

            $(document).on("change", "#cutoff_employee_body .cutoff-employee", updateCutoffSelectedCount);

            $("#btn_save_cutoff").click(function () {
                createCutoffSummaries();
            });

            loadDivision();
        });

        function loadCutoffPeriods() {
            fetch(`{{ route('getCutoffPeriods') }}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    cutoffPeriods = data.data || [];
                    renderCutoffPeriods();
                })
                .catch(err => console.error(err));
        }

        function renderCutoffPeriods() {
            const $tbody = $("#cutoff_period_body");
            $tbody.empty();

            if (!cutoffPeriods.length) {
                $tbody.append("<tr><td colspan='5' class='text-muted text-center py-4'>No cutoff summaries yet. Click Create cutoff to start.</td></tr>");
                $("#cutoff_list_count").text("");
                return;
            }

            cutoffPeriods.forEach(item => {
                const title = item.title || `Cutoff ${item.period_start} - ${item.period_end}`;
                const status = (item.status || "draft").toLowerCase();
                const safeTitle = escapeHtml(title);
                const editButton = status === "draft" && CUTOFF_CAN_MANAGE
                    ? `<button type="button" class="btn btn-action btn-edit btn-edit-cutoff" data-id="${item.id}" title="Edit this draft cutoff">
                           <i class="bi bi-pencil-square"></i>
                       </button>`
                    : "";
                const row = `
                    <tr data-title="${safeTitle.toLowerCase()}" data-status="${status}">
                        <td>
                            <div class="cutoff-list-title">${safeTitle}</div>
                        </td>
                        <td>
                            <div>${formatPeriodRange(item.period_start, item.period_end)}</div>
                            <div class="cutoff-list-sub">${periodDayCount(item.period_start, item.period_end)}</div>
                        </td>
                        <td class="text-center">${Number(item.employees || 0)}</td>
                        <td class="text-center">${cutoffStatusBadge(status)}</td>
                        <td class="text-end">
                            <div class="cutoff-row-actions">
                                ${editButton}
                                <button type="button" class="btn btn-action btn-view btn-view-cutoff"
                                        data-id="${item.id}" data-start="${item.period_start}" data-end="${item.period_end}" data-title="${safeTitle}"
                                        title="View cutoff summary">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                $tbody.append(row);
            });
            applyCutoffListFilters();
        }

        function formatPeriodRange(start, end) {
            const s = new Date(`${start}T00:00:00`);
            const e = new Date(`${end}T00:00:00`);
            if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) {
                return `${escapeHtml(start)} to ${escapeHtml(end)}`;
            }
            const month = s.toLocaleDateString("en-US", {month: "short"});
            return s.getMonth() === e.getMonth() && s.getFullYear() === e.getFullYear()
                ? `${month} ${s.getDate()}–${e.getDate()}, ${s.getFullYear()}`
                : `${formatDisplayDate(start)} – ${formatDisplayDate(end)}`;
        }

        function periodDayCount(start, end) {
            const days = Math.round((new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) / 86400000) + 1;
            return Number.isFinite(days) && days > 0 ? `${days} day${days === 1 ? "" : "s"}` : "";
        }

        let cutoffStatusFilter = "";

        function applyCutoffListFilters() {
            const value = ($("#cutoff_title_filter").val() || "").toLowerCase();
            let shown = 0;
            $("#cutoff_period_body tr[data-title]").each(function () {
                const title = ($(this).data("title") || "").toString();
                const status = ($(this).data("status") || "").toString();
                const show = (!value || title.includes(value)) && (!cutoffStatusFilter || status === cutoffStatusFilter);
                $(this).toggle(show);
                shown += show ? 1 : 0;
            });
            const total = $("#cutoff_period_body tr[data-title]").length;
            $("#cutoff_list_count").text(total ? `${shown} of ${total} cutoff${total === 1 ? "" : "s"}` : "");
        }

        $(document).on("click", ".cutoff-status-tabs button", function () {
            $(".cutoff-status-tabs button").removeClass("active");
            $(this).addClass("active");
            cutoffStatusFilter = $(this).data("status") || "";
            applyCutoffListFilters();
        });

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}")
                .then(response => response.json())
                .then(data => {
                    $("#cutoff_division").empty().append("<option value=''>Select division</option>");
                    $("#cutoff_section").empty().append("<option value=''>Select section</option>");
                    data.forEach(it => {
                        $("#cutoff_division").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                })
                .catch(err => console.error(err));
        }

        $("#cutoff_division").change(function () {
            const division = $("#cutoff_division").val();
            $("#cutoff_section").empty().append("<option value=''>Select section</option>");
            if (!division) {
                return;
            }
            fetch("{{ route('getSectionList') }}?division=" + division)
                .then(response => response.json())
                .then(data => {
                    data.forEach(it => {
                        $("#cutoff_section").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                })
                .catch(err => console.error(err));
        });

        function loadCutoffEmployees() {
            const division = $("#cutoff_division").val();
            const section = $("#cutoff_section").val();
            if (!division) {
                toastr.error("Please select a division first.", "Validation");
                return;
            }

            fetch(`{{ route('getEmployeeByDivisionSection') }}?division=${division}&section=${section}`)
                .then(response => response.json())
                .then(data => {
                    // Keep whoever is already ticked (e.g. a cutoff being edited, or an
                    // earlier office) so loading another office only adds people.
                    const kept = cutoffCheckedEmployees();
                    const loaded = data.results || [];
                    const loadedIds = new Set(loaded.map(emp => String(emp.user_id)));
                    renderCutoffEmployees(
                        kept.filter(emp => !loadedIds.has(String(emp.user_id))).concat(loaded),
                        new Set(kept.map(emp => String(emp.user_id)))
                    );
                    if (!loaded.length) {
                        toastr.info("No employees found in that office.", "Information");
                    }
                })
                .catch(err => console.error(err));
        }

        // The ticked rows, in the same shape as the employee list endpoint.
        function cutoffCheckedEmployees() {
            return $("#cutoff_employee_body .cutoff-employee:checked").map(function () {
                const $row = $(this).closest("tr");
                return {
                    user_id: $(this).val(),
                    full_name: $(this).data("name"),
                    designation: $row.attr("data-designation") || null,
                    employee_type: $(this).data("type") || "",
                };
            }).get();
        }

        function cutoffEmployeeName(emp) {
            if (emp.full_name) {
                return emp.full_name;
            }
            const middleInitial = emp.mname ? emp.mname.charAt(0) + '.' : '';
            const suffix = emp.suffix ? ` ${emp.suffix}` : '';
            return formatEmployeeName(`${emp.lname || ""}, ${emp.fname || ""} ${middleInitial}${suffix}`);
        }

        function renderCutoffEmployees(employees, checkedIds) {
            const $tbody = $("#cutoff_employee_body").empty();
            $("#cutoff_select_all").prop("checked", false);
            if (!employees.length) {
                buildCutoffTypeOptions([]);
                $tbody.append("<tr><td colspan='4' class='text-muted text-center'>No employees loaded.</td></tr>");
                updateCutoffSelectedCount();
                return;
            }
            employees.forEach(emp => {
                const fullName = cutoffEmployeeName(emp);
                const type = (emp.employee_type || "").trim();
                const $row = $(`
                    <tr>
                        <td class="text-center"><input type="checkbox" class="form-check-input cutoff-employee"></td>
                        <td class="cutoff-emp-name"></td>
                        <td class="cutoff-emp-designation"></td>
                        <td>${employeeTypeBadge(type)}</td>
                    </tr>`);
                $row.attr({"data-name": fullName.toLowerCase(), "data-type": type || "Not set", "data-designation": emp.designation || ""});
                $row.find(".cutoff-employee").val(emp.user_id).attr({"data-name": fullName, "data-type": type})
                    .prop("checked", checkedIds.has(String(emp.user_id)));
                $row.find(".cutoff-emp-name").text(fullName);
                $row.find(".cutoff-emp-designation").text(emp.designation || "-");
                $tbody.append($row);
            });
            buildCutoffTypeOptions(employees.map(emp => (emp.employee_type || "").trim() || "Not set"));
            filterCutoffEmployees();
            updateCutoffSelectedCount();
        }

        // ---- Create vs Edit ----
        let editingCutoffId = null;

        function setCutoffModalMode(cutoff) {
            editingCutoffId = cutoff ? cutoff.id : null;
            $("#createCutoffLabel").text(cutoff ? "Edit Cutoff" : "Create Cutoff");
            $("#create_cutoff_modal .modal-sub").text(cutoff
                ? "Change the period or the employees of this draft. Ticked employees are regenerated; unticked ones are removed."
                : "Total each employee's attendance for a period. It starts as a draft you can review before finalizing.");
            $("#create_cutoff_modal .modal-icon i").attr("class", cutoff ? "bi bi-pencil-square" : "bi bi-calendar-range");
            $("#btn_save_cutoff").html(cutoffSaveLabel());
        }

        function cutoffSaveLabel() {
            return editingCutoffId
                ? '<i class="bi bi-check2-circle me-1"></i>Save changes'
                : '<i class="bi bi-gear-wide-connected me-1"></i>Generate cutoff';
        }

        $(document).on("click", ".btn-edit-cutoff", function () {
            const id = $(this).data("id");
            const $button = $(this).prop("disabled", true);
            fetch(`{{ url('/cutoff-summaries') }}/${id}/edit`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load the cutoff.", "Error");
                        return;
                    }
                    const cutoff = data.data;
                    if (cutoff.status !== "draft") {
                        toastr.warning("Only a draft cutoff can be edited.", "Not editable");
                        loadCutoffPeriods();
                        return;
                    }
                    resetCutoffFeedback();
                    resetCreateCutoffForm();
                    setCutoffModalMode(cutoff);
                    $("#cutoff_title").val(cutoff.title || "");
                    $("#cutoff_payroll_date").val(cutoff.payroll_date || "");
                    $("#cutoff_remarks").val(cutoff.remarks || "");
                    $("#cutoff_modal_start_date").val(cutoff.period_start);
                    $("#cutoff_modal_end_date").val(cutoff.period_end);
                    const employees = cutoff.employees || [];
                    renderCutoffEmployees(employees, new Set(employees.map(emp => String(emp.user_id))));
                    $("#create_cutoff_modal").modal("show");
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to load the cutoff.", "Error");
                })
                .finally(() => $button.prop("disabled", false));
        });

        function updateCutoffSelectedCount() {
            const total = $("#cutoff_employee_body .cutoff-employee").length;
            const picked = $("#cutoff_employee_body .cutoff-employee:checked").length;
            $("#cutoff_selected_count").text(total ? `${picked} of ${total} selected` : "0 selected");
        }

        function filterCutoffEmployees() {
            const search = ($("#cutoff_search_name").val() || "").toLowerCase();
            const types = pickedCutoffTypes();
            $("#cutoff_employee_body tr[data-name]").each(function () {
                const name = ($(this).data("name") || "").toString();
                const type = ($(this).attr("data-type") || "").toString();
                const show = (!search || name.includes(search)) && (!types.length || types.includes(type));
                $(this).toggle(show);
            });
            // "Select all" reflects the rows now visible.
            const $visible = $("#cutoff_employee_body tr:visible .cutoff-employee");
            $("#cutoff_select_all").prop("checked", $visible.length > 0 && $visible.length === $visible.filter(":checked").length);
        }

        // ---- Employee type multi-select ----
        function buildCutoffTypeOptions(allTypes) {
            const counts = {};
            allTypes.forEach(t => counts[t] = (counts[t] || 0) + 1);
            const types = Object.keys(counts).sort((a, b) => a === "Permanent" ? -1 : b === "Permanent" ? 1 : a.localeCompare(b));
            const $box = $("#cutoff_type_options").empty();
            types.forEach((type, i) => {
                const id = `cutoff_type_opt_${i}`;
                const $item = $(`
                    <div class="form-check">
                        <input class="form-check-input cutoff-type-opt" type="checkbox" id="${id}">
                        <label class="form-check-label d-flex justify-content-between" for="${id}">
                            <span></span><span class="text-muted small"></span>
                        </label>
                    </div>`);
                $item.find("input").val(type);
                $item.find("label span:first").text(type);
                $item.find("label span:last").text(counts[type]);
                $box.append($item);
            });
            $("#cutoff_type_toggle").prop("disabled", !types.length);
            updateCutoffTypeLabel();
        }

        function pickedCutoffTypes() {
            return $("#cutoff_type_options .cutoff-type-opt:checked").map(function () {
                return $(this).val();
            }).get();
        }

        function updateCutoffTypeLabel() {
            const picked = pickedCutoffTypes();
            const total = $("#cutoff_type_options .cutoff-type-opt").length;
            let label = "All employee types";
            if (picked.length && picked.length < total) {
                label = picked.length <= 2 ? picked.join(", ") : `${picked.length} types selected`;
            }
            $("#cutoff_type_label").text(label);
        }

        $(document).on("change", "#cutoff_type_options .cutoff-type-opt", function () {
            updateCutoffTypeLabel();
            filterCutoffEmployees();
        });

        $("#cutoff_type_all").click(function () {
            $("#cutoff_type_options .cutoff-type-opt").prop("checked", true);
            updateCutoffTypeLabel();
            filterCutoffEmployees();
        });

        $("#cutoff_type_clear").click(function () {
            $("#cutoff_type_options .cutoff-type-opt").prop("checked", false);
            updateCutoffTypeLabel();
            filterCutoffEmployees();
        });

        // Resigned/Retired are always left out by the server; the other
        // non-Permanent types are allowed but the user must confirm them.
        const SEPARATED_TYPES = ["Resigned", "Retired"];

        function employeeTypeBadge(type) {
            if (!type) {
                return `<span class="text-muted">—</span>`;
            }
            if (type === "Permanent") {
                return `<span class="badge bg-success">${type}</span>`;
            }
            const cls = SEPARATED_TYPES.includes(type) ? "bg-danger" : "bg-warning text-dark";
            return `<span class="badge ${cls}">${type}</span>`;
        }

        // Checked employees whose type is set and isn't Permanent.
        function flaggedSelectedEmployees() {
            return $("#cutoff_employee_body .cutoff-employee:checked").map(function () {
                const type = ($(this).data("type") || "").toString();
                return type && type !== "Permanent"
                    ? {id: parseInt($(this).val(), 10), name: $(this).data("name"), type: type}
                    : null;
            }).get();
        }

        function selectedEmployeeIds() {
            return $("#cutoff_employee_body .cutoff-employee:checked").map(function () {
                return parseInt($(this).val(), 10);
            }).get();
        }

        function showTypeWarning(flagged) {
            const byType = {};
            flagged.forEach(e => (byType[e.type] = byType[e.type] || []).push(e.name));

            const $list = $("#cutoff_type_warning_list").empty();
            Object.keys(byType).sort().forEach(type => {
                const names = byType[type].map(n => `<li>${n}</li>`).join("");
                $list.append(`
                    <div class="mb-2">
                        ${employeeTypeBadge(type)} <strong>${byType[type].length}</strong> selected
                        <ul class="mb-0 small">${names}</ul>
                    </div>`);
            });

            const separated = flagged.filter(e => SEPARATED_TYPES.includes(e.type)).length;
            const others = flagged.length - separated;
            $("#cutoff_type_warning_note").text(separated > 0
                ? `Resigned and retired employees are always left out of a cutoff, even if you keep them.`
                : "");
            // Nothing left to keep when every flagged employee is resigned/retired.
            $("#btn_type_warning_keep").toggle(others > 0);

            $("#cutoff_type_warning_modal").modal("show");
        }

        $(document).on("shown.bs.modal", ".cutoff-stacked-modal", function () {
            $(".modal-backdrop").last().addClass("cutoff-stacked-backdrop");
        });

        $("#btn_type_warning_back").click(function () {
            $("#cutoff_type_warning_modal").modal("hide");
        });

        $("#btn_type_warning_keep").click(function () {
            $("#cutoff_type_warning_modal").modal("hide");
            submitCutoff(selectedEmployeeIds());
        });

        $("#btn_type_warning_remove").click(function () {
            const flaggedIds = flaggedSelectedEmployees().map(e => e.id);
            flaggedIds.forEach(id => $(`#cutoff_employee_body .cutoff-employee[value='${id}']`).prop("checked", false));
            $("#cutoff_select_all").prop("checked", false);
            updateCutoffSelectedCount();
            $("#cutoff_type_warning_modal").modal("hide");

            const remaining = selectedEmployeeIds();
            if (remaining.length < 1) {
                showCutoffFeedback("No employees left after removing them. Please select at least one employee.", "danger");
                return;
            }
            submitCutoff(remaining);
        });

        function createCutoffSummaries() {
            const title = ($("#cutoff_title").val() || "").trim();
            const startDate = $("#cutoff_modal_start_date").val();
            const endDate = $("#cutoff_modal_end_date").val();
            const selected = selectedEmployeeIds();

            // A typed date that doesn't exist (e.g. 09/31) leaves the input empty but "badInput".
            const invalidDate = [["#cutoff_modal_start_date", "Start date"], ["#cutoff_modal_end_date", "End date"]]
                .find(([selector]) => $(selector)[0]?.validity?.badInput);
            if (invalidDate) {
                showCutoffFeedback(`${invalidDate[1]} is not a real date. Check the day, for example September has only 30 days.`, "danger");
                $(invalidDate[0]).trigger("focus");
                return;
            }

            if (!title || !startDate || !endDate) {
                const missing = [[!title, "title"], [!startDate, "start date"], [!endDate, "end date"]].filter(([m]) => m).map(([, n]) => n);
                showCutoffFeedback(`Please fill in the ${missing.join(", ")}.`, "danger");
                return;
            }

            if (selected.length < 1) {
                showCutoffFeedback("Please select at least one employee.", "danger");
                return;
            }

            const flagged = flaggedSelectedEmployees();
            if (flagged.length > 0) {
                showTypeWarning(flagged);
                return;
            }

            submitCutoff(selected);
        }

        async function submitCutoff(selected) {
            const isEdit = !!editingCutoffId;
            const payload = {
                title: ($("#cutoff_title").val() || "").trim(),
                start_date: $("#cutoff_modal_start_date").val(),
                end_date: $("#cutoff_modal_end_date").val(),
                payroll_date: $("#cutoff_payroll_date").val() || null,
                remarks: ($("#cutoff_remarks").val() || "").trim() || null,
                employees: selected,
            };
            const saveUrl = isEdit ? `{{ url('/cutoff-summaries') }}/${editingCutoffId}` : "{{ route('createCutoffSummaries') }}";

            resetCutoffFeedback();
            const $saveBtn = $("#btn_save_cutoff");
            $saveBtn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
                + (isEdit ? "Saving..." : "Generating..."));
            showCutoffProgress(isEdit ? "Saving and regenerating cutoff" : "Generating cutoff", selected.length);

            // Every batch sends the full list (for validation and, when editing, removals)
            // plus the slice to generate now.
            let lastMessage = null;
            try {
                await runCutoffBatches(selected, "Generating employee summaries", async batch => {
                    const data = await cutoffPostJson(saveUrl, isEdit ? "PUT" : "POST", Object.assign({}, payload, {batch: batch}));
                    lastMessage = data.message;
                    return data.processed;
                });
                hideCutoffProgress();
                toastr.success(lastMessage || "Cutoff generated.", "Information");
                resetCreateCutoffForm();
                $("#create_cutoff_modal").modal("hide");
                loadCutoffPeriods();
            } catch (err) {
                console.error(err);
                hideCutoffProgress();
                const done = err.done || 0;
                const message = (err.message || "Failed to generate cutoff.")
                    + (done > 0 ? ` Stopped after ${done} of ${selected.length} employees; those are saved. Click ${isEdit ? "Save changes" : "Generate cutoff"} again to finish.` : "");
                showCutoffFeedback(message, "danger");
                toastr.error(message, "Error");
                if (done > 0) loadCutoffPeriods();
            } finally {
                $saveBtn.prop("disabled", false).html(cutoffSaveLabel());
            }
        }

        function showCutoffFeedback(message, type) {
            const $feedback = $("#cutoff_feedback");
            $feedback.removeClass("d-none alert-success alert-danger alert-info")
                .addClass("alert-" + type)
                .text(message);
        }

        function resetCutoffFeedback() {
            $("#cutoff_feedback").addClass("d-none").removeClass("alert-success alert-danger alert-info").text("");
        }

        function resetCreateCutoffForm() {
            $("#cutoff_title").val("");
            $("#cutoff_payroll_date").val("");
            $("#cutoff_remarks").val("");
            $("#cutoff_modal_start_date").val("");
            $("#cutoff_modal_end_date").val("");
            // Last month or the month before it can be generated (the server enforces this too):
            // a month's payroll uses the DTR of the month before it.
            const today = new Date();
            const pad = n => String(n).padStart(2, "0");
            const firstEarliest = new Date(today.getFullYear(), today.getMonth() - 2, 1);
            const firstPrev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastPrev = new Date(today.getFullYear(), today.getMonth(), 0);
            const toIso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            $("#cutoff_modal_start_date, #cutoff_modal_end_date")
                .attr("min", toIso(firstEarliest))
                .attr("max", toIso(lastPrev));
            // Prefill the whole of last month (1st to last day).
            $("#cutoff_modal_start_date").val(toIso(firstPrev));
            $("#cutoff_modal_end_date").val(toIso(lastPrev));
            const monthLabel = firstPrev.toLocaleDateString("en-US", {month: "long", year: "numeric"});
            const earliestLabel = firstEarliest.toLocaleDateString("en-US", {month: "long", year: "numeric"});
            $("#cutoff_period_hint").text(`${earliestLabel} or ${monthLabel} can be generated. Half-month ranges are fine.`);
            $("#cutoff_title").attr("placeholder", `e.g. ${monthLabel.split(" ")[0]} 1-${lastPrev.getDate()}, ${firstPrev.getFullYear()}`);
            $("#cutoff_search_name").val("");
            buildCutoffTypeOptions([]);
            $("#cutoff_select_all").prop("checked", false);
            $("#cutoff_employee_body").empty()
                .append("<tr><td colspan='4' class='text-muted text-center'>No employees loaded.</td></tr>");
            updateCutoffSelectedCount();
            loadDivision();
        }

        $("#cutoff_title_filter").on("input", applyCutoffListFilters);

        $(document).on("click", ".btn-view-cutoff", function () {
            const id = $(this).data("id");
            const start = $(this).data("start");
            const end = $(this).data("end");
            const title = $(this).data("title");
            currentCutoffDetail = {id: id, start: start, end: end, title: title};
            $("#cutoff_detail_title").text(title);
            $("#cutoff_detail_start").text(formatDisplayDate(start));
            $("#cutoff_detail_end").text(formatDisplayDate(end));
            loadCutoffDetails(id);
            loadCutoffStatus(id);
            $("#cutoff_detail_modal").modal("show");
        });

        // ---- Cutoff lifecycle: draft -> processing -> for review -> finalized -> closed ----
        let currentCutoffStatus = null;
        const CUTOFF_CAN_MANAGE = @json(permission_check('payroll.settings'));

        const PRECHECK_LABELS = {
            unfiled_absence: "Absent with nothing filed (becomes a pay deduction)",
            pending_leave: "Leave still pending",
            changed_since_generated: "Leave changed after the cutoff was generated",
            old_formula: "Generated with the old credit formula",
        };

        const SNAPSHOT_LABELS = {
            no_salary: "No salary on file",
            no_employee_record: "No user account",
            missing_employee_type: "Employee type is blank",
            separated_in_period: "Separated during this cutoff",
            salary_changed_in_period: "Salary changed during this cutoff",
            unfiled_absence: "Absent with nothing filed",
            pending_leave: "Leave still pending",
            lwop_non_working_day: "LWOP on a holiday or rest day",
            lwop_mismatch: "LWOP days do not match",
            no_opening_balance: "No opening leave balance",
            late_changes_included: "Late changes carried in",
        };

        function renderLateChanges(data) {
            const changes = data.late_changes || {found: [], included: []};
            const row = (a, otherLabel) => `
                <tr>
                    <td class="text-start">${escapeHtml(((a.lname || "") + ", " + (a.fname || "")).toUpperCase())}</td>
                    <td class="text-start small">${escapeHtml(a.reason || "")}</td>
                    <td>${a.adjustment_type === "credits" ? formatThreeDecimals(a.credits) + " credits"
                        : (Number(a.amount) < 0 ? "Refund " + snapMoney(-a.amount) : "Charge " + snapMoney(a.amount))}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(a.status)}</span>${a.other_cutoff ? `<div class="small text-muted">${otherLabel} ${escapeHtml(a.other_cutoff)}</div>` : ""}</td>
                </tr>`;
            const table = (rows, otherLabel) => `<div class="table-responsive"><table class="table table-sm align-middle saln-table-premium mb-2">
                <thead><tr><th class="text-start">Employee</th><th class="text-start">Change</th><th>Adjustment</th><th>Status</th></tr></thead>
                <tbody>${rows.map(a => row(a, otherLabel)).join("")}</tbody></table></div>`;
            let html = "";
            if (changes.found.length) {
                html += `<div class="alert alert-warning py-2 mb-2"><strong>Changed after finalizing (${changes.found.length}).</strong> These are not edited here; they are carried into each employee's next cutoff.</div>` + table(changes.found, "In");
            }
            if (changes.included.length) {
                html += `<div class="alert alert-info py-2 mb-2"><strong>Carried in from earlier cutoffs (${changes.included.length}).</strong></div>` + table(changes.included, "From");
            }
            $("#cutoff_late_changes").toggleClass("d-none", !html).html(html);
        }

        const SNAPSHOT_GROUPS = [
            ["Missing information", ["no_employee_record", "missing_employee_type", "no_opening_balance"], "bg-secondary"],
            ["Attendance", ["unfiled_absence"], "bg-danger"],
            ["Leave", ["pending_leave", "lwop_non_working_day", "lwop_mismatch"], "bg-warning text-dark"],
            ["Payroll", ["no_salary", "salary_changed_in_period", "separated_in_period"], "bg-danger"],
        ];

        function snapshotGroupBadges(counts) {
            return SNAPSHOT_GROUPS.map(([label, types, style]) => {
                const total = types.reduce((sum, type) => sum + (counts[type] || 0), 0);
                return total ? `<span class="badge ${style} me-1">${label}: ${total}</span>` : "";
            }).join("");
        }

        function snapMoney(value) {
            return value === null || value === undefined ? "—"
                : Number(value).toLocaleString("en-US", {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function snapText(value) {
            return value === null || value === undefined || value === "" ? "—" : escapeHtml(value);
        }

        function snapTime(value) {
            return value ? escapeHtml(String(value).substring(11, 16)) : "—";
        }

        function snapTable(headers, rows, empty) {
            if (!rows.length) {
                return `<p class="text-muted text-center my-4">${empty}</p>`;
            }
            return `<div class="table-responsive"><table class="table table-sm align-middle saln-table-premium">
                <thead><tr>${headers.map(h => `<th>${h}</th>`).join("")}</tr></thead>
                <tbody>${rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join("")}</tr>`).join("")}</tbody>
            </table></div>`;
        }

        function snapFacts(pairs) {
            return `<dl class="row mb-3">${pairs.map(([label, value]) =>
                `<dt class="col-sm-5 col-lg-3 fw-normal text-muted">${label}</dt><dd class="col-sm-7 col-lg-3 mb-1">${value}</dd>`).join("")}</dl>`;
        }

        function cutoffSnapshotCell(item, name) {
            if (!item.snapshot_id) {
                return `<span class="text-muted" title="Generate the snapshot to fill this in">—</span>`;
            }
            let items = [];
            try { items = JSON.parse(item.exceptions || "[]") || []; } catch (e) { items = []; }
            const badge = Number(item.payroll_eligible) === 0
                ? `<span class="badge bg-danger" title="${escapeHtml(item.exclusion_reason || "")}">Excluded</span>`
                : (items.length
                    ? `<span class="badge bg-warning text-dark">${items.length} to review</span>`
                    : `<span class="badge bg-success">OK</span>`);
            return `${badge} <button type="button" class="btn btn-action btn-view btn-employee-snapshot ms-1"
                        data-employee="${item.employee_id}" data-name="${escapeHtml(name)}" title="View what was saved"><i class="bi bi-eye"></i></button>`;
        }

        $(document).on("click", ".btn-employee-snapshot", function () {
            loadEmployeeSnapshot($(this).data("employee"), $(this).data("name"));
        });

        function loadEmployeeSnapshot(employeeId, name) {
            fetch(`{{ url('/cutoff-summaries') }}/${currentCutoffDetail.id}/employees/${employeeId}/snapshot`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load the snapshot.", "Error");
                        return;
                    }
                    renderEmployeeSnapshot(data, name);
                    $("#cutoff_employee_modal").modal("show");
                })
                .catch(() => toastr.error("Failed to load the snapshot.", "Error"));
        }

        function renderEmployeeSnapshot(data, name) {
            const e = data.employee;
            const s = data.summary || {};
            $("#cutoffEmployeeLabel").text(name);
            $("#cutoff_employee_sub").text([e.position, e.employment_type, e.division_name, e.section_name].filter(Boolean).join(" · "));

            const items = s.exceptions || [];
            $("#cutoff_employee_items").html(items.length
                ? `<div class="alert alert-warning py-2"><ul class="mb-0 ps-3">${items.map(i => `<li><strong>${escapeHtml(SNAPSHOT_LABELS[i.type] || i.type)}:</strong> ${escapeHtml(i.detail)}</li>`).join("")}</ul></div>`
                : "");

            $("#snap_attendance").html(snapTable(
                ["Date", "Status", "Shift", "In", "Out", "Scheduled (min)", "Rendered (min)", "Late", "Undertime", "Paid", "LWOP", "Day rate"],
                data.days.map(d => [
                    escapeHtml(d.date),
                    escapeHtml((d.attendance_status || "—").replace(/_/g, " ")),
                    snapText(d.schedule_code),
                    snapTime(d.time_in),
                    snapTime(d.time_out),
                    d.required_minutes ?? "—",
                    d.rendered_minutes ?? "—",
                    d.late_minutes,
                    d.undertime_minutes,
                    Number(d.is_paid) === 1 ? "Yes" : "No",
                    Number(d.lwop_fraction || 0) || "—",
                    snapMoney(d.scheduled_daily_rate),
                ]),
                "No daily records saved."
            ));

            $("#snap_leave").html(snapTable(
                ["Date", "Type", "Status", "Hours", "Paid days", "Unpaid days", "Credits charged", "Charged to fallback"],
                data.leaves.map(l => [
                    escapeHtml(l.leave_date),
                    snapText(l.leave_type_code) + (Number(l.is_lwop) === 1 ? ` <span class="badge bg-secondary">LWOP</span>` : ""),
                    snapText(l.approval_status),
                    l.hours,
                    Number(l.paid_days),
                    Number(l.unpaid_days),
                    formatThreeDecimals(l.credits_deducted),
                    Number(l.fallback_credits) > 0 ? formatThreeDecimals(l.fallback_credits) : "—",
                ]),
                "No leave in this period."
            ));

            $("#snap_credits").html(snapFacts([
                ["Earns credits", Number(e.credit_eligible) === 1 ? "Yes" : "No"],
                ["Filed LWOP days", snapText(s.credit_without_pay_days === null ? null : Number(s.credit_without_pay_days))],
                ["Table III value", s.leave_earning_rate === null ? "—" : formatThreeDecimals(s.leave_earning_rate)],
                ["Service days in period", snapText(s.qualifying_service_days === null ? null : Number(s.qualifying_service_days))],
                ["VL for this cutoff", s.vl_earned === null ? "—" : formatThreeDecimals(s.vl_earned)],
                ["SL for this cutoff", s.sl_earned === null ? "—" : formatThreeDecimals(s.sl_earned)],
            ]) + snapTable(
                ["Leave type", "Beginning", "Used", "Adjustments", "Ending", "Beginning from"],
                data.balances.map(b => [
                    snapText(b.leave_type_code),
                    formatThreeDecimals(b.beginning_balance),
                    formatThreeDecimals(b.leave_used),
                    formatThreeDecimals(b.adjustments),
                    formatThreeDecimals(b.ending_balance),
                    escapeHtml((b.opening_source || "—").replace(/_/g, " ")),
                ]),
                "No balances saved."
            ));

            $("#snap_payroll").html(snapFacts([
                ["Computation", snapText(e.pay_computation_name)],
                ["Monthly basic", snapMoney(e.monthly_salary)],
                ["Salary grade / step", snapText(e.salary_grade === null ? null : `${e.salary_grade} / ${e.salary_step}`)],
                ["Daily base rate", snapMoney(e.daily_base_rate)],
                ["Hourly rate", snapMoney(e.hourly_rate)],
                ["Minute rate", e.minute_rate === null ? "—" : Number(e.minute_rate).toFixed(4)],
                ["Usual hours a day", snapText(e.scheduled_hours_per_day === null ? null : Number(e.scheduled_hours_per_day))],
                ["Scheduled daily rate", snapMoney(e.scheduled_daily_rate)],
                ["Absences charged", snapMoney(s.payroll_absence_deduction_basis)],
                ["LWOP charged", snapMoney(s.payroll_lwop_deduction_basis)],
            ]) + snapTable(
                ["Code", "Name", "Type", "Taxable / before tax", "Amount"],
                data.components.map(c => [
                    snapText(c.code), snapText(c.name), snapText(c.type), Number(c.taxable) === 1 ? "Yes" : "No", snapMoney(c.amount),
                ]),
                "No earnings or deductions saved."
            ));
        }

        function renderSnapshotPanel(data) {
            const $panel = $("#cutoff_snapshot_panel").addClass("d-none").empty();
            const report = data.snapshot;
            if (!report) {
                return;
            }
            const intact = data.snapshot_intact === false
                ? `<div class="alert alert-danger py-2">This finalized snapshot no longer matches what was saved. Its data was changed outside the system.</div>`
                : "";
            const excluded = (report.excluded || []).map(e => `
                <tr>
                    <td class="text-start">${escapeHtml(e.employee)}</td>
                    <td class="text-start">Excluded</td>
                    <td class="text-start small">${escapeHtml(e.reason)}</td>
                </tr>`).join("");
            const items = (report.exceptions || []).map(i => `
                <tr>
                    <td class="text-start">${escapeHtml(i.employee)}</td>
                    <td class="text-start">${escapeHtml(SNAPSHOT_LABELS[i.type] || i.type)}</td>
                    <td class="text-start small">${escapeHtml(i.detail)}</td>
                </tr>`).join("");
            const table = (excluded || items) ? `
                <div class="table-responsive" style="max-height: 40vh;">
                    <table class="table table-sm align-middle saln-table-premium mb-0">
                        <thead><tr><th class="text-start">Employee</th><th class="text-start">Item</th><th class="text-start">Detail</th></tr></thead>
                        <tbody>${excluded}${items}</tbody>
                    </table>
                </div>` : "";
            $panel.removeClass("d-none").html(`
                ${intact}
                <div class="alert alert-info py-2 mb-2">
                    <strong>Snapshot:</strong> ${report.processed} processed
                    &middot; ${report.payroll_eligible} for payroll
                    &middot; ${report.credit_eligible} for earned credits
                    &middot; ${(report.exceptions || []).length} item(s) to review
                    <div class="mt-1">${snapshotGroupBadges(report.counts || {})}</div>
                </div>
                ${table}`);
        }

        function cutoffStatusBadge(status) {
            const s = (status || "draft").toLowerCase();
            if (s === "finalized") return `<span class="badge bg-primary"><i class="bi bi-lock-fill"></i> Finalized</span>`;
            if (s === "closed") return `<span class="badge bg-dark"><i class="bi bi-archive-fill"></i> Closed</span>`;
            if (s === "for_review") return `<span class="badge bg-warning text-dark"><i class="bi bi-search"></i> For review</span>`;
            if (s === "processing") return `<span class="badge bg-info text-dark">Processing</span>`;
            return `<span class="badge bg-secondary">Draft</span>`;
        }

        function escapeHtml(value) {
            return $("<div>").text(value ?? "").html();
        }

        function loadCutoffStatus(cutoffId) {
            currentCutoffStatus = null;
            $("#cutoff_detail_status").html("");
            $("#cutoff_detail_status_note").text("");
            $("#cutoff_precheck_panel").addClass("d-none").empty();
            $("#btn_finalize_cutoff, #btn_reopen_cutoff, #btn_open_leave_credits, #btn_generate_snapshot, #btn_return_draft").addClass("d-none");
            $("#cutoff_snapshot_panel").addClass("d-none").empty();
            $("#cutoff_late_changes").addClass("d-none").empty();

            fetch(`{{ url('/cutoff-summaries') }}/${cutoffId}/status`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || currentCutoffDetail.id !== cutoffId) {
                        return;
                    }
                    currentCutoffStatus = data;
                    $("#cutoff_detail_status").html(cutoffStatusBadge(data.status));

                    const last = (data.history || [])[0];
                    renderSnapshotPanel(data);
                    renderLateChanges(data);
                    $("#cutoff_detail_settings").text([
                        data.cutoff_type === "partial" ? "Partial month" : (data.cutoff_type === "whole_month" ? "Whole month" : null),
                        data.payroll_date ? `Payroll date: ${formatDisplayDate(data.payroll_date)}` : null,
                        data.remarks,
                    ].filter(Boolean).join(" · "));
                    if (last && (data.status === "finalized" || data.status === "closed")) {
                        const who = [last.fname, last.lname].filter(Boolean).join(" ");
                        const when = formatDisplayDate((last.created_at || "").substring(0, 10));
                        $("#cutoff_detail_status_note").text(`${data.status === "closed" ? "Closed" : "Finalized"} ${when}${who ? " by " + who : ""}`);
                    } else if (last && last.to_status === "draft" && last.reason) {
                        $("#cutoff_detail_status_note").text(`Reopened: ${last.reason}`);
                    }

                    const $panel = $("#cutoff_precheck_panel");
                    $panel.removeClass("alert-warning alert-success alert-secondary");
                    if (data.status === "draft" || data.status === "for_review" || data.status === "processing") {
                        const canFinalizeNow = data.status === "for_review" || (data.status === "draft" && !data.snapshots_enabled);
                        $("#btn_finalize_cutoff").toggleClass("d-none", !(canFinalizeNow && data.can_finalize));
                        $("#btn_generate_snapshot").toggleClass("d-none", !(data.status === "draft" && data.snapshots_enabled && data.can_manage));
                        $("#btn_return_draft").toggleClass("d-none", !(data.status === "for_review" && data.can_manage));
                        const counts = (data.precheck && data.precheck.counts) || {};
                        const keys = Object.keys(counts);
                        if (keys.length) {
                            $panel.removeClass("d-none").addClass("alert-warning").html(
                                `<strong>Before finalizing:</strong> `
                                + keys.map(k => `${escapeHtml(PRECHECK_LABELS[k] || k)}: ${counts[k]}`).join(" &middot; ")
                            );
                        } else {
                            $panel.removeClass("d-none").addClass("alert-success")
                                .text("Pre-check is clear. This cutoff is ready to finalize.");
                        }
                    } else {
                        // Finalized/closed: its month's credits are a batch on Leave Credits Earned.
                        const monthKey = (currentCutoffDetail.start || "").substring(0, 7);
                        $("#btn_open_leave_credits")
                            .attr("href", `{{ route('leave_credits_earned') }}?month=${monthKey}`)
                            .removeClass("d-none");
                        if (data.status === "finalized" && !data.reopen_blocker && data.can_reopen) {
                            $("#btn_reopen_cutoff").removeClass("d-none");
                        }
                        if (data.reopen_blocker) {
                            $panel.removeClass("d-none").addClass("alert-secondary").text(data.reopen_blocker);
                        }
                    }
                })
                .catch(err => console.error(err));
        }

        $("#btn_finalize_cutoff").click(function () {
            if (!currentCutoffStatus || !currentCutoffStatus.precheck) {
                return;
            }
            const precheck = currentCutoffStatus.precheck;
            $("#cutoff_finalize_count").text(precheck.employees);
            const issues = precheck.issues || [];
            const $box = $("#cutoff_finalize_issues").empty();
            if (!issues.length) {
                $box.html(`<div class="alert alert-success mb-0">Pre-check is clear: no unfiled absences, pending leave or outdated rows.</div>`);
                $("#cutoff_finalize_confirm_label").text("I reviewed this cutoff and it is ready to finalize.");
            } else {
                const rows = issues.map(i => `
                    <tr>
                        <td class="text-start">${escapeHtml(i.employee)}</td>
                        <td class="text-start">${escapeHtml(PRECHECK_LABELS[i.type] || i.type)}</td>
                        <td class="text-start small">${escapeHtml(i.detail)}</td>
                    </tr>`).join("");
                $box.html(`
                    <div class="alert alert-warning py-2">Check these first. Unfiled absences become pay deductions; leave approved after finalizing goes into the next cutoff as an adjustment.</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle saln-table-premium">
                            <thead><tr><th class="text-start">Employee</th><th class="text-start">Check</th><th class="text-start">Detail</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`);
                $("#cutoff_finalize_confirm_label").text(`I reviewed the ${issues.length} item(s) above and want to finalize anyway.`);
            }
            $("#cutoff_finalize_confirm").prop("checked", false);
            $("#btn_confirm_finalize").prop("disabled", true);
            $("#cutoff_finalize_modal").modal("show");
        });

        $("#cutoff_finalize_confirm").change(function () {
            $("#btn_confirm_finalize").prop("disabled", !$(this).is(":checked"));
        });

        $("#btn_confirm_finalize").click(function () {
            postCutoffLifecycle("finalize", {confirmed: true}, $(this), "Finalizing...", "#cutoff_finalize_modal");
        });

        $("#btn_generate_snapshot").click(function () {
            generateSnapshotInBatches($(this));
        });

        /** start -> batches of employees (with progress) -> finish; a failure puts the cutoff back to draft. */
        async function generateSnapshotInBatches($button) {
            const cutoffId = currentCutoffDetail.id;
            if (!cutoffId) return;
            const base = `{{ url('/cutoff-summaries') }}/${cutoffId}/generate-snapshot`;
            let started = false;

            setButtonLoading($button, true, "Generating...");
            showCutoffProgress("Generating snapshot", 0);
            try {
                const start = await cutoffPostJson(`${base}/start`);
                started = true;
                const ids = start.employee_ids || [];
                await runCutoffBatches(ids, "Copying attendance, leave and pay", async batch => {
                    const data = await cutoffPostJson(`${base}/batch`, "POST", {employee_ids: batch});
                    return data.processed;
                });
                updateCutoffProgress(ids.length, ids.length, "Finishing...", null);
                const finish = await cutoffPostJson(`${base}/finish`);
                hideCutoffProgress();
                toastr.success(finish.message, "Information");
            } catch (err) {
                console.error(err);
                hideCutoffProgress();
                if (started) {
                    cutoffPostJson(`${base}/abandon`, "POST", {reason: err.message || "Stopped."}).catch(() => {});
                }
                toastr.error((err.message || "Snapshot generation failed.") + (started ? " The cutoff is back to draft; generate the snapshot again." : ""), "Error");
            } finally {
                setButtonLoading($button, false, "Generating...");
                loadCutoffStatus(cutoffId);
                loadCutoffPeriods();
            }
        }

        $("#btn_return_draft").click(function () {
            postCutoffLifecycle("return-to-draft", {}, $(this), "Sending back...", null);
        });

        $("#btn_reopen_cutoff").click(function () {
            $("#cutoff_reopen_reason").val("");
            $("#cutoff_reopen_modal").modal("show");
        });

        $("#btn_confirm_reopen").click(function () {
            const reason = ($("#cutoff_reopen_reason").val() || "").trim();
            if (reason.length < 5) {
                toastr.warning("Give a reason for reopening this cutoff.", "Validation");
                return;
            }
            postCutoffLifecycle("reopen", {reason: reason}, $(this), "Reopening...", "#cutoff_reopen_modal");
        });

        function postCutoffLifecycle(action, body, $button, loadingText, modalSelector) {
            const cutoffId = currentCutoffDetail.id;
            if (!cutoffId) {
                return;
            }
            setButtonLoading($button, true, loadingText);
            fetch(`{{ url('/cutoff-summaries') }}/${cutoffId}/${action}`, {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}"},
                body: JSON.stringify(body),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message, "Information");
                        if (modalSelector) {
                            $(modalSelector).modal("hide");
                        }
                        loadCutoffStatus(cutoffId);
                        loadCutoffPeriods();
                    } else {
                        toastr.error(data.message || "Action failed.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Action failed.", "Error");
                })
                .finally(() => {
                    setButtonLoading($button, false, loadingText);
                    if (action === "finalize") {
                        $button.prop("disabled", !$("#cutoff_finalize_confirm").is(":checked"));
                    }
                });
        }


        function loadCutoffDetails(cutoffId) {
            const params = new URLSearchParams();
            params.append("cutoff_id", cutoffId);

            fetch(`{{ route('getCutoffSummaryPreview') }}?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#cutoff_detail_body");
                    $tbody.empty();
                    if (!data.success || !data.data || !data.data.length) {
                        $tbody.append("<tr><td colspan='12' class='text-muted text-center'>No cutoff summaries found.</td></tr>");
                        return;
                    }

                    data.data.forEach(item => {
                        const middleInitial = item.mname ? item.mname.charAt(0) + '.' : '';
                        const suffix = item.suffix ? ` ${item.suffix}` : '';
                        const fullName = formatEmployeeName(`${item.lname}, ${item.fname} ${middleInitial}${suffix}`);
                        const row = `
                            <tr>
                                <td>${fullName}</td>
                                <td>${Number(item.scheduled_work_days) > 0 ? item.scheduled_work_days : '<span class="text-muted" title="Regenerate this cutoff to fill this in">—</span>'}</td>
                                <td>${item.days_present ?? 0}</td>
                                <td>${item.days_absent ?? 0}</td>
                                <td>${item.late_minutes ?? 0}</td>
                                <td>${item.undertime_minutes ?? 0}</td>
                                <td>${item.hospital_order_days ?? 0}</td>
                                <td>${item.approved_overtime_minutes ?? 0}</td>
                                <td>${item.approved_pass_slips_minutes ?? 0}</td>
                                <td>${item.approved_leave_days ?? 0}</td>
                                <td>${Number(item.lwop_days ?? 0)}</td>
                                <td class="text-nowrap">${cutoffSnapshotCell(item, fullName)}</td>
                            </tr>`;
                        $tbody.append(row);
                    });
                })
                .catch(err => {
                    console.error(err);
                });
        }

        function formatDisplayDate(dateString) {
            if (!dateString) {
                return "-";
            }

            const date = new Date(`${dateString}T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return dateString;
            }

            return date.toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric"
            });
        }

        function formatEmployeeName(name) {
            return (name || "-").toString().trim().toUpperCase();
        }

        function formatThreeDecimals(value) {
            const numeric = parseFloat(value ?? 0);
            return Number.isNaN(numeric) ? "0.000" : numeric.toFixed(3);
        }

        function setButtonLoading($button, isLoading, loadingText) {
            const $label = $button.find(".btn-label");
            const $spinner = $button.find(".spinner-border");
            const defaultText = $button.data("default-text") || $label.text().trim();

            if (!$button.data("default-text")) {
                $button.data("default-text", defaultText);
            }

            $button.prop("disabled", isLoading);
            $label.text(isLoading ? loadingText : defaultText);
            $spinner.toggleClass("d-none", !isLoading);
        }
    </script>
@stop
