<div class="text-white bg-danger rounded p-2 mt-5 d-none" id="address_error">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>There are some errors, please correct them below:</div>
    </div>
    <ul class="error_list"></ul>
</div>
<div id="payroll"><!-- EMPLOYEE -->
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

<div class="text-end mt-5">
    <button type="button" class="btn btn-success" id="btn_save_payroll">SAVE CHANGES</button>
</div>

