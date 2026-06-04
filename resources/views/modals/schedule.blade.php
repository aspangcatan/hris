<div class="modal fade premium-modal" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-clock text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Update Schedule</div>
                <input type="hidden" id="selectedEmployeeId">
                <input type="hidden" id="selectedDay">
                <input type="hidden" id="selectedCode">

                <label for="scheduleSelect" class="form-label fw-semibold">Select Schedule:</label>
                <select class="form-select" id="scheduleSelect"></select>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-modal-save" id="btn_save_schedule">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #advanceScheduleModal .modal-content {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(16, 24, 40, 0.2);
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    }

    #advanceScheduleModal .modal-header {
        border-bottom: 0;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #0f766e 0%, #0f766e 50%, #0ea5a4 100%);
        color: #fff;
    }

    #advanceScheduleModal .modal-title {
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    #advanceScheduleModal .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.9;
    }

    #advanceScheduleModal .premium-panel {
        background: #fff;
        border: 1px solid #e6ebf2;
        border-radius: 14px;
        padding: 1rem;
        box-shadow: 0 8px 20px rgba(16, 24, 40, 0.05);
    }

    #advanceScheduleModal .panel-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #344054;
        margin-bottom: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }

    #advanceScheduleModal #advance_employee_search {
        border-radius: 10px;
        border: 1px solid #d5deea;
        padding: 0.6rem 0.75rem;
    }

    #advanceScheduleModal #advance_employee_search:focus {
        border-color: #0ea5a4;
        box-shadow: 0 0 0 0.2rem rgba(14, 165, 164, 0.14);
    }

    #advanceScheduleModal .employee-table thead th,
    #advanceScheduleModal .weekly-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f2f6fb;
        color: #475467;
        font-weight: 700;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.35px;
    }

    #advanceScheduleModal .employee-table tbody tr:hover {
        background: #f8fbff;
    }

    #advanceScheduleModal .weekly-table select {
        min-width: 110px;
        border-radius: 8px;
        border-color: #d5deea;
    }

    #advanceScheduleModal .range-input {
        border-radius: 10px;
        border: 1px solid #d5deea;
        padding: 0.55rem 0.75rem;
    }

    #advanceScheduleModal .range-input:focus {
        border-color: #0ea5a4;
        box-shadow: 0 0 0 0.2rem rgba(14, 165, 164, 0.14);
    }

    #advanceScheduleModal .range-help {
        font-size: 0.8rem;
        color: #667085;
    }

    #advanceScheduleModal .range-error {
        font-size: 0.82rem;
        color: #b42318;
        font-weight: 600;
        min-height: 1.1rem;
    }

    #advanceScheduleModal .modal-footer {
        border-top: 1px solid #e9eef5;
        background: #f8fafc;
    }

    #advanceScheduleModal #save_advance_schedule {
        border-radius: 10px;
        padding: 0.52rem 1.1rem;
        font-weight: 600;
        background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%);
        border: none;
        box-shadow: 0 10px 18px rgba(15, 118, 110, 0.24);
    }
</style>

<div class="modal fade" id="advanceScheduleModal" tabindex="-1" aria-labelledby="advanceScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">Advance Schedule Builder</h5>
                <button type="button" class="btn-close fs-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="premium-panel h-100">
                            <div class="panel-title">Employee Selection</div>
                            <label for="advance_employee_search" class="form-label mb-1">Search Employee</label>
                            <input type="text" id="advance_employee_search" class="form-control" placeholder="Type name or designation...">

                            <div class="table-responsive mt-3" style="max-height: 360px;">
                                <table class="table table-bordered table-sm align-middle employee-table mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width: 50px;">
                                            <input type="checkbox" id="advance_select_all_employees">
                                        </th>
                                        <th>Employee</th>
                                        <th>Designation</th>
                                    </tr>
                                    </thead>
                                    <tbody id="advance_employee_table_body">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No employees loaded yet.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="row">
   <div class="col-lg-12">
                        <div class="premium-panel">
                            <div class="panel-title">Schedule Range</div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label for="advance_start_date" class="form-label mb-1">Start Date</label>
                                    <input type="date" id="advance_start_date" class="form-control range-input" required>
                                </div>
                                <div class="col-md-5">
                                    <label for="advance_end_date" class="form-label mb-1">End Date</label>
                                    <input type="date" id="advance_end_date" class="form-control range-input" required>
                                </div>
                                <div class="col-md-2">
                                    <span class="range-help d-block">Pick a custom range. Only dates inside the range will be scheduled.</span>
                                </div>
                            </div>
                            <div id="advance_range_error" class="range-error mt-2"></div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="premium-panel h-100">
                            <div class="panel-title">Weekly Template</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle weekly-table mb-0">
                                    <thead>
                                    <tr>
                                        @php
                                            $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                                        @endphp
                                        @foreach($days as $day)
                                            <th>{{ $day }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        @foreach($days as $day)
                                            <td>
                                                <select class="form-select form-select-sm advance-schedule-select" data-day="{{ $day }}">
                                                    <option value="">-- Select --</option>
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>
                 
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="save_advance_schedule" disabled>Apply Schedule</button>
            </div>
        </div>
    </div>
</div>
