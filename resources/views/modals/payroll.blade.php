<style>
    /* Payroll Settings modal: spacing only - colours and borders come from the theme. */
    #payroll_settings_modal .payset-panel {
        padding: 1rem 1.25rem 1.125rem;
    }
    #payroll_settings_modal .payset-panel-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--primary, #00236f);
        margin-bottom: 0.875rem;
    }
    #payroll_settings_modal .payset-panel .table-responsive {
        background: var(--surface, #fff);
        border: 1px solid var(--line, #e6e8f2);
        border-radius: 0.75rem;
    }
    #payroll_settings_modal .payset-panel .table {
        margin-bottom: 0;
    }
    #payroll_settings_modal .payset-panel .table > :not(caption) > * > * {
        padding: 0.625rem 0.75rem;
    }
    #payroll_settings_modal .payset-note {
        margin-top: 0.625rem;
    }
</style>

<div class="modal fade payroll-modal-theme" id="payroll_settings_modal" tabindex="-1" aria-labelledby="payrollSettingsLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header sticky-top">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-cash-stack"></i></div>
                        <div>
                            <h5 class="modal-title" id="payrollSettingsLabel">Payroll Settings</h5>
                            <p class="modal-sub">Salary, and the earnings and deductions that apply to this employee.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body" id="modal_salary_details"
                 style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                <div><!-- EMPLOYEE -->
                    <div class="payset-panel mb-3">
                        <h5 class="payset-panel-title">Salary</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Salary Grade</label>
                                <input type="number" class="form-control" placeholder="e.g. 15" id="salary_grade" min="1" step="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Steps</label>
                                <input type="number" class="form-control" placeholder="e.g. 1" id="step" min="1" step="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" placeholder="₱ e.g. 42178.00" id="basic_salary" min="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="salary_effective_date">Salary effective date</label>
                                <input type="date" class="form-control" id="salary_effective_date" title="Only used when the salary changes. Leave blank for today.">
                            </div>
                        </div>
                    </div>

                    <!-- EARNINGS -->
                    <div class="payset-panel mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h5 class="payset-panel-title mb-0">Earnings</h5>
                            <span class="small text-muted">Global components apply by default unless overridden. Non-global components apply only if given an amount here.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 16%;">Earning</th>
                                    <th style="width: 7%;">Global</th>
                                    <th style="width: 11%;">Default</th>
                                    <th style="width: 12%;">Amount</th>
                                    <th style="width: 12%;">Rate Override %</th>
                                    <th style="width: 8%;">Exclude</th>
                                    <th style="width: 13%;">Effective From</th>
                                    <th style="width: 13%;">Effective To</th>
                                </tr>
                                </thead>
                                <tbody id="dynamic_components_earnings_body"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted payset-note">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
                        </div>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="payset-panel mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h5 class="payset-panel-title mb-0">Deductions</h5>
                            <span class="small text-muted">Global components apply by default unless overridden. Non-global components apply only if given an amount here.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 16%;">Deduction</th>
                                    <th style="width: 7%;">Global</th>
                                    <th style="width: 11%;">Default</th>
                                    <th style="width: 12%;">Amount</th>
                                    <th style="width: 12%;">Rate Override %</th>
                                    <th style="width: 8%;">Exclude</th>
                                    <th style="width: 13%;">Effective From</th>
                                    <th style="width: 13%;">Effective To</th>
                                </tr>
                                </thead>
                                <tbody id="dynamic_components_deductions_body"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted payset-note">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-modal-save" id="btn_save">Save Changes</button>
            </div>
        </div>
    </div>
</div>


<!-- Create Payroll Modal -->
<div class="modal fade payroll-modal-theme" id="create_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header sticky-top">
                <h3 class="modal-title">Create Payroll</h3>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">

                <div class="row g-3">
                    <!-- Payroll Title -->
                    <div class="col-md-12">
                        <label for="payroll_title" class="form-label">Payroll Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <!-- Cutoff Period (required) -->
                    <div class="col-md-12">
                        <label for="cutoff_period" class="form-label">Cutoff Period <span class="text-danger">*</span></label>
                        <select class="form-control" id="cutoff_period" required>
                            <option value="">— Select a cutoff period —</option>
                        </select>
                    </div>

                    <!-- Payroll Period (auto-filled from cutoff, read-only) -->
                    <div class="col-md-6">
                        <label class="form-label">Period Start</label>
                        <input type="date" class="form-control" id="start_date" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Period End</label>
                        <input type="date" class="form-control" id="end_date" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="payroll_type" class="form-label">Type</label>
                        <select class="form-control" id="payroll_type">
                            <option selected>Regular</option>
                            <option>Job Order</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="rate_type" class="form-label">Rate Type</label>
                        <select class="form-control" id="rate_type">
                            <option selected>Non Pro-rated</option>
                            <option>Pro-rated</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="tax_purpose" class="form-label">Tax Purpose</label>
                        <select class="form-control" id="tax_purpose">
                            <option value="">Select tax purpose</option>
                        </select>
                    </div>
                    <!-- Remarks -->
                    <div class="col-md-12">
                        <label for="payroll_remarks" class="form-label">Remarks</label>
                        <textarea type="text" class="form-control" id="remarks" name="remarks" rows="5"
                                  placeholder="Optional"></textarea>
                    </div>
                </div>

            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-modal-save" id="btn_save_payroll">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade payroll-modal-theme" id="append_employee_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header sticky-top">
                <h3 class="modal-title">Append Employees</h3>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto; padding: 1.5rem;">
                <p class="text-muted small">Employees are pulled automatically from this payroll's cutoff entry — every employee with a cutoff summary for this period is included below. Use the search to find someone and enter any manual adjustments.</p>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="filter_name" class="form-label">Search Name</label>
                            <input type="text" class="form-control" id="filter_name" placeholder="Search employee">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="mb-3 w-100">
                            <div class="small text-muted">Cutoff Period</div>
                            <div class="fw-semibold">
                                <span id="preview_cutoff_start">-</span> to <span id="preview_cutoff_end">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                    /* Every adjustment box wide enough to show its amount; many columns scroll sideways. */
                    #append_employee_modal .employee-adjustment { min-width: 6.5rem; }
                    #append_employee_modal #adjustment_columns_row th { min-width: 7rem; }
                </style>
                <div class="border rounded p-2" style="height: 55vh; overflow: auto;">
                    <div>
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light text-center" id="employee_list_head">
                            <tr>
                                <th rowspan="2" style="width: 35%">Full Name</th>
                                <th colspan="1" id="adjustment_group_header">Adjustment</th>
                            </tr>
                            <tr id="adjustment_columns_row">
                                <th>Basic Pay</th>
                            </tr>
                            </thead>
                            <tbody id="employee_list"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-modal-save" id="btn_save_employees">Save Changes</button>
            </div>
        </div>
    </div>
</div>

{{-- Read-only reference opened from Append Employees: what this employee's payroll starts from --}}
<style>
    #employee_components_modal { z-index: 1065; }
    .modal-backdrop.employee-components-backdrop { z-index: 1060; }
    #employee_components_modal .ec-summary { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
    #employee_components_modal .ec-stat { flex: 1 1 140px; background: var(--surface, #fff); border: 1px solid var(--line, #e2e8f0); border-radius: 12px; padding: .6rem .85rem; }
    #employee_components_modal .ec-stat small { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--outline, #64748b); }
    #employee_components_modal .ec-stat strong { font-size: 1.05rem; }
    #employee_components_modal .ec-table { background: var(--surface, #fff); border: 1px solid var(--line, #e2e8f0); border-radius: 12px; overflow: hidden; margin-bottom: 1rem; }
    #employee_components_modal .ec-table table { margin: 0; font-size: .875rem; }
    #employee_components_modal .ec-table thead th { background: var(--surface-low, #f8fafc); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
    #employee_components_modal .ec-table tfoot td { font-weight: 700; background: var(--surface-low, #f8fafc); }
</style>
<div class="modal fade" id="employee_components_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="bi bi-list-columns-reverse"></i></div>
                    <div>
                        <h5 class="modal-title" id="ec_employee_name">Earnings &amp; Deductions</h5>
                        <p class="modal-sub" id="ec_source">What this employee's payroll starts from.</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ec_body">
                <div class="text-center text-muted py-4">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade payroll-modal-theme" id="regular_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header sticky-top">
                <h3 class="modal-title">Regular Payroll</h3>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body p-3">
                <div class="table-responsive shadow-sm rounded">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light" id="regular_payroll_thead"></thead>
                        <tbody id="payroll_employee_list"></tbody>
                        <tfoot class="table-light fw-bold" id="regular_payroll_tfoot"></tfoot>
                    </table>
                </div>
            </div>

            <!-- Optional Footer -->
            <div class="modal-footer">
                <button class="btn btn-modal-close" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Close
                </button>
                <button class="btn btn-modal-save" id="btn_export_regular">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade payroll-modal-theme" id="jo_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header sticky-top">
                <h3 class="modal-title">Job Order Payroll</h3>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body p-3">
                <div class="table-responsive shadow-sm rounded">
                    <table class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th class="sticky-col sticky-1">Serial No.</th>
                            <th class="sticky-col sticky-2">Family Name</th>
                            <th class="sticky-col sticky-3">First Name</th>
                            <th class="sticky-col sticky-4">Middle Name</th>
                            <th class="sticky-col sticky-5">Suffix</th>
                            <th>Position</th>
                            <th>Rate/Day</th>
                            <th># of Days Work</th>
                            <th>Overtime Minutes</th>
                            <th>Amount</th>
                            <th>Tardiness Minutes</th>
                            <th>Amount</th>
                            <th>Salary Adjustment</th>
                            <th>Gross Amount Earned</th>
                            <th>SSS Deduction</th>
                            <th>Income Tax (5%)</th>
                            <th>5% Tax Prev. Payroll</th>
                            <th>Percentage Tax (3%)</th>
                            <th>Professional Fee %</th>
                            <th>Total Deduction</th>
                            <th>Net Amount Due</th>
                        </tr>
                        </thead>
                        <tbody id="jo_payroll_list">
                        <!-- Payroll rows go here -->
                        </tbody>
                        <tfoot class="fw-bold table-light">
                        <tr>
                            <td colspan="13" class="text-end">TOTAL:</td>
                            <td id="total_gross" class="text-end">0.00</td>
                            <td colspan="5"></td>
                            <td id="total_deduction" class="text-end">0.00</td>
                            <td id="total_net" class="text-end">0.00</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-modal-save" id="btn_export_jo">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
            </div>

        </div>
    </div>
</div>


