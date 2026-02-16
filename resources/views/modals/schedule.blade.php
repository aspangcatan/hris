<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">Update Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="selectedEmployeeId">
                <input type="hidden" id="selectedDay">
                <input type="hidden" id="selectedCode">

                <label for="scheduleSelect">Select Schedule:</label>
                <select class="form-select" id="scheduleSelect"></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_save_schedule">Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="advanceScheduleModal" tabindex="-1" aria-labelledby="advanceScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Advance Set Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="advance_employee_select" class="form-label">Select Employees:</label>
                    <select id="advance_employee_select" class="form-select" multiple placeholder="Search employees..."></select>
                </div>

                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Day</th>
                        <th>Schedule</th>
                    </tr>
                    </thead>
                    <tbody id="advance_schedule_table_body">
                    @php
                        $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    @endphp
                    @foreach($days as $day)
                        <tr>
                            <td>{{ $day }}</td>
                            <td>
                                <select class="form-control advance-schedule-select" data-day="{{ $day }}">
                                    <option value="">-- Select --</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button class="btn btn-success" id="save_advance_schedule">Save</button>
            </div>
        </div>
    </div>
</div>
