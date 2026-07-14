<style>
    .payroll-modal-theme .modal-content {
        border: 0;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.2);
    }

    .payroll-modal-theme .modal-header {
        background: linear-gradient(135deg, #0f766e, #0ea5a4);
        color: #fff;
        border: 0;
    }

    .payroll-modal-theme .modal-title {
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    .payroll-modal-theme .btn-close {
        filter: invert(1);
        opacity: 0.9;
    }

    .payroll-modal-theme .modal-body {
        background: #f8fafc;
    }
</style>

<div class="modal fade payroll-modal-theme" id="payroll_settings_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <!-- Sticky Header -->
            <div class="modal-header sticky-top">
                <h3 class="modal-title text-white">Payroll Settings</h3>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <!-- Scrollable Body -->
            <div class="modal-body" id="modal_salary_details"
                 style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                <div><!-- EMPLOYEE -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <h5 class="text-primary border-bottom pb-2">Employee Info</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Salary Grade</label>
                                <input type="number" class="form-control" placeholder="e.g. 11" id="salary_grade">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Steps</label>
                                <input type="number" class="form-control" placeholder="e.g. 5" id="step">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" placeholder="₱" id="basic_salary">
                            </div>
                        </div>
                    </div>

                    <!-- EARNINGS -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                            <h5 class="text-primary mb-0">Earnings</h5>
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
                        <div class="small text-muted">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
                        </div>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                            <h5 class="text-primary mb-0">Deductions</h5>
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
                        <div class="small text-muted">
                            Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-edit" id="btn_save">Save Changes</button>
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
                <h3 class="modal-title text-white">Create Payroll</h3>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
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
                <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-edit" id="btn_save_payroll">Save Changes</button>
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
                <h3 class="modal-title text-white">Append Employees</h3>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
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
                <div class="border rounded p-2" style="height: 55vh; overflow-y: auto;">
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
                <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-edit" id="btn_save_employees">Save Changes</button>
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
                <h3 class="modal-title text-white">Regular Payroll</h3>
                <button type="button" class="btn-close fs-1" data-bs-dismiss="modal"
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
                <button class="btn btn-secondary btn-view" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Close
                </button>
                <button class="btn btn-success btn-edit" id="btn_export_regular">
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
                <h3 class="modal-title text-white">Job Order Payroll</h3>
                <button type="button" class="btn-close fs-1" data-bs-dismiss="modal"
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
                <button class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success btn-edit" id="btn_export_jo">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
            </div>

        </div>
    </div>
</div>


