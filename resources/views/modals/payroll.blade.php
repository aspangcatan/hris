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

                <!-- EMPLOYEE -->
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

