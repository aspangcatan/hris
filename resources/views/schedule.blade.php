@extends('master')

@section('content')
    <style>
        .schedule-page {
            --surface: #ffffff;
            --surface-soft: #f7fafc;
            --line: #e5eaf1;
            --text-strong: #12263f;
            --text-muted: #526581;
            --brand-1: #0f766e;
            --brand-2: #0ea5a4;
            --accent: #0f766e;
            --font-ui: "Manrope", "Avenir Next", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        }

        .schedule-page .premium-shell {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 16px 35px rgba(16, 24, 40, 0.08);
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        }

        .schedule-page .page-head {
            border-radius: 14px;
            padding: 1rem 1.1rem;
            background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
            color: #fff;
        }

        .schedule-page .page-head h2 {
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.2px;
        }

        .schedule-page .legend-box {
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            border-radius: 12px;
            padding: 0.75rem 0.85rem;
        }

        .schedule-page .filter-panel {
            margin-top: 1rem;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            padding: 1rem;
        }

        .schedule-page .filter-panel .form-control,
        .schedule-page .filter-panel .form-select {
            border-radius: 10px;
            border-color: #d7dfea;
        }

        .schedule-page .filter-panel .form-control:focus,
        .schedule-page .filter-panel .form-select:focus {
            border-color: #0ea5a4;
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 164, 0.14);
        }

        .schedule-page .btn-advance {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%);
            box-shadow: 0 10px 18px rgba(15, 118, 110, 0.24);
            font-weight: 600;
        }

        .schedule-page .table-wrap {
            margin-top: 1rem;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: var(--surface);
        }

        .schedule-page .schedule-table {
            margin-bottom: 0;
            font-family: var(--font-ui);
        }

        .schedule-page .schedule-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--surface-soft);
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            line-height: 1.1;
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
        }

        .schedule-page .schedule-table tbody tr:hover {
            background: #f8fbff;
        }

        .schedule-page .schedule-table tbody td {
            font-size: 0.94rem;
            color: #27364b;
            letter-spacing: 0.1px;
            line-height: 1.35;
            vertical-align: top;
        }

        .schedule-page .schedule-table tbody td:nth-child(2) {
            font-weight: 700;
            color: #11243c;
            font-size: 0.96rem;
            letter-spacing: 0.15px;
        }

        .schedule-page .schedule-table tbody td:nth-child(3) {
            font-weight: 600;
            color: #4d5f78;
            font-size: 0.89rem;
            line-height: 1.4;
        }

        .schedule-page .schedule-table .schedule-cell {
            font-family: var(--font-ui);
            font-size: 0.84rem;
            font-weight: 700 !important;
            color: #0f766e !important;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }
    </style>

    <div class="schedule-page">
    <div class="card">
        <div class="card-body p-4 premium-shell">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 page-head">
                <h2>Schedule Management</h2>
                <div class="legend-box">
                    <strong>Legend:</strong>
                    <div class="row" id="legend_list"></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row g-3 align-items-end filter-panel">
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="filter_month">
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

                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="filter_year">
                            <option value="" selected disabled>Select year</option>
                            @for($year = 2023; $year <= \Carbon\Carbon::now()->format('Y'); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                        <label for="year">Year</label>
                    </div>
                </div>
                @if(in_array(session('access_rights'), ['admin', 'schedule']))
                    <div class="col-md-2">
                        <div class="form-floating">
                            <select class="form-select form-control-sm" id="division">

                            </select>
                            <label for="month">Division</label>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-floating">
                            <select class="form-select form-control-sm" id="section">

                            </select>
                            <label for="year">Section</label>
                        </div>
                    </div>
                @endif
                <div class="col-md-2">
                    <div class="form-floating">
                        <input type="text" class="form-control form-control-sm" id="employee_search_filter" placeholder="Search employee">
                        <label for="employee_search_filter">Search Employee</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button id="btn_advance_schedule" class="btn btn-success btn-lg w-100 btn-advance">Advance</button>
                </div>


            </div>

            <!-- Schedule Table -->
            <div class="table-responsive mt-4 table-wrap">
                <table class="table table-bordered table-hover schedule-table">
                    <thead>
                    <tr id="schedule_header">
                        <th>Name</th>
                        <th>Designation</th>
                    </tr>
                    </thead>
                    <tbody id="employee_list"></tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

    <!-- Modals -->
    @include('modals.schedule')
@stop

@section('js')
    <script>
        let currentEmployees = [];

        $(document).ready(function () {
            // Initial Load
            loadScheduleMasterList();

            $("#division").change(function () {
                const division = $("#division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        $("#section").empty();
                        if (division == 0) {
                            $("#section").addClass("d-none");
                        } else {
                            $("#section").removeClass("d-none");
                            $("#section").append(`<option value="0">-- Select Section --</option>`);

                            data.forEach(it => {
                                const option = `<option value="${it.id}">${it.description}</option>`;
                                $("#section").append(option);
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: "error",
                            title: "Oops...",
                            text: "Something went wrong while loading sections. Please reload the page."
                        });
                    });
            });

            // Re-fetch on filter change
            $('#filter_month, #filter_year,#division,#section').on('change', loadEmployeeSchedule);
            $('#employee_search_filter').on('input', function () {
                filterScheduleRows($(this).val());
            });

            // Advance Set Schedule Button
            $('#btn_advance_schedule').on('click', function () {
                const options = $("#scheduleSelect").html();
                $(".advance-schedule-select").each(function () {
                    if ($(this).children().length <= 1) {
                        $(this).append(options);
                    }
                });

                renderAdvanceEmployeeTable();
                $("#advance_employee_search").val("");
                filterAdvanceEmployees("");
                $("#advance_select_all_employees").prop("checked", true);

                $('#advanceScheduleModal').modal('show');
            });

            // Save Advance Schedule
            $('#save_advance_schedule').on('click', function () {
                const year = $('#filter_year').val();
                const month = $('#filter_month').val();

                if (!year || !month) {
                    toastr.error("Please select both year and month", "Error");
                    return;
                }

                const employeeIds = $('.advance-employee-check:checked').map(function () {
                    return $(this).val();
                }).get();

                if (employeeIds.length === 0) {
                    toastr.warning("Please select at least one employee", "Warning");
                    return;
                }

                let scheduleData = [];

                $('.advance-schedule-select').each(function () {
                    const dayName = $(this).data('day');
                    const schedule_id = $(this).val();
                    if (schedule_id) {
                        const dates = getDatesForDayOfWeek(dayName, year, month);
                        dates.forEach(date => {
                            employeeIds.forEach(id => {
                                scheduleData.push({employee_id: id, schedule_id, schedule_date: date});
                            });
                        });
                    }
                });

                if (scheduleData.length === 0) {
                    toastr.warning("Please select at least one schedule", "Warning");
                    return;
                }

                fetch("{{ route('saveAdvanceSchedule') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                    },
                    body: JSON.stringify({schedules: scheduleData})
                })
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, "Success");
                        $('#advanceScheduleModal').modal('hide');
                        loadEmployeeSchedule();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Failed to save schedule", "Error");
                    });
            });

            $('#advance_employee_search').on('input', function () {
                filterAdvanceEmployees($(this).val());
            });

            $('#advance_select_all_employees').on('change', function () {
                $('.advance-employee-check').prop('checked', $(this).is(':checked'));
            });

            $("body").on("change", ".advance-employee-check", function () {
                const total = $(".advance-employee-check").length;
                const checked = $(".advance-employee-check:checked").length;
                $("#advance_select_all_employees").prop("checked", total > 0 && total === checked);
            });

            // Open Schedule Modal
            $("body").on("click", ".schedule-cell", function () {
                const employeeId = $(this).data('employee-id');
                const day = $(this).data('day');

                $('#selectedEmployeeId').val(employeeId);
                $('#selectedDay').val(day);
                $('#scheduleModal').modal('show');
            });

            // Save Individual Schedule
            $('#btn_save_schedule').on('click', function () {
                const $btn = $(this);
                const employeeId = $('#selectedEmployeeId').val();
                const day = $('#selectedDay').val();
                const schedule_id = $('#scheduleSelect').val();
                const schedule_code = $('#scheduleSelect option:selected').data("code");
                const schedule_date = `${$('#filter_year').val()}-${$('#filter_month').val()}-${String(day).padStart(2, '0')}`;
                $btn.prop('disabled', true);

                fetch('{{ route('setEmployeeSchedule') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        schedule_date: schedule_date,
                        schedule_id: schedule_id
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, "Updated");
                        $(`.schedule-cell[data-employee-id="${employeeId}"][data-day="${day}"]`).text(schedule_code);
                        $('#scheduleModal').modal('hide');
                    })
                    .then(() => {
                        loadEmployeeSchedule();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Failed to update schedule.", "Error");
                    })
                    .finally(() => {
                        $btn.prop('disabled', false);
                    });
            });

            @if(in_array(session('access_rights'), ['admin']))
            loadDivision();
            @endif
        });

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    const userDivisionId = "{{ Auth::user()->division }}";
                    const isAdmin = {{ session('access_rights') == 'admin' ? 'true' : 'false' }};
                    $("#division").empty(); 

                    if (isAdmin) {
                        $("#division").append(`<option value="0">-- Select Division --</option>`);
                        data.forEach(it => {
                            const option = `<option value="${it.id}">${it.description}</option>`;
                            $("#division").append(option);
                        });
                    } else {
                        data.forEach(it => {
                            if (it.id == userDivisionId) {
                                const option = `<option value="${it.id}" selected>${it.description}</option>`;
                                $("#division").append(option);
                            }
                        });
                        // Trigger change to automatically load sections for the user's division
                        $("#division").trigger('change');
                    }
                })
                .catch(err => {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Something went wrong while loading divisions. Please reload the page."
                    });
                });
        }

        function renderAdvanceEmployeeTable() {
            const tbody = $("#advance_employee_table_body");
            tbody.empty();

            if (!currentEmployees.length) {
                tbody.append(`<tr><td colspan="3" class="text-center text-muted">No employees found for selected filters.</td></tr>`);
                return;
            }

            currentEmployees.forEach(emp => {
                tbody.append(`
                    <tr class="advance-employee-row" data-search="${(emp.name + ' ' + emp.designation).toLowerCase()}">
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input advance-employee-check" value="${emp.id}" checked>
                        </td>
                        <td>${emp.name}</td>
                        <td>${emp.designation}</td>
                    </tr>
                `);
            });
        }

        function filterAdvanceEmployees(keyword) {
            const q = (keyword || "").toLowerCase().trim();
            $(".advance-employee-row").each(function () {
                const text = String($(this).data("search") || "");
                $(this).toggle(q === "" || text.includes(q));
            });
        }


        // Load Schedule Master List (Legend & Options)
        function loadScheduleMasterList() {
            fetch('{{ route('getScheduleMasterList') }}')
                .then(res => res.json())
                .then(data => {
                        $('#scheduleSelect').append(`<option value="""></option>`);
                    data.forEach(it => {
                        $('#legend_list').append(`<div class="col-md-6"><div>${it.code} - ${it.label}</div></div>`);
                        $('#scheduleSelect').append(`<option value="${it.id}" data-code="${it.code}">${it.label}</option>`);
                    });
                })
                .catch(console.error);
        }

        // Load Employee Schedules
        function loadEmployeeSchedule() {
            const month = $('#filter_month').val();
            const year = $('#filter_year').val();
            const division = $('#division').val() ?? '';
            const section = $('#section').val() ?? '';

            if (!month || !year) {
                toastr.error("Please select both month and year.", "Missing Filter");
                return;
            }

            fetch(`{{ route('getSchedules') }}?month=${month}&year=${year}&division=${division}&section=${section}`)
                .then(res => res.json())
                .then(data => {
                    let headerHTML = `<th>Name</th><th>Designation</th>`;
                    for (let i = 1; i <= data.days; i++) {
                        headerHTML += `<th>${i}</th>`;
                    }
                    $('#schedule_header').html(headerHTML);

                    $('#employee_list').empty();
                    currentEmployees = data.schedules; // Store for modal
                    data.schedules.forEach(emp => {
                        let row = `<tr class="schedule-employee-row" data-search="${(emp.name + ' ' + emp.designation).toLowerCase()}"><td class="d-none">${emp.id}</td><td>${emp.name}</td><td>${emp.designation}</td>`;
                        emp.schedule.forEach((sched, i) => {
                            row += `<td class="fw-bold text-primary schedule-cell" style="cursor:pointer"
                                 data-employee-id="${emp.id}" data-day="${i + 1}">${sched}</td>`;
                        });
                        row += `</tr>`;
                        $('#employee_list').append(row);
                    });
                    filterScheduleRows($('#employee_search_filter').val());
                })
                .catch(console.error);
        }

        function filterScheduleRows(keyword) {
            const q = (keyword || "").toLowerCase().trim();
            $(".schedule-employee-row").each(function () {
                const text = String($(this).data("search") || "");
                $(this).toggle(q === "" || text.includes(q));
            });
        }

        // Get Dates for Day of Week
        function getDatesForDayOfWeek(dayName, year, month) {
            const dayMap = {Sunday: 0, Monday: 1, Tuesday: 2, Wednesday: 3, Thursday: 4, Friday: 5, Saturday: 6};
            const dates = [];
            const date = new Date(`${year}-${month}-01`);
            const targetDay = dayMap[dayName];

            while (date.getMonth() === parseInt(month, 10) - 1) {
                if (date.getDay() === targetDay) {
                    const yyyy = date.getFullYear();
                    const mm = String(date.getMonth() + 1).padStart(2, '0');
                    const dd = String(date.getDate()).padStart(2, '0');
                    dates.push(`${yyyy}-${mm}-${dd}`);
                }
                date.setDate(date.getDate() + 1);
            }

            return dates;
        }
    </script>
@stop
