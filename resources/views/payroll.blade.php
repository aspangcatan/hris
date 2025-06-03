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


            <div class="table-responsive mt-3">
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
                        <th colspan="2" style="width: 5%"></th>
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
        $(document).ready(function () {
            getPayroll();
            loadDivision();

            $("#filter_division").change(function () {
                const division = $("#filter_division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        $("#filter_section").empty();
                        if (division == 0) {
                            $("#filter_section").addClass("d-none");
                        } else {
                            $("#filter_section").removeClass("d-none");
                        }

                        data.forEach(it => {
                            const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                            $("#filter_section").append(option);
                        });
                    }).catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
            });

            $("#filter_section").change(function () {
                getEmployee($("#filter_division").val(),$(this).val());
            });

            $("#btn_add").click(function () {
                $("#create_payroll_modal").modal("show");
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

        function getEmployee(division, section) {
            fetch(`{{ route('getEmployeeByDivisionSection') }}?division=${division}&section=${section}`)
                .then(response => response.json())
                .then(data => {
                    const $tbody = $('#employee_list');
                    $tbody.empty(); // Clear current list

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
                        <td class="text-center">${emp.designation}</td>
                    </tr>
                `;

                        $tbody.append(row);
                    });
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
                    data.forEach(it => {
                        const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                        $("#filter_division").append(option);
                    });
                }).catch(err => {
                alert("Ooops, something went wrong. Please reload");
            });
        }

        function editPayroll(id) {
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
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editPayroll(${item.id})">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="managePayroll(${item.id})">
                                <i class="bi bi-gear"></i>
                            </button>
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
