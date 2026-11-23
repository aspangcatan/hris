@extends('master')
@section('content')
    <style>
        .payroll-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .payroll-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .payroll-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .payroll-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .payroll-filter {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .payroll-filter .form-control,
        .payroll-filter .form-select {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .payroll-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        /* Bootstrap couples overflow-x:auto (table-responsive) with an implicit
           overflow-y:auto, which clips the row-action dropdown menu. Temporarily
           lift the clip while a dropdown inside this table is open. */
        .payroll-table-shell.dropdown-open {
            overflow: visible;
        }

        .payroll-table-shell table {
            margin-bottom: 0;
        }

        .payroll-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .payroll-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        /* Regular Payroll review: subtotal columns and amounts with a computation note. */
        #regular_payroll_modal .payroll-subtotal {
            background-color: #eef2ff !important;
            font-weight: 700;
        }
        #regular_payroll_modal .payroll-note {
            text-decoration: underline dotted #64748b;
            text-underline-offset: 3px;
            cursor: help;
        }
        #regular_payroll_modal .payroll-check {
            cursor: help;
        }
        #regular_payroll_modal .payroll-employer {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
        }
        #regular_payroll_modal .payroll-breakdown-link {
            font-weight: 600;
            text-decoration: underline;
        }
        .payroll-summary-box {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.4rem 0.75rem;
            background: #fff;
            min-width: 9rem;
        }
        .payroll-summary-box .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .payroll-summary-box .value {
            font-weight: 700;
        }
        .payroll-summary-box.is-net .value {
            color: #dc2626;
        }
        .payroll-summary-box.is-employer .value {
            color: #64748b;
        }
        #payroll_breakdown_modal .payroll-subtotal {
            background-color: #eef2ff;
        }
    </style>

    <div class="payroll-board">
        <div class="payroll-head">
            <div class="payroll-head-title">
                <h4>Payroll Processing</h4>
                <span>Create and manage payroll periods for employees.</span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add">
                <i class="bi bi-plus-lg"></i>
                Create Payroll
            </button>
        </div>

        <div class="payroll-filter">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="month">
                            <option value="" selected disabled>Select month</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <label for="month">Month</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="year">
                            <option value="" selected disabled>Select year</option>
                            @for($year = 2023; $year <= \Carbon\Carbon::now()->format('Y'); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                        <label for="year">Year</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive payroll-table-shell">
                <table class="table table-bordered align-middle text-center">
                    <thead>
                    <tr>
                        <th style="width: 18%">Title</th>
                        <th style="width: 16%">Cutoff Period</th>
                        <th style="width: 9%">Period Start</th>
                        <th style="width: 9%">Period End</th>
                        <th style="width: 9%">Status</th>
                        <th style="width: 14%">Remarks</th>
                        <th style="width: 10%">Prepared By</th>
                        <th style="width: 10%">Created At</th>
                        <th style="width: 5%"></th>
                    </tr>
                    </thead>
                    <tbody id="payroll_list">
                    <tr>
                        <td colspan="8" class="text-center text-muted">No records found. Please select month & year.
                        </td>
                    </tr>
                    </tbody>
                </table>
        </div>
    </div>
    @include('modals.payroll')
@stop
@section('js')
    <script>
        let payroll_id = 0;
        let payrollMeta = {};

        $(document).ready(function () {
            getPayroll();
            loadTaxPurposes();
            loadCutoffPeriods();

            $("#tax_purpose").select2({
                dropdownParent: $("#create_payroll_modal"),
                placeholder: "Select tax purpose",
                allowClear: true,
                width: "100%"
            });

            // Row-action dropdowns are appended dynamically inside #payroll_list,
            // so bind via delegation. Lift the table shell's overflow clip while
            // a dropdown is open so it isn't cut off near the bottom of the table.
            $(document).on('show.bs.dropdown', '.payroll-table-shell .dropdown', function () {
                $(this).closest('.payroll-table-shell').addClass('dropdown-open');
            });
            $(document).on('hidden.bs.dropdown', '.payroll-table-shell .dropdown', function () {
                $(this).closest('.payroll-table-shell').removeClass('dropdown-open');
            });

            $("#filter_name").on("input", function () {
                filterEmployeeRows();
            });

            $("#btn_add").click(function () {
                $("#create_payroll_modal").modal("show");
            });

            $("#btn_save_employees").click(function () {
                const checked = [];
                $("#employee_list tr").each(function () {
                    const userId = $(this).data("userid");
                    if (!userId) {
                        return;
                    }

                    // Basic Pay is the one fixed, non-component input; every
                    // other adjustment column is read by its component code
                    // (set as data-code on the input), not by position.
                    const salaryAdjustment = $(this).find("[data-code='__SALARY__']").val();
                    const adjustments = {};
                    $(this).find("input.employee-adjustment[data-code]").not("[data-code='__SALARY__']").each(function () {
                        adjustments[$(this).data("code")] = $(this).val();
                    });

                    checked.push({
                        "userid": userId,
                        "salary_adjustment": salaryAdjustment,
                        "adjustments": adjustments
                    });
                });

                fetch('{{ route('savePayrollEmployee') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        "payroll_id": payroll_id,
                        "employees": checked
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            toastr.error(data.error, 'Error');
                            return;
                        }
                        if (data.missing_employee_ids) {
                            toastr.error("Missing cutoff summaries for some employees.", "Error");
                            return;
                        }
                        toastr.success(data.message || "Payroll computation completed.", 'Information');
                    }).catch(err => {
                    console.log(err);
                });

            });


            $("#btn_save_payroll").click(function () {
                const title        = $("#title").val().trim();
                const cutoff_id    = parseInt($("#cutoff_period").val()) || null;
                const payroll_type = $("#payroll_type").val();
                const rate_type    = $("#rate_type").val();
                const remarks      = $("#remarks").val().trim();
                const tax_purpose  = ($("#tax_purpose").val() || "").trim();
                const start_date   = $("#start_date").val();
                const end_date     = $("#end_date").val();

                if (!title) {
                    toastr.error("Payroll title is required.", "Validation");
                    return;
                }
                if (!cutoff_id) {
                    toastr.error("Please select a cutoff period.", "Validation");
                    return;
                }
                if (!start_date || !end_date || end_date < start_date) {
                    toastr.error("Enter a valid Start Date and End Date for the period paid.", "Validation");
                    return;
                }
                if (!tax_purpose) {
                    toastr.error("Please select a Tax Purpose.", "Validation");
                    return;
                }

                fetch('{{ route('savePayroll') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        title:        title,
                        cutoff_id:    cutoff_id,
                        payroll_type: payroll_type,
                        rate_type:    rate_type,
                        remarks:      remarks || null,
                        tax_purpose:  tax_purpose || null,
                        start_date:   start_date,
                        end_date:     end_date,
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.errors || data.error) {
                            const msg = data.error || Object.values(data.errors)[0]?.[0] || "Failed to create payroll.";
                            toastr.error(msg, "Error");
                            return;
                        }
                        getPayroll();
                        $("#create_payroll_modal").modal("hide");
                        toastr.success(data.message, 'Success');
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Failed to create payroll.", "Error");
                    });
            });

            $("#btn_export_regular").click(function () {
                window.location.href = "{{ url('/payroll/export') }}/" + payroll_id;
            });

        });

        function loadTaxPurposes() {
            fetch('{{ route('getPayrollTaxRules') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    const purposes = [...new Set((data.data || []).map(item => item.purpose).filter(Boolean))];
                    const $select = $("#tax_purpose");
                    $select.empty().append('<option value="">Select tax purpose</option>');
                    purposes.forEach(purpose => {
                        $select.append(`<option value="${purpose}">${purpose}</option>`);
                    });
                    $select.val("").trigger("change");
                })
                .catch(err => console.error(err));
        }

        function loadCutoffEmployees(payrollId) {
            const $tbody = $('#employee_list');
            $tbody.empty();

            fetch(`{{ url('/payroll') }}/${payrollId}/cutoff-employees`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load employees for this cutoff.", "Error");
                        return;
                    }

                    const columns = data.columns || [];
                    renderAdjustmentColumnHeaders(columns);

                    (data.data || []).forEach(emp => {
                        const middleInitial = emp.mname ? emp.mname.charAt(0) + '.' : '';
                        const suffix = emp.suffix ?? '';
                        const fullName = `${emp.lname}, ${emp.fname} ${middleInitial} ${suffix}`.trim();

                        const adjustmentCells = columns.map(col => `
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number"
                                   data-code="${col.code}" value="${(emp.adjustments || {})[col.code] ?? 0}" />
                        </td>`).join('');

                        const row = `
                    <tr data-name="${fullName.toLowerCase()}" data-userid="${emp.employee_id}">
                        <td class="text-start text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary me-2 btn-employee-components"
                                    data-userid="${emp.employee_id}" data-name="${$('<div>').text(fullName).html()}"
                                    title="View earnings and deductions set for this employee">
                                <i class="bi bi-list-columns-reverse"></i>
                            </button>${$('<div>').text(fullName).html()}
                        </td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number"
                                   data-code="__SALARY__" value="${emp.salary_adjustment ?? 0}" />
                        </td>
                        ${adjustmentCells}
                    </tr>
                    `;

                        $tbody.append(row);
                    });
                    filterEmployeeRows();
                })
                .catch(err => {
                    console.error("Error fetching cutoff employees:", err);
                    toastr.error("Failed to load employees for this cutoff.", "Error");
                });
        }

        // ---- Earnings & deductions reference (Append Employees) ----
        const ROUTE_EMPLOYEE_COMPONENTS = "{{ url('/payroll') }}";

        $(document).on("click", ".btn-employee-components", function () {
            openEmployeeComponents($(this).data("userid"), $(this).data("name"));
        });

        $(document).on("shown.bs.modal", "#employee_components_modal", function () {
            $(".modal-backdrop").last().addClass("employee-components-backdrop");
        });

        async function openEmployeeComponents(employeeId, name) {
            const esc = v => $('<div>').text(v == null ? '' : String(v)).html();
            const money = v => formatCurrency(v || 0);
            $("#ec_employee_name").text(name || "Earnings & Deductions");
            $("#ec_source").text("What this employee's payroll starts from.");
            $("#ec_body").html('<div class="text-center text-muted py-4">Loading...</div>');
            bootstrap.Modal.getOrCreateInstance(document.getElementById("employee_components_modal")).show();

            try {
                const res = await fetch(`${ROUTE_EMPLOYEE_COMPONENTS}/${payroll_id}/employees/${employeeId}/components`, {headers: {"Accept": "application/json"}});
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || "Could not load the components.");

                $("#ec_source").text(data.source === "snapshot"
                    ? "As saved in this payroll's cutoff snapshot."
                    : "From the employee's current Payroll Settings (this cutoff has no snapshot).");

                const table = (title, rows, total, flagLabel) => `
                    <div class="ec-table">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>${title}</th><th class="text-center" style="width:22%">${flagLabel}</th><th class="text-end" style="width:25%">Amount</th></tr></thead>
                            <tbody>${rows.length ? rows.map(r => `
                                <tr>
                                    <td>${esc(r.name)} <span class="text-muted small">${esc(r.code)}</span></td>
                                    <td class="text-center">${r.taxable ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-muted">—</span>'}</td>
                                    <td class="text-end">${money(r.amount)}</td>
                                </tr>`).join("") : `<tr><td colspan="3" class="text-center text-muted py-3">None set.</td></tr>`}
                            </tbody>
                            <tfoot><tr><td colspan="2">Total ${title.toLowerCase()}</td><td class="text-end">${money(total)}</td></tr></tfoot>
                        </table>
                    </div>`;

                $("#ec_body").html(`
                    <div class="ec-summary">
                        <div class="ec-stat"><small>Basic salary</small><strong>${money(data.basic_salary)}</strong></div>
                        <div class="ec-stat"><small>Earnings</small><strong class="text-success">${money(data.total_earnings)}</strong></div>
                        <div class="ec-stat"><small>Deductions</small><strong class="text-danger">${money(data.total_deductions)}</strong></div>
                    </div>
                    ${table("Earnings", data.earnings || [], data.total_earnings, "Taxable")}
                    ${table("Deductions", data.deductions || [], data.total_deductions, "Before tax")}
                    <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Withholding tax and late/absence deductions are computed when the payroll is generated, so they are not listed here.</div>
                `);
            } catch (err) {
                $("#ec_body").html(`<div class="alert alert-danger mb-0">${esc(err.message || "Could not load the components.")}</div>`);
            }
        }

        function renderAdjustmentColumnHeaders(columns) {
            const $row = $("#adjustment_columns_row");
            $row.empty().append("<th>Basic Pay</th>");
            columns.forEach(col => {
                $row.append(`<th>${col.name}</th>`);
            });
            $("#adjustment_group_header").attr("colspan", 1 + columns.length);
        }

        function formatCurrency(value) {
            return parseFloat(value).toLocaleString('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            });
        }

        function formatStatus(status) {
            const key = (status || 'draft').toLowerCase();
            const labels = {
                draft: 'Draft',
                computed: 'Computed',
                for_approval: 'For Approval',
                approved: 'Approved',
                rejected: 'Rejected',
                released: 'Released'
            };
            const colors = {
                draft: 'secondary',
                computed: 'info',
                for_approval: 'warning',
                approved: 'success',
                rejected: 'danger',
                released: 'primary'
            };

            const label = labels[key] || key;
            const color = colors[key] || 'secondary';
            return `<span class="badge bg-${color}">${label}</span>`;
        }

        function submitForApproval(id) {
            fetch('{{ route('submitPayrollForApproval') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({payroll_id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        toastr.error(data.error, 'Error');
                        return;
                    }
                    toastr.success(data.message || 'Submitted for approval.', 'Information');
                    getPayroll();
                })
                .catch(err => console.error(err));
        }

        function approvePayroll(id) {
            fetch('{{ route('payrollApprovalAction') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    payroll_id: id,
                    status: 'approved',
                    remarks: ''
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        toastr.error(data.error, 'Error');
                        return;
                    }
                    toastr.success(data.message || 'Payroll approved.', 'Information');
                    getPayroll();
                })
                .catch(err => console.error(err));
        }

        function rejectPayroll(id) {
            const remarks = prompt("Enter rejection remarks:");
            if (remarks === null) return;

            fetch('{{ route('payrollApprovalAction') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    payroll_id: id,
                    status: 'rejected',
                    remarks: remarks
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        toastr.error(data.error, 'Error');
                        return;
                    }
                    toastr.success(data.message || 'Payroll rejected.', 'Information');
                    getPayroll();
                })
                .catch(err => console.error(err));
        }

        function releasePayroll(id) {
            fetch('{{ route('releasePayroll') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({payroll_id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        toastr.error(data.error, 'Error');
                        return;
                    }
                    toastr.success(data.message || 'Payroll released.', 'Information');
                    getPayroll();
                })
                .catch(err => console.error(err));
        }

        function downloadPayslip(id) {
            window.location.href = "{{ url('/payroll/payslip') }}/" + id;
        }


        function viewPayroll(id, type) {
            payroll_id = id;
            const config = {
                Regular: {
                    tbodyId: '#payroll_employee_list',
                    modalId: '#regular_payroll_modal',
                    populate: populateRegularRows,
                    colspan: 72 // adjust based on your total columns
                },
                "Job Order": {
                    tbodyId: '#jo_payroll_list',
                    modalId: '#jo_payroll_modal',
                    populate: populateJobOrderRows,
                    colspan: 20 // adjust based on your total columns
                }
            };

            const settings = config[type];
            if (!settings) {
                console.error(`Invalid payroll type: ${type}`);
                return;
            }

            fetch(`{{ route('getPayrollEmployee') }}?payroll_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = $(settings.tbodyId);
                    tbody.empty();

                    if (!data || !data.employees || data.employees.length === 0) {
                        toastr.error("There are no employee assigned", "Information");
                        return;
                    }

                    $(settings.modalId).modal('show');
                    settings.populate(tbody, data);
                })
                .catch(error => {
                    console.error(error);
                    $(settings.tbodyId).html(`<tr><td colspan="${settings.colspan}" class="text-center text-danger">An error occurred while loading data.</td></tr>`);
                });
        }


        function assignEmployee(id) {
            payroll_id = id;
            const meta = payrollMeta[id] || {};
            $("#preview_cutoff_start").text(meta.cutoff_start_date || meta.start_date || "-");
            $("#preview_cutoff_end").text(meta.cutoff_end_date || meta.end_date || "-");
            $("#filter_name").val("");
            loadCutoffEmployees(id);
            $("#append_employee_modal").modal("show");
        }

        function componentAmountFor(componentAmounts, employeeId, code) {
            const row = componentAmounts && componentAmounts[employeeId];
            return (row && row[code]) || 0;
        }

        function escapeAttr(text) {
            return String(text ?? "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }

        /**
         * Regular Payroll columns in computation order, each subtotal right after the
         * amounts it adds up, so a reviewer can check every step by eye:
         * Basic - Late/Absent = Basic Earned; + earnings = Gross; mandatory (before
         * tax) -> Taxable Income -> Withholding Tax; + other deductions = Total
         * Deduction; Gross - Total Deduction = Net = 10th + 25th.
         * kind: text | money (totalled) | subtotal (totalled, highlighted) | check
         */
        function regularPayrollColumns(payload) {
            const { earningComponents, mandatoryComponents, otherDeductionComponents, componentAmounts } = payload;
            const review = item => (payload.review || {})[item.id] || {};
            const amount = code => item => componentAmountFor(componentAmounts, item.id, code);
            const num = v => parseFloat(v || 0);

            const columns = [
                {group: null, header: "No.", kind: "text", value: (item, i) => i + 1},
                {group: null, header: "Family Name", kind: "text",
                    value: item => `<a href="#" class="payroll-breakdown-link" data-id="${item.id}" title="Open this employee's breakdown">${item.lname}</a>`},
                {group: null, header: "First Name", kind: "text", value: item => item.fname},
                {group: null, header: "Middle Name", kind: "text", value: item => item.mname ?? ""},
                {group: null, header: "Suffix", kind: "text", value: item => item.suffix ?? ""},
                {group: null, header: "Designation", kind: "text", value: item => item.designation ?? ""},
                {group: null, header: "SG", kind: "text", value: item => item.salary_grade ?? ""},
                {group: null, header: "Step", kind: "text", value: item => item.step ?? ""},
                {group: "Basic", header: "Basic Pay", kind: "money", value: item => num(item.basic_salary)},
                {group: "Basic", header: "Less: Late / Absent / LWOP", kind: "money", value: item => num(item.late_absent),
                    note: item => (review(item).notes || {}).late_absent},
                {group: "Basic", header: "Basic Earned", kind: "subtotal", value: item => num(review(item).basic_earned),
                    note: () => "Basic Pay − Late / Absent / LWOP"},
            ];
            earningComponents.forEach(c => columns.push({
                group: "Earnings", header: c.name, kind: "money", value: amount(c.code),
                note: item => ((review(item).notes || {}).components || {})[c.code],
            }));
            columns.push({group: null, header: "GROSS PAY", kind: "subtotal", value: item => num(item.gross_pay),
                note: () => "Basic Earned + all earnings"});
            mandatoryComponents.forEach(c => columns.push({
                group: "Mandatory Deductions (before tax)", header: c.name, kind: "money", value: amount(c.code),
            }));
            columns.push({group: null, header: "Taxable Income", kind: "subtotal", value: item => num(review(item).taxable_income),
                note: () => "Basic Earned + taxable earnings − mandatory deductions"});
            columns.push({group: null, header: "Withholding Tax", kind: "money", value: item => num(item.withholding_tax),
                note: item => (review(item).notes || {}).withholding_tax});
            otherDeductionComponents.forEach(c => columns.push({
                group: "Loans & Other Deductions", header: c.name, kind: "money", value: amount(c.code),
            }));
            columns.push(
                {group: null, header: "TOTAL DEDUCTION", kind: "subtotal", value: item => num(item.deductions),
                    note: () => "Mandatory + Withholding Tax + loans & other deductions"},
                {group: null, header: "NET PAY", kind: "subtotal", net: true, value: item => num(item.net_pay),
                    note: () => "Gross Pay − Total Deduction"},
                {group: "Pay Dates", header: "1st Half 10th", kind: "money", value: item => num(item.first_half_10th)},
                {group: "Pay Dates", header: "2nd Half 25th", kind: "money", value: item => num(item.second_half_25th)},
            );
            (payload.employerShareComponents || []).forEach(c => columns.push({
                group: "Employer Shares (not deducted)", header: c.name, kind: "money", employer: true, value: amount(c.code),
                note: () => "Government share, for remittance. Not taken from the employee's pay.",
            }));
            columns.push(
                {group: null, header: "Net vs Last Payroll", kind: "compare", value: item => review(item).previous},
                {group: null, header: "Check", kind: "check", value: item => review(item)},
            );
            return columns;
        }

        function compareCell(previous, alertPercent) {
            if (!previous) {
                return `<td class="text-center text-muted" title="No earlier payroll for this employee">—</td>`;
            }
            const pct = previous.change_percent;
            const sign = previous.change > 0 ? "+" : "";
            const alert = pct !== null && Math.abs(pct) > alertPercent;
            const title = `Last payroll: ${previous.title} (${previous.start_date} to ${previous.end_date}), net ${formatCurrency(previous.net_pay)}`;
            return `<td class="text-end payroll-note ${alert ? "text-warning fw-bold" : ""}" title="${escapeAttr(title)}">
                        ${sign}${formatCurrency(previous.change)}<div class="small">${pct === null ? "" : sign + pct + "%"}</div></td>`;
        }

        function renderPayrollSummary(payload, totals) {
            const meta = payload.meta || {};
            const range = (a, b) => (a && b) ? `${moment(a).format("MMM D")} – ${moment(b).format("MMM D, YYYY")}` : "—";
            const box = (label, value, extra = "") => `
                <div class="payroll-summary-box ${extra}"><div class="label">${label}</div><div class="value">${value}</div></div>`;
            $("#regular_payroll_summary").html(`
                <div class="fw-bold mb-2">${meta.title ?? ""} <span class="badge bg-secondary ms-1 text-capitalize">${String(meta.status ?? "").replace("_", " ")}</span></div>
                <div class="d-flex flex-wrap gap-2">
                    ${box("Pay period", range(meta.start_date, meta.end_date))}
                    ${box("DTR (cutoff) period", range(meta.cutoff_start_date, meta.cutoff_end_date))}
                    ${box("Tax purpose", meta.tax_purpose ?? "—")}
                    ${box("Employees", payload.employees.length)}
                    ${box("Total gross", formatCurrency(totals.gross))}
                    ${box("Total deductions", formatCurrency(totals.deductions))}
                    ${box("Total net", formatCurrency(totals.net), "is-net")}
                    ${box("Employer shares", formatCurrency(totals.employer), "is-employer")}
                </div>`);
        }

        function showEmployeeBreakdown(payrollEmployeeId) {
            const payload = window.regularPayrollPayload || {};
            const item = (payload.employees || []).find(e => String(e.id) === String(payrollEmployeeId));
            const review = (payload.review || {})[payrollEmployeeId];
            if (!item || !review) return;

            const lines = review.lines || [];
            const section = (title, type, filter = () => true) => {
                const rows = lines.filter(l => l.type === type && filter(l));
                if (!rows.length) return "";
                return `<tr class="table-light"><th colspan="3">${title}</th></tr>` + rows.map(l => `
                    <tr><td>${l.name}${l.taxable && type === "earning" ? ' <span class="badge bg-light text-dark border">taxable</span>' : ""}${l.taxable && type === "deduction" ? ' <span class="badge bg-light text-dark border">before tax</span>' : ""}</td>
                        <td class="small text-muted">${l.source ?? ""}</td><td class="text-end">${formatCurrency(l.amount)}</td></tr>`).join("");
            };
            const total = (label, value, cls = "") => `<tr class="fw-bold ${cls}"><td>${label}</td><td></td><td class="text-end">${formatCurrency(value)}</td></tr>`;
            const notes = review.notes || {};

            $("#payroll_breakdown_title").text(`${item.lname}, ${item.fname} ${item.mname ?? ""}`.trim());
            $("#payroll_breakdown_sub").text(`${item.designation ?? ""} · SG ${item.salary_grade ?? "?"}-${item.step ?? "?"}`);
            $("#payroll_breakdown_body").html(`
                <table class="table table-sm align-middle mb-3">
                    <thead><tr><th>Item</th><th>Where it came from</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Basic Pay</td><td class="small text-muted">${review.basic_source ?? ""}</td><td class="text-end">${formatCurrency(item.basic_salary)}</td></tr>
                        <tr><td>Less: Late / Absent / LWOP</td><td class="small text-muted">${notes.late_absent ?? ""}</td><td class="text-end">(${formatCurrency(item.late_absent)})</td></tr>
                        ${total("Basic Earned", review.basic_earned, "payroll-subtotal")}
                        ${section("Earnings", "earning")}
                        ${total("GROSS PAY", item.gross_pay, "payroll-subtotal")}
                        ${section("Mandatory Deductions (before tax)", "deduction", l => l.taxable)}
                        ${total("Taxable Income", review.taxable_income, "payroll-subtotal")}
                        ${section("Withholding Tax", "deduction", l => l.code === "WITHHOLDING_TAX")}
                        ${section("Loans & Other Deductions", "deduction", l => !l.taxable && l.code !== "WITHHOLDING_TAX")}
                        ${total("TOTAL DEDUCTION", item.deductions, "payroll-subtotal")}
                        ${total("NET PAY", item.net_pay, "payroll-subtotal text-danger")}
                        <tr><td>1st Half (10th) / 2nd Half (25th)</td><td></td><td class="text-end">${formatCurrency(item.first_half_10th)} / ${formatCurrency(item.second_half_25th)}</td></tr>
                        ${section("Employer Shares (not deducted from pay)", "employer_share")}
                    </tbody>
                </table>
                ${review.previous ? `<div class="small mb-2"><strong>Last payroll:</strong> ${review.previous.title} — net ${formatCurrency(review.previous.net_pay)}
                    (${review.previous.change > 0 ? "+" : ""}${formatCurrency(review.previous.change)}${review.previous.change_percent === null ? "" : ", " + review.previous.change_percent + "%"})</div>` : ""}
                ${(review.warnings || []).length ? `<div class="alert alert-warning small mb-0"><strong>To double-check:</strong><ul class="mb-0">${review.warnings.map(w => `<li>${w}</li>`).join("")}</ul></div>`
                    : `<div class="alert alert-success small mb-0">No warnings for this employee.</div>`}`);
            $("#payroll_breakdown_modal").modal("show");
        }

        $(document).on("click", ".payroll-breakdown-link", function (e) {
            e.preventDefault();
            showEmployeeBreakdown($(this).data("id"));
        });
        // The breakdown opens over the payroll window; closing it must keep that window scrollable.
        $(document).on("hidden.bs.modal", "#payroll_breakdown_modal", function () {
            if ($("#regular_payroll_modal").hasClass("show")) {
                $("body").addClass("modal-open");
            }
        });

        function checkCell(review) {
            const checks = review.checks || {};
            const failed = [];
            if (checks.net_balanced === false) failed.push("Gross Pay − Total Deduction does not equal Net Pay.");
            if (checks.halves_balanced === false) failed.push("10th + 25th does not equal Net Pay.");
            const warnings = review.warnings || [];
            if (failed.length) {
                return `<span class="badge bg-danger" title="${escapeAttr(failed.concat(warnings).join("\n"))}"><i class="bi bi-x-circle"></i> Mismatch</span>`;
            }
            if (warnings.length) {
                return `<span class="badge bg-warning text-dark payroll-check" title="${escapeAttr(warnings.join("\n"))}"><i class="bi bi-exclamation-triangle"></i> ${warnings.length}</span>`;
            }
            return `<span class="badge bg-success" title="Gross − Deductions = Net, and 10th + 25th = Net"><i class="bi bi-check2"></i></span>`;
        }

        function populateRegularRows(tbody, payload) {
            payload.mandatoryComponents = payload.mandatoryComponents || [];
            payload.otherDeductionComponents = payload.otherDeductionComponents || payload.deductionComponents || [];
            window.regularPayrollPayload = payload; // for the per-employee breakdown
            const alertPercent = parseFloat((payload.meta || {}).net_change_alert_percent ?? 10);
            const columns = regularPayrollColumns(payload);
            const isLeftAligned = col => col.kind === "text" || col.kind === "check";

            // Header: group row (consecutive columns of a group share one cell), then column names.
            let groupRow = "", nameRow = "";
            for (let i = 0; i < columns.length;) {
                const group = columns[i].group;
                let span = 1;
                while (group && i + span < columns.length && columns[i + span].group === group) span++;
                if (group) {
                    groupRow += `<th colspan="${span}" class="text-center">${group}</th>`;
                    for (let j = i; j < i + span; j++) {
                        nameRow += `<th class="${isLeftAligned(columns[j]) ? "" : "text-end"} ${columns[j].kind === "subtotal" ? "payroll-subtotal" : ""} ${columns[j].employer ? "payroll-employer" : ""}">${columns[j].header}</th>`;
                    }
                } else {
                    const col = columns[i];
                    groupRow += `<th rowspan="2" class="${isLeftAligned(col) ? "" : "text-end"} ${col.kind === "subtotal" ? "payroll-subtotal" : ""}">${col.header}</th>`;
                }
                i += span;
            }
            $("#regular_payroll_thead").html(`<tr class="align-middle">${groupRow}</tr><tr class="align-middle">${nameRow}</tr>`);

            // Rows, keeping a total for every money column.
            const totals = columns.map(() => 0);
            let flagged = 0;
            payload.employees.forEach((item, index) => {
                const cells = columns.map((col, c) => {
                    const value = col.value(item, index);
                    if (col.kind === "text") return `<td>${value}</td>`;
                    if (col.kind === "check") {
                        if ((value.warnings || []).length || (value.checks || {}).net_balanced === false || (value.checks || {}).halves_balanced === false) flagged++;
                        return `<td class="text-center">${checkCell(value)}</td>`;
                    }
                    if (col.kind === "compare") return compareCell(value, alertPercent);
                    totals[c] += value;
                    const note = col.note ? col.note(item) : null;
                    const classes = ["text-end", col.kind === "subtotal" ? "payroll-subtotal" : "", col.net ? "text-danger" : "",
                        col.employer ? "payroll-employer" : "", note ? "payroll-note" : ""].join(" ");
                    return `<td class="${classes}" ${note ? `title="${escapeAttr(note)}"` : ""}>${formatCurrency(value)}</td>`;
                });
                tbody.append(`<tr>${cells.join("")}</tr>`);
            });

            // Footer: TOTAL label over the text columns, then every money column's total.
            const textCount = columns.findIndex(col => col.kind !== "text");
            const footer = columns.slice(textCount).map((col, k) => {
                const c = textCount + k;
                if (col.kind === "check" || col.kind === "compare") return `<td></td>`;
                return `<td class="text-end ${col.kind === "subtotal" ? "payroll-subtotal" : ""} ${col.employer ? "payroll-employer" : ""}">${formatCurrency(totals[c])}</td>`;
            }).join("");
            $("#regular_payroll_tfoot").html(`<tr><td colspan="${textCount}" class="text-end">TOTAL (${payload.employees.length}):</td>${footer}</tr>`);

            const totalOf = header => totals[columns.findIndex(col => col.header === header)] || 0;
            renderPayrollSummary(payload, {
                gross: totalOf("GROSS PAY"),
                deductions: totalOf("TOTAL DEDUCTION"),
                net: totalOf("NET PAY"),
                employer: columns.reduce((sum, col, c) => sum + (col.employer ? totals[c] : 0), 0),
            });

            $("#regular_payroll_flagged").text(flagged
                ? `${flagged} employee(s) flagged for double-checking — hover the ⚠ badge to see why.`
                : "All rows balance and no warnings.").toggleClass("text-warning", flagged > 0);
        }

        function populateJobOrderRows(tbody, payload) {
            payload.employees.forEach((item, index) => {
                const row = `
                        <tr>
                            <td class="sticky-col sticky-1">${index + 1}</td>
                            <td class="sticky-col sticky-2">${item.lname}</td>
                            <td class="sticky-col sticky-3">${item.fname}</td>
                            <td class="sticky-col sticky-4">${item.mname ?? ''}</td>
                            <td class="sticky-col sticky-5">${item.suffix ?? ''}</td>
                            <td>${item.designation}</td>
                            <td>${formatCurrency(item.basic_salary)}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>`;
                tbody.append(row);
            });
        }

        function getPayroll() {
            fetch('{{ route('getPayroll') }}')
                .then(response => response.json())
                .then(data => {
                    const tbody = $("#payroll_list");
                    tbody.empty(); // Clear old records

                    if (data.length === 0) {
                        tbody.append("<tr><td colspan='8' class='text-center'>No payroll records found.</td></tr>");
                        return;
                    }

                    data.forEach(item => {
                        payrollMeta[item.id] = item;
                        const actions = [];
                        if (item.can_assign) {
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="assignEmployee(${item.id})">Review &amp; Compute</a></li>`);
                        }
                        actions.push(`<li><a class="dropdown-item" href="#" onclick="viewPayroll(${item.id},'${item.payroll_type}')">View</a></li>`);

                        if (item.can_submit) {
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="submitForApproval(${item.id})">Submit for Approval</a></li>`);
                        }
                        if (item.can_approve) {
                            actions.push(`<li><a class="dropdown-item text-success" href="#" onclick="approvePayroll(${item.id})">Approve (${item.pending_approval_label})</a></li>`);
                            actions.push(`<li><a class="dropdown-item text-danger" href="#" onclick="rejectPayroll(${item.id})">Reject (${item.pending_approval_label})</a></li>`);
                        }
                        if (item.can_release) {
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="releasePayroll(${item.id})">Release Payroll</a></li>`);
                        }
                        if (item.can_generate_payslip) {
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="downloadPayslip(${item.id})">Download Payslip</a></li>`);
                        }

                        if (actions.length === 0) {
                            actions.push(`<li><span class="dropdown-item-text text-muted">No actions available</span></li>`);
                        }

                        const row = `
                        <tr>
                            <td>${item.title}</td>
                            <td>${item.cutoff_label || ''}</td>
                            <td>${moment(item.start_date).format('MM/DD/YYYY')}</td>
                            <td>${moment(item.end_date).format('MM/DD/YYYY')}</td>
                            <td>${formatStatus(item.status)}</td>
                            <td>${item.remarks || ''}</td>
                            <td>${item.prepared_by_name || ''}</td>
                            <td>${moment(item.created_at).format('MM/DD/YYYY hh:mm:ss A')}</td>
                            <td class="text-center position-relative">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu position-absolute" style="z-index: 9999; right: 0; top: 100%;">
                                        ${actions.join('')}
                                    </ul>
                                </div>
                            </td>
                        </tr>`;
                        tbody.append(row);
                    });
                })
                .catch(error => {
                    console.error("Error fetching payroll:", error);
                });
        }

        function filterEmployeeRows() {
            const search = ($("#filter_name").val() || "").toLowerCase();
            $("#employee_list tr").each(function () {
                const name = ($(this).data("name") || "").toString();
                if (!search || name.includes(search)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }


        function loadCutoffPeriods() {
            fetch('{{ route('getCutoffPeriods') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    const $select = $("#cutoff_period");
                    $select.empty().append("<option value=''>— Select a cutoff period —</option>");
                    // Only finalized cutoffs can be used; drafts show greyed out so
                    // HR knows to finalize them first, closed ones are hidden.
                    (data.data || []).forEach((item) => {
                        const status = (item.status || "draft").toLowerCase();
                        if (status === "closed") {
                            return;
                        }
                        const title = item.title || `Cutoff ${item.period_start} - ${item.period_end}`;
                        const isDraft = status !== "finalized";
                        // Payroll is always a whole month.
                        const [year, month, day] = item.period_start.split("-").map(Number);
                        const lastDay = new Date(year, month, 0).getDate();
                        const wholeMonth = day === 1 && item.period_end === `${item.period_start.slice(0, 8)}${String(lastDay).padStart(2, "0")}`;
                        const note = isDraft ? " - not finalized yet" : (wholeMonth ? "" : " - not a whole month");
                        $select.append(
                            `<option value="${item.id}" data-start="${item.period_start}" data-end="${item.period_end}" data-title="${title}"${note ? " disabled" : ""}>` +
                            `${title} (${item.period_start} to ${item.period_end})${note}</option>`
                        );
                    });
                })
                .catch(err => console.error(err));
        }

        $("#cutoff_period").change(function () {
            const $option = $(this).find(":selected");
            const periodStart = $option.data("start") || "";
            const periodEnd   = $option.data("end")   || "";
            $("#start_date").val(periodStart);
            $("#end_date").val(periodEnd);
        });
    </script>
@stop
