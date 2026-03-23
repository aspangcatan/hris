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

                    <!-- GLOBAL EARNINGS OVERRIDES -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                            <h5 class="text-primary mb-0">Global Earnings (Overrides)</h5>
                            <span class="small text-muted">Defaults apply to all employees unless overridden here.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">Earning</th>
                                    <th style="width: 12%;">Default</th>
                                    <th style="width: 16%;">Override Amount</th>
                                    <th style="width: 10%;">Exclude</th>
                                    <th style="width: 14%;">Effective From</th>
                                    <th style="width: 14%;">Effective To</th>
                                </tr>
                                </thead>
                                <tbody id="dynamic_components_earnings_body"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted">
                            Leave override blank to use the global default amount.
                        </div>
                    </div>

                    <!-- GLOBAL DEDUCTIONS OVERRIDES -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                            <h5 class="text-primary mb-0">Global Deductions (Overrides)</h5>
                            <span class="small text-muted">Defaults apply to all employees unless overridden here.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">Deduction</th>
                                    <th style="width: 12%;">Default</th>
                                    <th style="width: 16%;">Override Amount</th>
                                    <th style="width: 10%;">Exclude</th>
                                    <th style="width: 14%;">Effective From</th>
                                    <th style="width: 14%;">Effective To</th>
                                </tr>
                                </thead>
                                <tbody id="dynamic_components_deductions_body"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted">
                            Leave override blank to use the global default amount.
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

                    <!-- Payroll Period Start -->
                    <div class="col-md-6">
                        <label for="payroll_start" class="form-label">Payroll Period Start</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>

                    <!-- Payroll Period End -->
                    <div class="col-md-6">
                        <label for="payroll_end" class="form-label">Payroll Period End</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-12">
                        <label for="cutoff_period" class="form-label">Cutoff Period</label>
                        <select class="form-control" id="cutoff_period">
                            <option value="">Use payroll period dates</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cutoff Start</label>
                        <input type="date" class="form-control" id="cutoff_start_date" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cutoff End</label>
                        <input type="date" class="form-control" id="cutoff_end_date" readonly>
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
                        <input type="text" class="form-control" id="tax_purpose" list="tax_purpose_list"
                               placeholder="e.g. Payroll Monthly Salary">
                        <datalist id="tax_purpose_list"></datalist>
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
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="filter_division" class="form-label">Filter by Division</label>
                            <select id="filter_division" class="form-select"></select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="filter_section" class="form-label">Filter by Section</label>
                            <select id="filter_section" class="form-select"></select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="filter_status" class="form-label">Filter by Status</label>
                            <select id="filter_status" class="form-select">
                                <option value="" selected>Select status</option>
                                <option>Job Order</option>
                                <option>Permanent</option>
                                <option>Resigned</option>
                                <option>Retired</option>
                                <option>Temporary</option>
                                <option>EOT</option>
                                <option>COS</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="filter_name" class="form-label">Search Name</label>
                            <input type="text" class="form-control" id="filter_name" placeholder="Search employee">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-action btn-view w-100 mb-3" id="btn_preview_cutoff">
                            <i class="bi bi-eye me-1"></i>
                            Preview Cutoff Summary
                        </button>
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
                            <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" style="width: 4%;">
                                    <input class="form-check-input" type="checkbox" id="select_all"/>
                                </th>
                                <th rowspan="2" style="width: 35%">Full Name</th>
                                <th colspan="5">Adjustment</th>
                            </tr>
                            <tr>
                                <th>Basic Pay</th>
                                <th>Hazard</th>
                                <th>PERA</th>
                                <th>Subsistence</th>
                                <th>Laundry</th>
                            </tr>
                            </thead>
                            <tbody id="employee_list"></tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3 table-responsive shadow-sm rounded">
                    <table class="table table-sm table-bordered align-middle text-center">
                        <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Working Days</th>
                            <th>Days Present</th>
                            <th>Days Absent</th>
                            <th>Late (mins)</th>
                            <th>Undertime (mins)</th>
                        </tr>
                        </thead>
                        <tbody id="cutoff_preview_body">
                        <tr>
                            <td colspan="6" class="text-muted text-center">Preview cutoff summary to view totals.</td>
                        </tr>
                        </tbody>
                    </table>
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
                        <thead class="table-light">
                        <tr class="text-center align-middle">
                            <th class="sticky-col sticky-1">No.</th>
                            <th class="sticky-col sticky-2">Family Name</th>
                            <th class="sticky-col sticky-3">First Name</th>
                            <th class="sticky-col sticky-4">Middle Name</th>
                            <th class="sticky-col sticky-5">Suffix</th>
                            <th class="sticky-col sticky-6">MI</th>
                            <th class="sticky-col sticky-7">STAT</th>
                            <th>Division</th>
                            <th>Designation</th>
                            <th>zz</th>
                            <th>SG</th>
                            <th>Step</th>
                            <th>SG B</th>
                            <th class="text-end">SSL 6 Basic Pay</th>
                            <th class="text-end">Basic Pay Adj</th>
                            <th class="text-end">Basic Pay LWOP</th>
                            <th class="text-end">Final Basic Pay</th>
                            <th class="text-end">PERA</th>
                            <th class="text-end">RATA</th>
                            <th class="text-end">Subsistence</th>
                            <th class="text-end">Laundry</th>
                            <th class="text-end">Hazard</th>
                            <th class="text-end">Longevity</th>
                            <th class="text-end">Quarters Allowance</th>
                            <th class="text-end">Cellphone Allowance</th>
                            <th class="fw-bold text-end">Gross Pay</th>
                            <th class="text-end">Withholding Tax</th>
                            <th class="text-end">Withholding Tax (Adjustment)</th>
                            <th class="text-end">GSIS PS</th>
                            <th class="text-end">GSIS GS</th>
                            <th class="text-end">GSIS EC</th>
                            <th class="text-end">HDMF</th>
                            <th class="text-end">PHIC</th>
                            <th class="text-end">PAGIBIG CAL</th>
                            <th class="text-end">PAGIBIG MPL</th>
                            <th class="text-end">PAG-IBIG MP2</th>
                            <th class="text-end">PAG-IBIG MP2 2nd Account</th>
                            <th class="text-end">PAG-IBIG MP2 3rd Account</th>
                            <th class="text-end">GSIS Conso Loan</th>
                            <th class="text-end">GSIS Emergency Loan</th>
                            <th class="text-end">GSIS Educational Loan</th>
                            <th class="text-end">GSIS Policy Loan</th>
                            <th class="text-end">GSIS Opt_Life Loan</th>
                            <th class="text-end">GSIS GFAL</th>
                            <th class="text-end">GSIS MPL</th>
                            <th class="text-end">GSIS CPL</th>
                            <th class="text-end">GSIS MPL Lite</th>
                            <th class="text-end">CFI Coop</th>
                            <th class="text-end">1st Valley Bank</th>
                            <th class="text-end">TDHEA</th>
                            <th class="text-end">DBP Loan</th>
                            <th class="text-end">UCPB Loan</th>
                            <th class="text-end">Meds Payable</th>
                            <th class="text-end">PHIC Contri Adj</th>
                            <th class="text-end">PHIC Refund from E.O.</th>
                            <th class="text-end">Hazard Pay Adj</th>
                            <th class="text-end">Adj PERA</th>
                            <th class="text-end">Adj Subsistence</th>
                            <th class="text-end">Adj Laundry</th>
                            <th class="text-end">SSS Deduction</th>
                            <th class="text-end">SSS Adjustment</th>
                            <th class="text-end">GSIS PS LWOP (2%)</th>
                            <th class="text-end">GSIS GS LWOP (2%)</th>
                            <th class="text-end">GSIS PLREG Refund</th>
                            <th class="text-end">GSIS MPL Refund</th>
                            <th class="text-end">GSIS Emergency Refund</th>
                            <th class="text-end">GSIS Conso Loan Refund</th>
                            <th class="text-end">Subsistence Deduction from Leave</th>
                            <th class="text-end">2nd Half Adjustment (Resigned Employee)</th>
                            <th class="fw-bold text-end">Total Deduction</th>
                            <th class="fw-bold text-end">Net Pay</th>
                            <th class="text-end">1st Half 10th</th>
                            <th class="text-end">2nd Half 25th</th>
                        </tr>
                        </thead>
                        <tbody id="payroll_employee_list"></tbody>
                        <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="25" class="text-end">TOTAL:</td>
                            <td class="text-end" id="total_gross">0.00</td>
                            <td colspan="44"></td>
                            <td class="text-end" id="total_deduction">0.00</td>
                            <td class="text-end" id="total_net">0.00</td>
                            <td colspan="2"></td>
                        </tr>
                        </tfoot>
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


