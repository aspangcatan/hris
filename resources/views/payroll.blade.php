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
                const tax_purpose  = $("#tax_purpose").val().trim();

                if (!title) {
                    toastr.error("Payroll title is required.", "Validation");
                    return;
                }
                if (!cutoff_id) {
                    toastr.error("Please select a cutoff period.", "Validation");
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
                        <td class="text-center">${fullName}</td>
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

        function downloadSummary(id) {
            window.location.href = "{{ url('/payroll/summary') }}/" + id;
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

        function renderRegularPayrollHeader(earningComponents, deductionComponents) {
            const earningHeaders = earningComponents.map(c => `<th class="text-end">${c.name}</th>`).join('');
            const deductionHeaders = deductionComponents.map(c => `<th class="text-end">${c.name}</th>`).join('');

            const headerHtml = `
                <tr class="text-center align-middle">
                    <th rowspan="2">No.</th>
                    <th rowspan="2">Family Name</th>
                    <th rowspan="2">First Name</th>
                    <th rowspan="2">Middle Name</th>
                    <th rowspan="2">Suffix</th>
                    <th rowspan="2">Designation</th>
                    <th rowspan="2">SG</th>
                    <th rowspan="2">Step</th>
                    <th rowspan="2" class="text-end">Basic Pay</th>
                    <th rowspan="2" class="text-end">Late/Absent Deduction</th>
                    <th colspan="${earningComponents.length}">Earnings</th>
                    <th colspan="${deductionComponents.length}">Deductions</th>
                    <th rowspan="2" class="text-end">Withholding Tax</th>
                    <th rowspan="2" class="text-end fw-bold">Total Deduction</th>
                    <th rowspan="2" class="text-end fw-bold">Net Pay</th>
                    <th rowspan="2" class="text-end">1st Half 10th</th>
                    <th rowspan="2" class="text-end">2nd Half 25th</th>
                </tr>
                <tr class="text-center align-middle">
                    ${earningHeaders}
                    ${deductionHeaders}
                </tr>`;
            $("#regular_payroll_thead").html(headerHtml);

            const totalColspan = 10; // No. through Late/Absent Deduction
            const footerHtml = `
                <tr>
                    <td colspan="${totalColspan}" class="text-end">TOTAL:</td>
                    ${earningComponents.map(() => '<td></td>').join('')}
                    ${deductionComponents.map(() => '<td></td>').join('')}
                    <td class="text-end" id="total_withholding">0.00</td>
                    <td class="text-end" id="total_deduction">0.00</td>
                    <td class="text-end" id="total_net">0.00</td>
                    <td colspan="2"></td>
                </tr>`;
            $("#regular_payroll_tfoot").html(footerHtml);
        }

        function populateRegularRows(tbody, payload) {
            const { employees, earningComponents, deductionComponents, componentAmounts } = payload;
            renderRegularPayrollHeader(earningComponents, deductionComponents);

            let totalWithholding = 0;
            let totalDeduction = 0;
            let totalNet = 0;

            employees.forEach((item, index) => {
                totalWithholding += parseFloat(item.withholding_tax || 0);
                totalDeduction += parseFloat(item.deductions || 0);
                totalNet += parseFloat(item.net_pay || 0);

                const earningCells = earningComponents.map(c =>
                    `<td>${formatCurrency(componentAmountFor(componentAmounts, item.id, c.code))}</td>`
                ).join('');
                const deductionCells = deductionComponents.map(c =>
                    `<td>${formatCurrency(componentAmountFor(componentAmounts, item.id, c.code))}</td>`
                ).join('');

                const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.lname}</td>
                            <td>${item.fname}</td>
                            <td>${item.mname ?? ''}</td>
                            <td>${item.suffix ?? ''}</td>
                            <td>${item.designation ?? ''}</td>
                            <td>${item.salary_grade ?? ''}</td>
                            <td>${item.step ?? ''}</td>
                            <td>${formatCurrency(item.basic_salary)}</td>
                            <td>${formatCurrency(item.late_absent)}</td>
                            ${earningCells}
                            ${deductionCells}
                            <td>${formatCurrency(item.withholding_tax ?? "0.00")}</td>
                            <td class="fw-bold">${formatCurrency(item.deductions ?? "0.00")}</td>
                            <td class="text-danger fw-bold">${formatCurrency(item.net_pay)}</td>
                            <td>${formatCurrency(item.first_half_10th ?? "0.00")}</td>
                            <td>${formatCurrency(item.second_half_25th ?? "0.00")}</td>
                        </tr>`;
                tbody.append(row);
            });

            $("#total_withholding").text(formatCurrency(totalWithholding));
            $("#total_deduction").text(formatCurrency(totalDeduction));
            $("#total_net").text(formatCurrency(totalNet));
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
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="downloadSummary(${item.id})">Print Summary</a></li>`);
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
                    (data.data || []).forEach((item) => {
                        const title = item.title || `Cutoff ${item.period_start} - ${item.period_end}`;
                        $select.append(
                            `<option value="${item.id}" data-start="${item.period_start}" data-end="${item.period_end}" data-title="${title}">` +
                            `${title} (${item.period_start} to ${item.period_end})</option>`
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
