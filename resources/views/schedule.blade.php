@extends('master')

@php
    $canManageSchedule = permission_check('schedule.manage');
@endphp

@section('content')
    <style>
        .schedule-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .schedule-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .schedule-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .schedule-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .schedule-legend {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.75rem 0.9rem;
            min-width: 220px;
        }

        .schedule-legend .row {
            font-size: 0.85rem;
            color: #475569;
        }

        .schedule-filter {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .schedule-filter .form-control,
        .schedule-filter .form-select {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .schedule-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .schedule-table-shell table {
            margin-bottom: 0;
        }

        .schedule-table-shell table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .schedule-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: top;
        }

        .schedule-table-shell .schedule-cell {
            font-size: 0.84rem;
            font-weight: 700 !important;
            color: #0f766e !important;
            letter-spacing: 0.45px;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }
    </style>

    <div class="schedule-board">
        <div class="schedule-head">
            <div class="schedule-head-title">
                @if($canManageSchedule)
                    <h4>Schedule Management</h4>
                    <span>Build and manage monthly schedules by division and section.</span>
                @else
                    <h4>My Schedule</h4>
                    <span>View your schedule for the month.</span>
                @endif
            </div>
            <div class="schedule-legend">
                <div class="fw-bold mb-1">Legend</div>
                <div class="row" id="legend_list"></div>
            </div>
        </div>

        <div class="schedule-filter">
            <div class="row g-3 align-items-end">
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
                @if(in_array(session('access_rights'), ['admin']))
                    <div class="col-md-2">
                        <div class="form-floating">
                            <select class="form-select form-control-sm" id="division">

                            </select>
                            <label for="month">Division</label>
                        </div>
                    </div>
                @endif

                @if($canManageSchedule)
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
                @if($canManageSchedule)
                    <div class="col-md-2 d-grid gap-2">
                        <button id="btn_advance_schedule" class="btn btn-action btn-edit w-100">Advance</button>
                        <button id="btn_print_schedule" class="btn btn-action btn-view w-100">Print Preview</button>
                    </div>
                @endif
            </div>
        </div>

        <div class="table-responsive schedule-table-shell">
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

    <!-- Modals -->
    @include('modals.schedule')
@stop

@section('js')
    <script>
        let currentEmployees = [];
        let managedSectionsByDivision = {};
        const accessRights = @json(strtolower((string) session('access_rights')));
        const canManageSchedule = @json($canManageSchedule);

        $(document).ready(function () {
            loadScheduleMasterList();

            $("#division").on('change', function () {
                if (accessRights === 'admin') {
                    loadSectionsByDivision($(this).val());
                }
            });

            $('#filter_month, #filter_year, #division, #section').on('change', function () {
                loadEmployeeSchedule(true);
            });
            $('#employee_search_filter').on('input', function () {
                filterScheduleRows($(this).val());
            });

            $('#btn_advance_schedule').on('click', function () {
                if (isLockedMonthSelection()) {
                    toastr.warning("Schedules from last month and older can no longer be edited.", "Locked");
                    return;
                }

                const scheduleOptions = $('#scheduleSelect option').map(function () {
                    const value = $(this).val();
                    if (!value) {
                        return null;
                    }
                    const label = $(this).text();
                    return `<option value="${value}">${label}</option>`;
                }).get().join('');

                $(".advance-schedule-select, #advance_quick_schedule").each(function () {
                    $(this).html(`<option value="">No schedule (clear)</option>${scheduleOptions}`);
                    $(this).val('');
                });
                $("#advance_quick_schedule").val($("#advance_quick_schedule option:eq(1)").val() || '');

                renderAdvanceEmployeeTable();
                $("#advance_employee_search").val("");
                filterAdvanceEmployees("");
                $("#advance_select_all_employees").prop("checked", true);

                syncAdvanceRangeBounds();
                setAdvanceRange('month');
                $("#advance_month_label").text($('#filter_month option:selected').text() + ' ' + $('#filter_year').val());
                refreshAdvanceDays();
                $('#advanceScheduleModal').modal('show');
            });

            $('#btn_print_schedule').on('click', function () {
                const year = $('#filter_year').val();
                const month = $('#filter_month').val();
                const division = $('#division').val() ?? '';
                const section = $('#section').val() ?? '';

                if (!year || !month) {
                    toastr.error("Please select both month and year.", "Missing Filter");
                    return;
                }

                const url = `{{ route('printSchedule') }}?month=${month}&year=${year}&division=${division}&section=${section}`;
                window.open(url, '_blank');
            });

            $('#save_advance_schedule').on('click', function () {
                const $btn = $(this);
                const year = $('#filter_year').val();
                const month = $('#filter_month').val();
                const rangeStart = $('#advance_start_date').val();
                const rangeEnd = $('#advance_end_date').val();

                if (!updateAdvanceRangeState(true)) {
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
                let clearDays = [];

                $('.advance-schedule-select').each(function () {
                    const dayName = $(this).data('day');
                    const schedule_id = $(this).val();
                    if (schedule_id) {
                        const dates = getDatesForDayOfWeek(dayName, year, month, rangeStart, rangeEnd);
                        dates.forEach(date => {
                            employeeIds.forEach(id => {
                                scheduleData.push({employee_id: id, schedule_id, schedule_date: date});
                            });
                        });
                    } else {
                        clearDays.push(dayName);
                    }
                });

                if (scheduleData.length === 0 && clearDays.length === 0) {
                    toastr.warning("Please select at least one schedule or leave a day blank to clear.", "Warning");
                    return;
                }

                const originalText = $btn.html();
                $btn.prop('disabled', true);
                $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...');

                fetch("{{ route('saveAdvanceSchedule') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                    },
                    body: JSON.stringify({
                        schedules: scheduleData,
                        clear_days: clearDays,
                        range_start: rangeStart,
                        range_end: rangeEnd,
                        employee_ids: employeeIds,
                        year: year,
                        month: month
                    })
                })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to save schedule");
                        }
                        return data;
                    })
                    .then(data => {
                        toastr.success(data.message, "Success");
                        $('#advanceScheduleModal').modal('hide');
                        loadEmployeeSchedule();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error(err.message || "Failed to save schedule", "Error");
                    })
                    .finally(() => {
                        $btn.prop('disabled', false);
                        $btn.html(originalText);
                    });
            });

            $('#advance_employee_search').on('input', function () {
                filterAdvanceEmployees($(this).val());
            });

            $('#advance_select_all_employees').on('change', function () {
                $('.advance-employee-check').prop('checked', $(this).is(':checked'));
                updateAdvanceSummary();
            });

            $("body").on("change", ".advance-employee-check", function () {
                const total = $(".advance-employee-check").length;
                const checked = $(".advance-employee-check:checked").length;
                $("#advance_select_all_employees").prop("checked", total > 0 && total === checked);
                updateAdvanceSummary();
            });

            // Clicking anywhere on an employee row toggles it
            $("body").on("click", ".advance-employee-row", function (e) {
                if ($(e.target).is('input')) return;
                $(this).find('.advance-employee-check').prop('checked', (i, v) => !v).trigger('change');
            });

            // Quick date ranges
            $('#advanceScheduleModal').on('click', '[data-asb-range]', function () {
                setAdvanceRange($(this).data('asb-range'));
            });

            // Quick fill of the weekly pattern
            $('#advanceScheduleModal').on('click', '[data-asb-fill]', function () {
                const days = {
                    weekdays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    weekend: ['Saturday', 'Sunday'],
                    all: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
                }[$(this).data('asb-fill')];
                const value = $('#advance_quick_schedule').val();
                $('.advance-schedule-select').each(function () {
                    if (days.includes($(this).data('day'))) $(this).val(value);
                });
                refreshAdvanceDays();
            });

            $('#advanceScheduleModal').on('change', '.advance-schedule-select', refreshAdvanceDays);

            $("body").on("click", ".schedule-cell", function () {
                if (!canManageSchedule) {
                    return;
                }

                if (isLockedMonthSelection()) {
                    toastr.warning("Schedules from last month and older can no longer be edited.", "Locked");
                    return;
                }

                const employeeId = $(this).data('employee-id');
                const day = $(this).data('day');

                $('#selectedEmployeeId').val(employeeId);
                $('#selectedDay').val(day);
                $('#scheduleModal').modal('show');
            });

            $('#advance_start_date, #advance_end_date').on('change input', function () {
                updateAdvanceRangeState();
            });

            $('#filter_month, #filter_year').on('change', function () {
                syncAdvanceRangeBounds();
                updateAdvanceRangeState();
            });

            $('#btn_save_schedule').on('click', function () {
                const $btn = $(this);
                const employeeId = $('#selectedEmployeeId').val();
                const day = $('#selectedDay').val();
                const schedule_id = $('#scheduleSelect').val();
                const schedule_code = $('#scheduleSelect option:selected').data("code");
                const schedule_date = `${$('#filter_year').val()}-${$('#filter_month').val()}-${String(day).padStart(2, '0')}`;
                const originalText = $btn.text();
                $btn.prop('disabled', true);
                $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...');

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
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to update schedule.");
                        }
                        return data;
                    })
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
                        toastr.error(err.message || "Failed to update schedule.", "Error");
                    })
                    .finally(() => {
                        $btn.prop('disabled', false);
                        $btn.text(originalText);
                    });
            });

            if (accessRights === 'admin') {
                loadDivision();
            } else if (canManageSchedule) {
                loadManagedSections();
            }
        });

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    $("#division").empty();
                    $("#division").append(`<option value="0">-- Select Division --</option>`);
                    data.forEach(it => {
                        $("#division").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                    $("#section").empty().append(`<option value="0">-- Select Section --</option>`);
                })
                .catch(err => {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Something went wrong while loading divisions. Please reload the page."
                    });
                });
        }

        function loadSectionsByDivision(division) {
            const selectedDivision = division || '0';
            if (selectedDivision === '0') {
                $("#section").empty();
                $("#section").append(`<option value="0">-- Select Section --</option>`);
                return;
            }

            fetch("{{ route('getSectionList') }}?division=" + selectedDivision, {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    $("#section").empty();
                    $("#section").append(`<option value="0">-- Select Section --</option>`);
                    data.forEach(it => {
                        $("#section").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                })
                .catch(() => {
                    Swal.fire({
                        icon: "error",
                        title: "Oops...",
                        text: "Something went wrong while loading sections. Please reload the page."
                    });
                });
        }

        function loadManagedSections() {
            fetch("{{ route('getManagedSections') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(payload => {
                    const sections = payload.sections || [];
                    managedSectionsByDivision = {};

                    $("#section").empty();
                    $("#section").append(`<option value="0">All Allowed Sections</option>`);

                    sections.forEach(section => {
                        const divisionLabel = section.division_description ? `${section.division_description} - ` : '';
                        $("#section").append(`<option value="${section.id}">${divisionLabel}${section.description}</option>`);
                    });
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to load assigned sections.", "Error");
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
                        <td>
                            <input type="checkbox" class="form-check-input advance-employee-check" value="${emp.id}" checked aria-label="Select ${emp.name}">
                        </td>
                        <td>
                            <div class="asb-emp-name">${emp.name}</div>
                            <div class="asb-emp-role">${emp.designation || ''}</div>
                        </td>
                    </tr>
                `);
            });
            updateAdvanceSummary();
        }

        function formatYmd(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        // Fill the From/To dates with a preset inside the month being viewed
        function setAdvanceRange(preset) {
            const year = Number($('#filter_year').val());
            const month = Number($('#filter_month').val());
            if (!year || !month) return;

            const first = new Date(year, month - 1, 1);
            const last = getLastDayOfMonth(year, month - 1);
            let start = first, end = last;

            if (preset === 'today') {
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (today > last) {
                    toastr.info("Today is after this month.", "Date range");
                    return;
                }
                start = today < first ? first : today;
            } else if (preset === 'first') {
                end = new Date(year, month - 1, 15);
            } else if (preset === 'second') {
                start = new Date(year, month - 1, 16);
            }

            $('#advance_start_date').val(formatYmd(start));
            $('#advance_end_date').val(formatYmd(end));
            updateAdvanceRangeState();
        }

        // Mark days left on "No schedule (clear)" and refresh the footer summary
        function refreshAdvanceDays() {
            $('.advance-schedule-select').each(function () {
                $(this).closest('.asb-day').toggleClass('is-clear', !$(this).val());
            });
            updateAdvanceSummary();
        }

        function updateAdvanceSummary() {
            const total = $('.advance-employee-check').length;
            const selected = $('.advance-employee-check:checked').length;
            $('#advance_selected_count')
                .text(`${selected} of ${total} selected`)
                .toggleClass('is-none', selected === 0);

            const year = $('#filter_year').val();
            const month = $('#filter_month').val();
            const start = $('#advance_start_date').val();
            const end = $('#advance_end_date').val();
            const { valid } = getAdvanceRangeValidation();

            let setDates = 0, clearDates = 0;
            if (valid) {
                $('.advance-schedule-select').each(function () {
                    const n = getDatesForDayOfWeek($(this).data('day'), year, month, start, end).length;
                    if ($(this).val()) setDates += n; else clearDates += n;
                });
            }

            const range = valid
                ? `${moment(start).format('MMM D')} – ${moment(end).format('MMM D, YYYY')}`
                : 'no valid date range';
            $('#advance_summary').html(`
                <span><i class="bi bi-people me-1"></i><strong>${selected}</strong> employee${selected === 1 ? '' : 's'}</span>
                <span><i class="bi bi-calendar-range me-1"></i>${range}</span>
                <span><i class="bi bi-calendar-check me-1"></i><strong>${setDates}</strong> date${setDates === 1 ? '' : 's'} scheduled</span>
                <span class="asb-clear-count"><i class="bi bi-eraser me-1"></i><strong>${clearDates}</strong> cleared</span>
            `);
            $('#save_advance_schedule').prop('disabled', !valid || selected === 0);
        }

        function filterAdvanceEmployees(keyword) {
            const q = (keyword || "").toLowerCase().trim();
            $(".advance-employee-row").each(function () {
                const text = String($(this).data("search") || "");
                $(this).toggle(q === "" || text.includes(q));
            });
        }

        function updateAdvanceRangeState(showToast = false) {
            const { valid, message } = getAdvanceRangeValidation();
            $('#advance_range_error').text(valid ? '' : message);
            if (!valid && showToast && message) {
                toastr.warning(message, "Invalid Range");
            }
            updateAdvanceSummary(); // also enables/disables the Apply button

            return valid;
        }

        function getAdvanceRangeValidation() {
            const year = $('#filter_year').val();
            const month = $('#filter_month').val();
            const rangeStart = $('#advance_start_date').val();
            const rangeEnd = $('#advance_end_date').val();

            if (!year || !month) {
                return { valid: false, message: "Please select both month and year." };
            }
            if (!rangeStart || !rangeEnd) {
                return { valid: false, message: "Select both start and end dates." };
            }

            const startDate = parseYmdToLocalDate(rangeStart);
            const endDate = parseYmdToLocalDate(rangeEnd);
            if (startDate > endDate) {
                return { valid: false, message: "Start date must be on or before end date." };
            }
            if (!isSameMonthYear(startDate, year, month) || !isSameMonthYear(endDate, year, month)) {
                return { valid: false, message: "Date range must be within the selected month and year." };
            }

            return { valid: true, message: "" };
        }


        function loadScheduleMasterList() {
            fetch('{{ route('getScheduleMasterList') }}')
                .then(res => res.json())
                .then(data => {
                        $('#scheduleSelect').append(`<option value=""></option>`);
                    data.forEach(it => {
                        $('#legend_list').append(`<div class="col-md-6"><div><strong>${it.code}</strong> - ${it.label}</div></div>`);
                        $('#scheduleSelect').append(`<option value="${it.id}" data-code="${it.code}">${it.label}</option>`);
                    });
                })
                .catch(console.error);
        }

        function loadEmployeeSchedule(showValidation = true) {
            const month = $('#filter_month').val();
            const year = $('#filter_year').val();
            const division = $('#division').val() ?? '';
            const section = $('#section').val() ?? '';

            if (!month || !year) {
                if (showValidation) {
                    toastr.error("Please select both month and year.", "Missing Filter");
                }
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
                            if (canManageSchedule) {
                                row += `<td class="fw-bold text-primary schedule-cell" style="cursor:pointer"
                                     data-employee-id="${emp.id}" data-day="${i + 1}">${sched}</td>`;
                            } else {
                                row += `<td class="fw-bold text-primary">${sched}</td>`;
                            }
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

        function isLockedMonthSelection() {

            return false;

            const month = Number($('#filter_month').val());
            const year = Number($('#filter_year').val());

            if (!month || !year) {
                return false;
            }

            const selected = new Date(year, month - 1, 1);
            const currentMonthStart = new Date();
            currentMonthStart.setDate(1);
            currentMonthStart.setHours(0, 0, 0, 0);

            // Lock previous months (including last month).
            return selected < currentMonthStart;
        }

        // Get Dates for Day of Week
        function getDatesForDayOfWeek(dayName, year, month, rangeStart, rangeEnd) {
            const dayMap = {Sunday: 0, Monday: 1, Tuesday: 2, Wednesday: 3, Thursday: 4, Friday: 5, Saturday: 6};
            const dates = [];
            const date = new Date(`${year}-${month}-01`);
            const targetDay = dayMap[dayName];
            const startDate = rangeStart ? parseYmdToLocalDate(rangeStart) : new Date(Number(year), Number(month) - 1, 1);
            const endDate = rangeEnd ? parseYmdToLocalDate(rangeEnd) : getLastDayOfMonth(Number(year), Number(month) - 1);

            while (date.getMonth() === parseInt(month, 10) - 1) {
                if (date.getDay() === targetDay) {
                    const yyyy = date.getFullYear();
                    const mm = String(date.getMonth() + 1).padStart(2, '0');
                    const dd = String(date.getDate()).padStart(2, '0');
                    if (date >= startDate && date <= endDate) {
                        dates.push(`${yyyy}-${mm}-${dd}`);
                    }
                }
                date.setDate(date.getDate() + 1);
            }

            return dates;
        }

        function parseYmdToLocalDate(ymd) {
            const [y, m, d] = String(ymd).split('-').map(Number);
            return new Date(y, m - 1, d);
        }

        function getLastDayOfMonth(year, monthIndex) {
            return new Date(year, monthIndex + 1, 0);
        }

        function isSameMonthYear(date, year, month) {
            return date.getFullYear() === Number(year) && (date.getMonth() + 1) === Number(month);
        }

        function syncAdvanceRangeBounds() {
            const year = $('#filter_year').val();
            const month = $('#filter_month').val();
            if (!year || !month) {
                $('#advance_start_date').attr('min', '').attr('max', '');
                $('#advance_end_date').attr('min', '').attr('max', '');
                return;
            }

            const first = new Date(Number(year), Number(month) - 1, 1);
            const last = getLastDayOfMonth(Number(year), Number(month) - 1);
            // Local date, not toISOString(): in UTC+8 that shifts to the previous day
            const min = formatYmd(first);
            const max = formatYmd(last);

            $('#advance_start_date').attr('min', min).attr('max', max);
            $('#advance_end_date').attr('min', min).attr('max', max);
        }
    </script>
@stop
