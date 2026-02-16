@extends('master')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <h2 class="mb-0">Schedule Management</h2>
                <div style="border: 1px solid #c1c1c1;" class="p-3 rounded bg-light">
                    <strong>Legend:</strong>
                    <div class="row" id="legend_list"></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row g-3 align-items-end mb-4 mt-3">
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
                    <div class="col-md-4">
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
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button id="btn_advance_schedule" class="btn btn-success btn-lg w-100">Advance Settings</button>
                </div>


            </div>

            <!-- Schedule Table -->
            <div class="table-responsive mt-4">
                <table class="table table-bordered table-hover">
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

    <!-- Modals -->
    @include('modals.schedule')
@stop

@section('js')
    <script>
        let currentEmployees = [];
        let advanceEmployeeSelect;

        $(document).ready(function () {
            // Initial Selectize
            advanceEmployeeSelect = $('#advance_employee_select').selectize({
                plugins: ['remove_button'],
                valueField: 'value',
                labelField: 'text',
                searchField: 'text'
            })[0].selectize;

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

            // Advance Set Schedule Button
            $('#btn_advance_schedule').on('click', function () {
                const options = $("#scheduleSelect").html();
                $(".advance-schedule-select").each(function () {
                    if ($(this).children().length <= 1) {
                        $(this).append(options);
                    }
                });

                // Populate employee selectize
                advanceEmployeeSelect.clearOptions();
                currentEmployees.forEach(emp => {
                    advanceEmployeeSelect.addOption({value: emp.id, text: emp.name});
                    advanceEmployeeSelect.addItem(emp.id); // Default to all selected, user can remove
                });

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

                const employeeIds = advanceEmployeeSelect.getValue();

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
                const employeeId = $('#selectedEmployeeId').val();
                const day = $('#selectedDay').val();
                const schedule_id = $('#scheduleSelect').val();
                const schedule_code = $('#scheduleSelect option:selected').data("code");
                const schedule_date = `${$('#filter_year').val()}-${$('#filter_month').val()}-${String(day).padStart(2, '0')}`;

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
                    .catch(console.error);
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


        // Load Schedule Master List (Legend & Options)
        function loadScheduleMasterList() {
            fetch('{{ route('getScheduleMasterList') }}')
                .then(res => res.json())
                .then(data => {
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
                        let row = `<tr><td class="d-none">${emp.id}</td><td>${emp.name}</td><td>${emp.designation}</td>`;
                        emp.schedule.forEach((sched, i) => {
                            row += `<td class="fw-bold text-primary schedule-cell" style="cursor:pointer"
                                 data-employee-id="${emp.id}" data-day="${i + 1}">${sched}</td>`;
                        });
                        row += `</tr>`;
                        $('#employee_list').append(row);
                    });
                })
                .catch(console.error);
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
