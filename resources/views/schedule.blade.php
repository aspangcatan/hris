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
            <div class="row mt-4">
                <div class="col-md-3">
                    <label for="filter_month">Month:</label>
                    <select id="filter_month" class="form-control">
                        @foreach(['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'] as $num => $name)
                            <option value="{{ $num }}" {{ now()->format('m') == $num ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_year">Year:</label>
                    <select id="filter_year" class="form-control">
                        <option value="">Select year</option>
                        @for($year = 2024; $year <= \Carbon\Carbon::now()->year; $year++)
                            <option value="{{ $year }}" {{ now()->year == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button id="applyFilter" class="btn btn-primary w-100">Apply Filter</button>
                    <button id="btn_advance_schedule" class="btn btn-warning w-100">Advance Set Schedule</button>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initial Load
            loadScheduleMasterList();
            loadEmployeeSchedule();

            // Re-fetch on filter change
            $('#filter_month, #filter_year').on('change', loadEmployeeSchedule);

            // Advance Set Schedule Button
            $('#btn_advance_schedule').on('click', function () {
                const options = $("#scheduleSelect").html();
                $(".advance-schedule-select").each(function () {
                    if ($(this).children().length <= 1) {
                        $(this).append(options);
                    }
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

                let employeeIds = [];
                $('#employee_list tr').each(function () {
                    const id = $(this).find('td:first').text().trim();
                    if (id) employeeIds.push(id);
                });

                if (employeeIds.length === 0) {
                    toastr.warning("No employees to schedule", "Warning");
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
                                scheduleData.push({ employee_id: id, schedule_id, schedule_date: date });
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
                    body: JSON.stringify({ schedules: scheduleData })
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
        });

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

            if (!month || !year) {
                toastr.error("Please select both month and year.", "Missing Filter");
                return;
            }

            fetch(`{{ route('getSchedules') }}?month=${month}&year=${year}`)
                .then(res => res.json())
                .then(data => {
                    let headerHTML = `<th>Name</th><th>Designation</th>`;
                    for (let i = 1; i <= data.days; i++) {
                        headerHTML += `<th>${i}</th>`;
                    }
                    $('#schedule_header').html(headerHTML);

                    $('#employee_list').empty();
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
            const dayMap = { Sunday: 0, Monday: 1, Tuesday: 2, Wednesday: 3, Thursday: 4, Friday: 5, Saturday: 6 };
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
