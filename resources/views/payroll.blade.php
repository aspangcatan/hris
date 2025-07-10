@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">📊 Create Payroll</h4>
        </div>
        <div class="card-body bg-white">
            <!-- Filter Section -->
            <div class="row g-3 align-items-end mb-4 mt-3">
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

            <!-- Divider -->
            <hr class="my-4">

            <!-- Leave Credit Earned Logs -->
            <div class="d-flex justify-content-between">
                <h5 class="fw-bold text-dark mb-3">📅 Generated Payroll List</h5>
                <div>
                    <button class="btn btn-success" id="btn_add">CREATE PAYROLL</button>
                </div>
            </div>


            <div class="table-responsive position-relative" style="overflow: visible;">
                <table class="table table-bordered align-middle text-center" style="background-color: #fff;">
                    <thead class="table-light">
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
    </div>
    @include('modals.payroll')
@stop
@section('js')
    <script>
        let selectedEmployees = [];
        let payroll_id = 0;
        $(document).ready(function () {
            getPayroll();
            loadDivision();

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

                console.log({
                    "payroll_id": payroll_id,
                    "employees": checked
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
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });

            });


            $("#btn_save_payroll").click(function () {
                const title = $("#title").val().trim();
                const start = $("#start_date").val();
                const end = $("#end_date").val();
                const remarks = $("#remarks").val().trim();

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
                    remarks: remarks
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
                        $("#create_payroll_modal").modal("hide");
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });
        });

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
                    <tr>
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

        function viewPayroll(id) {
            $('#preview_payroll_modal').modal('show');

            fetch(`{{ route('getPayrollEmployee') }}?payroll_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    const $tbody = $('#payroll_employee_list');
                    $tbody.empty(); // Clear existing rows

                    if (data.length === 0) {
                        $tbody.append(`
                    <tr>
                        <td colspan="55" class="text-center text-muted">No record found</td>
                    </tr>
                `);
                        return;
                    }

                    data.forEach((item, index) => {
                        const fullName = [item.fname, item.mname, item.lname, item.suffix].filter(Boolean).join(' ');

                        const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${fullName}</td>
                            <td>${item.division}</td>
                            <td>${item.section}</td>
                            <td>${formatCurrency(item.basic_salary)}</td>
                            <td>${formatCurrency(item.salary_adjustment)}</td>
                            <td>${formatCurrency("0.00")}</td>
                            <td>${formatCurrency(item.pera)}</td>
                            <td>${formatCurrency(item.rata)}</td>
                            <td>${formatCurrency(item.subsistence)}</td>
                            <td>${formatCurrency(item.laundry)}</td>
                            <td>${formatCurrency(item.hazard)}</td>
                            <td>${formatCurrency(item.longevity)}</td>
                            <td>${formatCurrency("0.00")}</td>
                            <td>${formatCurrency(item.cellphone_allowance)}</td>
                            <td class="text-danger">${formatCurrency(item.gross_pay)}</td>
                            <td>${formatCurrency("0.00")}</td>
                            <td>${formatCurrency("0.00")}</td>
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
                            <td>${formatCurrency("0.00")}</td>
                            <td>${formatCurrency("0.00")}</td>
                            <td>${formatCurrency(item.sss_deduction)}</td>
                            <td>${formatCurrency(item.deductions)}</td>
                            <td><strong>${formatCurrency(item.net_pay)}</strong></td>
                            <td>${formatCurrency(item.hazard_adjustment)}</td>
                            <td>${formatCurrency(item.pera_adjustment)}</td>
                            <td>${formatCurrency(item.subsistence_adjustment)}</td>
                            <td>${formatCurrency(item.laundry_adjustment)}</td>
                            <td>${formatCurrency(item.net_pay)}</td>
                        </tr>`;
                        $tbody.append(row);
                    });
                })
                .catch(error => {
                    console.error("Error fetching payroll:", error);
                    $('#payroll_employee_list').html(`<tr><td colspan="55" class="text-center text-danger">An error occurred while loading data.</td></tr>`);
                });
        }

        function managePayroll(id) {
            payroll_id = id;
            $("#append_employee_modal").modal("show");
        }

        function getPayroll() {
            fetch('{{ route('getPayroll') }}')
                .then(response => response.json())
                .then(data => {
                    const tbody = $("#payroll_list");
                    tbody.empty(); // Clear old records

                    if (data.length === 0) {
                        tbody.append("<tr><td colspan='9' class='text-center'>No payroll records found.</td></tr>");
                        return;
                    }

                    data.forEach(item => {
                        const row = `
                        <tr>
                            <td>${item.title}</td>
                            <td>${moment(item.start_date).format('MM/DD/YYYY')}</td>
                            <td>${moment(item.end_date).format('MM/DD/YYYY')}</td>
                            <td></td>
                            <td>${item.remarks || ''}</td>
                            <td></td>
                            <td>${moment(item.created_at).format('MM/DD/YYYY hh:mm:ss A')}</td>
                            <td class="text-center position-relative">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu position-absolute" style="z-index: 9999; right: 0; top: 100%;">
                                        <li><a class="dropdown-item" href="#" onclick="editPayroll(${item.id})">Edit</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="managePayroll(${item.id})">Assign Employee</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="viewPayroll(${item.id})">View</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="downloadPayroll(${item.id})">Download</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deletePayroll(${item.id})">Delete</a></li>
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

    </script>
@stop
