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
                        <th style="width: 20%">Title</th>
                        <th style="width: 10%">Period Start</th>
                        <th style="width: 10%">Period End</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 25%">Remarks</th>
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
        let selectedEmployees = [];
        let payroll_id = 0;
        let payrollMeta = {};
        let cutoffMeta = {};

        $(document).ready(function () {
            getPayroll();
            loadDivision();
            loadTaxPurposes();
            loadCutoffPeriods();

            $("#select_all").change(function () {
                if ($(this).prop("checked")) {
                    $(".employee-checkbox").prop("checked", true).change();
                } else {
                    $(".employee-checkbox").prop("checked", false).change();
                }
            });

            $("#filter_status").change(function () {
                getEmployee($("#filter_division").val(), $("#filter_section").val(), $("#filter_status").val());
            });

            $("#filter_division").change(function () {
                const division = $("#filter_division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        getEmployee($("#filter_division").val(), "", $("#filter_status").val());
                        $("#filter_section").empty();
                        $("#filter_section").append("<option value=''>Select Section</option>");
                        if (division == 0) {
                            $("#filter_section").addClass("d-none");
                        } else {
                            $("#filter_section").removeClass("d-none");
                        }

                        data.forEach(it => {
                            const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                            $("#filter_section").append(option);
                        });

                        updateCheckedStatus();
                    }).catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
            });

            $("body").on("change", ".employee-checkbox", function () {
                const value = $(this).val();
                const row = $(this).closest("tr");

                if ($(this).prop("checked")) {
                    // Only add if not already present
                    if (!selectedEmployees.some(emp => emp.userid === value)) {
                        selectedEmployees.push({
                            userid: value,
                            salary_adjustment: row.find(".employee-adjustment").eq(0).val(),
                            hazard_adjustment: row.find(".employee-adjustment").eq(1).val(),
                            pera_adjustment: row.find(".employee-adjustment").eq(2).val(),
                            subsistence_adjustment: row.find(".employee-adjustment").eq(3).val(),
                            laundry_adjustment: row.find(".employee-adjustment").eq(4).val(),
                        });
                    }
                } else {
                    // Remove from array
                    selectedEmployees = selectedEmployees.filter(emp => emp.userid !== value);
                }
            });

            $("body").on("input", ".employee-adjustment", function () {
                const row = $(this).closest("tr");
                const checkbox = row.find(".employee-checkbox");
                const value = checkbox.val();

                if (checkbox.prop("checked")) {
                    // Update existing record
                    const existing = selectedEmployees.find(emp => emp.userid === value);
                    if (existing) {
                        existing.salary_adjustment = row.find(".employee-adjustment").eq(0).val();
                        existing.hazard_adjustment = row.find(".employee-adjustment").eq(1).val();
                        existing.pera_adjustment = row.find(".employee-adjustment").eq(2).val();
                        existing.subsistence_adjustment = row.find(".employee-adjustment").eq(3).val();
                        existing.laundry_adjustment = row.find(".employee-adjustment").eq(4).val();
                    } else {
                        // If somehow not found but checkbox is checked, add it
                        selectedEmployees.push({
                            userid: value,
                            salary_adjustment: row.find(".employee-adjustment").eq(0).val(),
                            hazard_adjustment: row.find(".employee-adjustment").eq(1).val(),
                            pera_adjustment: row.find(".employee-adjustment").eq(2).val(),
                            subsistence_adjustment: row.find(".employee-adjustment").eq(3).val(),
                            laundry_adjustment: row.find(".employee-adjustment").eq(4).val(),
                        });
                    }
                }
            });

            $("#filter_name").on("input", function () {
                filterEmployeeRows();
            });


            $("#filter_section").change(function () {
                getEmployee($("#filter_division").val(), $("#filter_section").val(), $("#filter_status").val());
            });

            $("#btn_add").click(function () {
                $("#create_payroll_modal").modal("show");
            });

            $("#btn_save_employees").click(function () {
                const checked = [];
                $(".employee-checkbox:checked").each(function () {
                    // Get the user_id from the value attribute
                    var userId = $(this).val();

                    // Get the full name from data-name attribute
                    var fullName = $(this).data("name");

                    // Get the row of the current checkbox
                    var row = $(this).closest("tr");

                    // Collect the input values in that row (assuming order matters)
                    var inputValues = row.find("input[type='number']").map(function () {
                        return $(this).val();
                    }).get(); // get() returns the array

                    // Log or process the data
                    checked.push({
                        "userid": userId,
                        "salary_adjustment": inputValues[0],
                        "hazard_adjustment": inputValues[1],
                        "pera_adjustment": inputValues[2],
                        "subsistence_adjustment": inputValues[3],
                        "laundry_adjustment": inputValues[4]
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
                const title = $("#title").val().trim();
                const start = $("#start_date").val();
                const end = $("#end_date").val();
                const payroll_type = $("#payroll_type").val();
                const rate_type = $("#rate_type").val();
                const remarks = $("#remarks").val().trim();
                const tax_purpose = $("#tax_purpose").val().trim();
                const cutoff_start_date = $("#cutoff_start_date").val();
                const cutoff_end_date = $("#cutoff_end_date").val();
                const cutoff_title = ($("#cutoff_period").find(":selected").data("title") || "").trim();

                // Basic validation
                if (!title || !start || !end) {
                    alert("Please fill in all required fields.");
                    return;
                }

                // Optional: Validate that end date is after or equal to start date
                if (new Date(start) > new Date(end)) {
                    alert("End date cannot be earlier than start date.");
                    return;
                }

                // Prepare data
                const params = {
                    title: title,
                    start_date: start,
                    end_date: end,
                    payroll_type: payroll_type,
                    rate_type: rate_type,
                    remarks: remarks,
                    tax_purpose: tax_purpose || null,
                    cutoff_start_date: cutoff_start_date || null,
                    cutoff_end_date: cutoff_end_date || null,
                    cutoff_title: cutoff_title || null
                };

                fetch('{{ route('savePayroll') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                })
                    .then(res => res.json())
                    .then(data => {
                        getPayroll();
                        $("#create_payroll_modal").modal("hide");
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });

            $("#btn_preview_cutoff").click(function () {
                previewCutoffSummary();
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
                    const $list = $("#tax_purpose_list");
                    $list.empty();
                    purposes.forEach(purpose => {
                        $list.append(`<option value="${purpose}"></option>`);
                    });
                })
                .catch(err => console.error(err));
        }

        function getEmployee(division, section, type) {
            fetch(`{{ route('getEmployeeByDivisionSection') }}?division=${division}&section=${section}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    const $tbody = $('#employee_list');
                    $tbody.empty(); // Clear current list
                    $("#select_all").prop("checked", false);
                    data.results.forEach(emp => {
                        const middleInitial = emp.mname ? emp.mname.charAt(0) + '.' : '';
                        const suffix = emp.suffix ?? '';
                        const fullName = `${emp.lname}, ${emp.fname} ${middleInitial} ${suffix}`.trim();

                        const row = `
                    <tr data-name="${fullName.toLowerCase()}">
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input employee-checkbox" value="${emp.user_id}" data-name="${fullName}">
                        </td>
                        <td class="text-center">${fullName}</td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number" value="0.00" />
                        </td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number" value="0.00" />
                        </td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number" value="0.00" />
                        </td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number" value="0.00" />
                        </td>
                        <td>
                            <input class="form-control text-center employee-adjustment" type="number" value="0.00" />
                        </td>
                    </tr>
                    `;

                        $tbody.append(row);
                    });
                    updateCheckedStatus();
                    filterEmployeeRows();
                })
                .catch(err => {
                    console.error("Error fetching employees:", err);
                });
        }


        function loadDivision() {
            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    $("#filter_division").append("<option value=''>Select division</option>");
                    data.forEach(it => {
                        const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                        $("#filter_division").append(option);
                    });
                }).catch(err => {
                alert("Ooops, something went wrong. Please reload");
            });
        }

        function updateCheckedStatus() {
            selectedEmployees.forEach(emp => {
                // Find the checkbox for this employee
                const checkbox = $(".employee-checkbox[value='" + emp.userid + "']");
                const row = checkbox.closest("tr");

                // Check the checkbox
                checkbox.prop("checked", true);

                // Update the input fields
                row.find(".employee-adjustment").eq(0).val(emp.salary_adjustment || "0.00");
                row.find(".employee-adjustment").eq(1).val(emp.hazard_adjustment || "0.00");
                row.find(".employee-adjustment").eq(2).val(emp.pera_adjustment || "0.00");
                row.find(".employee-adjustment").eq(3).val(emp.subsistence_adjustment || "0.00");
                row.find(".employee-adjustment").eq(4).val(emp.laundry_adjustment || "0.00");
            });
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

                    if (!data || data.length === 0) {
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
            $("#append_employee_modal").modal("show");
        }

        function populateRegularRows(tbody, data) {
            data.forEach((item, index) => {
                const row = `
                        <tr>
                            <td class="sticky-col sticky-1">${index + 1}</td>
                            <td class="sticky-col sticky-2">${item.lname}</td>
                            <td class="sticky-col sticky-3">${item.fname}</td>
                            <td class="sticky-col sticky-4">${item.mname ?? ''}</td>
                            <td class="sticky-col sticky-5">${item.suffix ?? ''}</td>
                            <td class="sticky-col sticky-6">${item.mi ?? ''}</td>
                            <td class="sticky-col sticky-7">${item.stat ?? ''}</td>
                            <td>${item.division}</td>
                            <td>${item.section ?? ''}</td>
                            <td>${item.zz ?? ''}</td>
                            <td>${item.sg ?? ''}</td>
                            <td>${item.step ?? ''}</td>
                            <td>${item.sg_b ?? ''}</td>
                            <td>${formatCurrency(item.basic_salary)}</td>
                            <td>${formatCurrency(item.salary_adjustment)}</td>
                            <td>${formatCurrency(item.late_absent)}</td>
                            <td>${formatCurrency(item.final_basic_pay ?? "0.00")}</td>
                            <td>${formatCurrency(item.pera)}</td>
                            <td>${formatCurrency(item.rata)}</td>
                            <td>${formatCurrency(item.subsistence)}</td>
                            <td>${formatCurrency(item.laundry)}</td>
                            <td>${formatCurrency(item.hazard)}</td>
                            <td>${formatCurrency(item.longevity)}</td>
                            <td>${formatCurrency(item.quarters_allowance ?? "0.00")}</td>
                            <td>${formatCurrency(item.cellphone_allowance)}</td>
                            <td class="text-danger">${formatCurrency(item.gross_pay)}</td>
                            <td>${formatCurrency(item.withholding_tax ?? "0.00")}</td>
                            <td>${formatCurrency(item.withholding_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_ps)}</td>
                            <td>${formatCurrency(item.gsis_gs)}</td>
                            <td>${formatCurrency(item.gsis_ec)}</td>
                            <td>${formatCurrency(item.hdmf)}</td>
                            <td>${formatCurrency(item.phic)}</td>
                            <td>${formatCurrency(item.pagibig_cal)}</td>
                            <td>${formatCurrency(item.pagibig_mpl)}</td>
                            <td>${formatCurrency(item.pagibig_mp2)}</td>
                            <td>${formatCurrency(item.pagibig_mp2_2nd)}</td>
                            <td>${formatCurrency(item.pagibig_mp2_3rd)}</td>
                            <td>${formatCurrency(item.gsis_consoloan)}</td>
                            <td>${formatCurrency(item.gsis_emergency_loan)}</td>
                            <td>${formatCurrency(item.gsis_educational_loan)}</td>
                            <td>${formatCurrency(item.gsis_policy_loan)}</td>
                            <td>${formatCurrency(item.gsis_opt_life_loan)}</td>
                            <td>${formatCurrency(item.gsis_gfal)}</td>
                            <td>${formatCurrency(item.gsis_mpl)}</td>
                            <td>${formatCurrency(item.gsis_cpl)}</td>
                            <td>${formatCurrency(item.gsis_mpl_lite)}</td>
                            <td>${formatCurrency(item.cfi_coop)}</td>
                            <td>${formatCurrency(item.first_valley_bank)}</td>
                            <td>${formatCurrency(item.tdhea)}</td>
                            <td>${formatCurrency(item.dbp_loan)}</td>
                            <td>${formatCurrency(item.ucpb_loan)}</td>
                            <td>${formatCurrency(item.meds_payable)}</td>
                            <td>${formatCurrency(item.phic_contri_adj ?? "0.00")}</td>
                            <td>${formatCurrency(item.phic_refund_eo ?? "0.00")}</td>
                            <td>${formatCurrency(item.hazard_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.pera_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.subsistence_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.laundry_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.sss_deduction)}</td>
                            <td>${formatCurrency(item.sss_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_ps_lwop ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_gs_lwop ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_plreg_refund ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_mpl_refund ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_emergency_refund ?? "0.00")}</td>
                            <td>${formatCurrency(item.gsis_consoloan_refund ?? "0.00")}</td>
                            <td>${formatCurrency(item.subsistence_deduction_leave ?? "0.00")}</td>
                            <td>${formatCurrency(item.resigned_adjustment ?? "0.00")}</td>
                            <td>${formatCurrency(item.deductions ?? "0.00")}</td>
                            <td class="text-danger">${formatCurrency(item.net_pay)}</td>
                            <td>${formatCurrency(item.first_half_10th ?? "0.00")}</td>
                            <td>${formatCurrency(item.second_half_25th ?? "0.00")}</td>
                        </tr>`;
                tbody.append(row);
            });
        }

        function populateJobOrderRows(tbody, data) {
            console.log(data);
            data.forEach((item, index) => {
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
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="assignEmployee(${item.id})">Assign / Compute</a></li>`);
                        }
                        actions.push(`<li><a class="dropdown-item" href="#" onclick="viewPayroll(${item.id},'${item.payroll_type}')">View</a></li>`);

                        if (item.can_submit) {
                            actions.push(`<li><a class="dropdown-item" href="#" onclick="submitForApproval(${item.id})">Submit for Approval</a></li>`);
                        }
                        if (item.can_approve) {
                            actions.push(`<li><a class="dropdown-item text-success" href="#" onclick="approvePayroll(${item.id})">Approve (${item.pending_approval_role})</a></li>`);
                            actions.push(`<li><a class="dropdown-item text-danger" href="#" onclick="rejectPayroll(${item.id})">Reject (${item.pending_approval_role})</a></li>`);
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

        function previewCutoffSummary() {
            const meta = payrollMeta[payroll_id] || {};
            const startDate = meta.cutoff_start_date || meta.start_date;
            const endDate = meta.cutoff_end_date || meta.end_date;
            if (!startDate || !endDate) {
                toastr.error("Cutoff period is not set for this payroll.", "Validation");
                return;
            }

            const selected = $("#employee_list .employee-checkbox:checked").map(function () {
                return $(this).val();
            }).get();

            const params = new URLSearchParams();
            params.append("start_date", startDate);
            params.append("end_date", endDate);
            if (selected.length) {
                params.append("employees", selected.join(","));
            }

            fetch(`{{ route('getCutoffSummaryPreview') }}?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#cutoff_preview_body");
                    $tbody.empty();
                    if (!data.success || !data.data || !data.data.length) {
                        $tbody.append("<tr><td colspan='6' class='text-muted text-center'>No cutoff summaries found for this period.</td></tr>");
                        return;
                    }

                    data.data.forEach(item => {
                        const middleInitial = item.mname ? item.mname.charAt(0) + '.' : '';
                        const suffix = item.suffix ? ` ${item.suffix}` : '';
                        const fullName = `${item.lname}, ${item.fname} ${middleInitial}${suffix}`.trim();
                        const row = `
                            <tr>
                                <td>${fullName}</td>
                                <td>${item.total_working_days ?? 0}</td>
                                <td>${item.days_present ?? 0}</td>
                                <td>${item.days_absent ?? 0}</td>
                                <td>${item.late_minutes ?? 0}</td>
                                <td>${item.undertime_minutes ?? 0}</td>
                            </tr>`;
                        $tbody.append(row);
                    });
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to load cutoff summary.", "Error");
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
                    $select.empty().append("<option value=''>Use payroll period dates</option>");
                    cutoffMeta = {};
                    (data.data || []).forEach((item, index) => {
                        const title = item.remarks || `Cutoff ${item.period_start} - ${item.period_end}`;
                        const value = `${item.period_start}|${item.period_end}|${title}`;
                        cutoffMeta[value] = item;
                        $select.append(`<option value="${value}" data-title="${title}">${title} (${item.period_start} to ${item.period_end})</option>`);
                    });
                })
                .catch(err => console.error(err));
        }

        $("#cutoff_period").change(function () {
            const value = $(this).val();
            if (!value) {
                $("#cutoff_start_date").val("");
                $("#cutoff_end_date").val("");
                $("#start_date").prop("readonly", false);
                $("#end_date").prop("readonly", false);
                return;
            }
            const parts = value.split("|");
            $("#cutoff_start_date").val(parts[0] || "");
            $("#cutoff_end_date").val(parts[1] || "");
            $("#start_date").val(parts[0] || "").prop("readonly", true);
            $("#end_date").val(parts[1] || "").prop("readonly", true);
        });
    </script>
@stop
