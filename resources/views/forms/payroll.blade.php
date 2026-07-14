<style>
    .payroll-tab-board {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
    }

    .payroll-tab-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }

    .payroll-tab-head h4 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
    }

    .payroll-tab-head span {
        font-size: 0.9rem;
        color: #64748b;
    }

    .payroll-tab-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1rem;
        margin-bottom: 1.25rem;
    }

    .payroll-tab-section .form-control {
        border-radius: 12px;
        border: 1px solid #d8e1ee;
        background: #fff;
    }

    .payroll-tab-table-shell {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
    }

    .payroll-tab-table-shell table {
        margin-bottom: 0;
    }

    .payroll-tab-table-shell table thead th {
        background: #f8fafc;
        color: #0f172a;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .payroll-tab-table-shell table tbody td {
        color: #1e293b;
        font-weight: 500;
        vertical-align: middle;
    }
</style>

<div class="text-white bg-danger rounded p-2 mt-5 d-none" id="address_error">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>There are some errors, please correct them below:</div>
    </div>
    <ul class="error_list"></ul>
</div>

<div id="payroll">
    <div class="payroll-tab-board">
        <div class="payroll-tab-head">
            <div>
                <h4>Payroll Details</h4>
                <span>Manage salary inputs and component overrides for this employee.</span>
            </div>
        </div>

        <div class="payroll-tab-section">
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
                    <input type="number" class="form-control" placeholder="â‚±" id="basic_salary">
                </div>
            </div>
        </div>

        <div class="payroll-tab-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                <h5 class="text-primary mb-0">Earnings</h5>
                <span class="small text-muted">Global components apply by default unless overridden. Non-global components apply only if given an amount here.</span>
            </div>
            <div class="table-responsive payroll-tab-table-shell">
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
            <div class="small text-muted mt-2">
                Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
            </div>
        </div>

        <div class="payroll-tab-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom pb-2 mb-3">
                <h5 class="text-primary mb-0">Deductions</h5>
                <span class="small text-muted">Global components apply by default unless overridden. Non-global components apply only if given an amount here.</span>
            </div>
            <div class="table-responsive payroll-tab-table-shell">
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
            <div class="small text-muted mt-2">
                Global: leave Amount blank to use the default. Non-global: leave Amount blank to leave it unassigned for this employee. Rate Override % only applies to percentage-based components and replaces the catalog's rate while still computing off basic salary.
            </div>
        </div>

        <div class="text-end">
            <button type="button" class="btn btn-success btn-edit" id="btn_save_payroll">Save Changes</button>
        </div>
    </div>
</div>
