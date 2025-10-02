<div class="modal fade" id="payroll_settings_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <!-- Sticky Header -->
            <div class="modal-header bg-primary text-white sticky-top">
                <h3 class="modal-title">Payroll Settings</h3>
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

                    <!-- BENEFITS -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <h5 class="text-success border-bottom pb-2">Benefits</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">PERA</label>
                                <input type="number" class="form-control" id="pera">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">RATA</label>
                                <input type="number" class="form-control" id="rata">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Subistence</label>
                                <input type="number" class="form-control" id="subsistence">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Laundry</label>
                                <input type="number" class="form-control" id="laundry">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Longevity</label>
                                <input type="number" class="form-control" id="longevity">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cellphone Allowance</label>
                                <input type="number" class="form-control" id="cellphone_allowance">
                            </div>
                        </div>
                    </div>

                    <!-- DEDUCTIONS -->
                    <div class="mb-4 border p-3 rounded shadow-sm">
                        <h5 class="text-danger border-bottom pb-2">Deductions</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">GSIS PS</label>
                                <input type="number" class="form-control" id="gsis_ps">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS GS</label>
                                <input type="number" class="form-control" id="gsis_gs">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS EC</label>
                                <input type="number" class="form-control" id="gsis_ec">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">HDMF (PagIbig)</label>
                                <input type="number" class="form-control" id="hdmf">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PHIC</label>
                                <input type="number" class="form-control" id="phic">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PAGIBIG CAL</label>
                                <input type="number" class="form-control" id="pagibig_cal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PAGIBIG MPL</label>
                                <input type="number" class="form-control" id="pagibig_mpl">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PAGIBIG MP2</label>
                                <input type="number" class="form-control" id="pagibig_mp2">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PAGIBIG MP2 2nd</label>
                                <input type="number" class="form-control" id="pagibig_mp2_2nd">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PAGIBIG MP2 3rd</label>
                                <input type="number" class="form-control" id="pagibig_mp2_3rd">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS Consoloan</label>
                                <input type="number" class="form-control" id="gsis_consoloan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS Emergency Loan</label>
                                <input type="number" class="form-control" id="gsis_emergency_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS Educational Loan</label>
                                <input type="number" class="form-control" id="gsis_educational_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS Policy Loan</label>
                                <input type="number" class="form-control" id="gsis_policy_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS OPT_Life</label>
                                <input type="number" class="form-control" id="gsis_opt_life_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS GFAL</label>
                                <input type="number" class="form-control" id="gsis_gfal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS MPL</label>
                                <input type="number" class="form-control" id="gsis_mpl">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS CPL</label>
                                <input type="number" class="form-control" id="gsis_cpl">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">GSIS MPL Lite</label>
                                <input type="number" class="form-control" id="gsis_mpl_lite">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">CFI Coop</label>
                                <input type="number" class="form-control" id="cfi_coop">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">1st Valley Bank</label>
                                <input type="number" class="form-control" id="first_valley_bank">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">TDHEA</label>
                                <input type="number" class="form-control" id="tdhea">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">DBP Loan</label>
                                <input type="number" class="form-control" id="dbp_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">UCPB Loan</label>
                                <input type="number" class="form-control" id="ucpb_loan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">MEDS Payable</label>
                                <input type="number" class="form-control" id="meds_payable">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">SSS Deduction</label>
                                <input type="number" class="form-control" id="sss_deduction">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_save">Save Changes</button>
            </div>
        </div>
    </div>
</div>


<!-- Create Payroll Modal -->
<div class="modal fade" id="create_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header bg-primary text-white sticky-top">
                <h3 class="modal-title">Create Payroll</h3>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_save_payroll">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="append_employee_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header bg-primary text-white sticky-top">
                <h3 class="modal-title">Append Employees</h3>
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
            </div>

            <!-- Footer with Save Button -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_save_employees">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="regular_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header bg-light border-bottom sticky-top shadow-sm">
                <h3 class="modal-title">Regular Payroll</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
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
            <div class="modal-footer bg-light">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Close
                </button>
                <button class="btn btn-outline-primary" id="btn_export_regular">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="jo_payroll_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">

            <!-- Sticky Header -->
            <div class="modal-header bg-light border-bottom sticky-top shadow-sm">
                <h3 class="modal-title">Job Order Payroll</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
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
            <div class="modal-footer border-top">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-outline-primary" id="btn_export_jo">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
            </div>

        </div>
    </div>
</div>


