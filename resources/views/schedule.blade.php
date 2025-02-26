@extends('master')

@section('content')
    <div class="card">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between">
                <h2>Schedule Management</h2>
                <div style="border: 1px solid rgb(193,193,193)" class="p-3">
                    <strong>Legend:</strong>
                    <div class="row" id="legend_list"></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mt-3">
                <div class="col-md-3">
                    <label for="filter_month">Month:</label>
                    <select id="filter_month" class="form-control">
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter_year">Year:</label>
                    <select id="filter_year" class="form-control">
                        <option value="">Select year</option>
                        @for($year=2024; $year<= \Carbon\Carbon::now()->format('Y'); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button id="applyFilter" class="btn btn-primary">Apply Filter</button>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="table-responsive mt-3">
                <table class="table table-bordered">
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
    <!-- Schedule Modal -->
    @include('modals.schedule')
@stop

@section('js')
    <!-- Bootstrap & jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            loadScheduleMasterList();

            $("#filter_month,#filter_year").change(function () {
                loadEmployeeSchedule();
            });

            $("body").on("click", ".schedule-cell", function () {
                let employeeId = $(this).data('employee-id');
                let day = $(this).data('day');

                $('#selectedEmployeeId').val(employeeId);
                $('#selectedDay').val(day);
                $('#scheduleModal').modal('show');
            });


            $('#btn_save_schedule').click(function () {
                const employeeId = $('#selectedEmployeeId').val();
                const day = $('#selectedDay').val();
                const schedule_id = $('#scheduleSelect').val();
                const schedule_code = $("#scheduleSelect option:selected").data("code");
                const schedule_date = $("#filter_year").val() + "-" + $("#filter_month").val() + "-" + String(day).padStart(2, '0');

                const params = {
                    employee_id: employeeId,
                    schedule_date: schedule_date,
                    schedule_id: schedule_id,
                    _token: "{{ csrf_token() }}"
                };

                fetch('{{ route('setEmployeeSchedule') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                })
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, "Information");
                        let cell = $('.schedule-cell[data-employee-id="' + employeeId + '"][data-day="' + day + '"]');
                        cell.text(schedule_code);
                        $('#scheduleModal').modal('hide');
                    }).catch(err => {
                    console.log(err);
                })
            });
        });

        function loadEmployeeSchedule() {
            const month = $("#filter_month").val();
            const year = $("#filter_year").val();

            if (month == "" || year == "") {
                toastr.error("Please fill in filter data first", "Ooops");
                return;
            }

            fetch('{{ route('getSchedules') }}?month=' + month +
                "&year=" + year, {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    let header = "";
                    header += `<th>Name</th>`;
                    header += `<th>Designation</th>`;
                    for (let i = 1; i <= data.days; i++) {
                        header += `<th>` + i + `</th>`;
                    }

                    $("#schedule_header").html(header);
                    $("#employee_list").empty();

                    data.schedules.forEach(it => {
                        let tr = `<tr>
                            <td>` + it.name + `</td>
                            <td>` + it.designation + `</td>`;

                        for (let j = 0; j < it.schedule.length; j++) {
                            tr += `<td
                                      style="cursor: pointer"
                                    class="fw-bold text-primary schedule-cell"
                                    data-employee-id="${it.id}"
                                    data-day="${j + 1}">` + it.schedule[j] + `</td>`;
                        }
                        tr += `</tr>`;
                        $("#employee_list").append(tr);
                    });
                }).catch(err => {
                console.log(err);
            })
        }

        function loadScheduleMasterList() {
            fetch('{{ route('getScheduleMasterList') }}', {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    data.forEach(it => {
                        const tr =
                            `<div class='col-md-6'>
                                    <div>` + it.code + ` - ` + it.label + `</div>
                                 </div>`;

                        const option = `<option value="` + it.id + `" data-code="` + it.code + `">` + it.label + `</option>`;

                        $("#legend_list").append(tr);
                        $("#scheduleSelect").append(option);
                    });
                }).catch(err => {
                console.log(err);
            })
        }
    </script>
@stop
