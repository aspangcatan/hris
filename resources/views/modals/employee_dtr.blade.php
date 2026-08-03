@php($rawLogManagers = in_array(strtolower((string) session('access_rights')), ['admin', 'it', 'dh'], true))

<style>
    .dtr-modal-theme .modal-content {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 28px 60px rgba(15, 23, 42, 0.22);
    }

    .dtr-modal-theme .modal-header {
        background: linear-gradient(135deg, #0f7b7a, #0b6b77);
        color: #fff;
        border: 0;
        padding: 1.1rem 1.5rem;
        justify-content: center;
        position: relative;
    }

    .dtr-modal-theme .modal-header .btn-close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        filter: invert(1);
        opacity: 0.9;
    }

    .dtr-modal-theme .modal-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .dtr-modal-theme .modal-title {
        font-weight: 700;
        letter-spacing: 0.02em;
        margin-top: 0.7rem;
    }

    .dtr-modal-theme .modal-body {
        background: #f8fafc;
        padding: 1.6rem;
    }

    .dtr-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1rem 1.1rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .dtr-card-title {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #475569;
        font-weight: 700;
        margin-bottom: 0.8rem;
    }

    .dtr-controls .form-floating > .form-control,
    .dtr-controls .form-floating > .form-select {
        border-radius: 12px;
        border: 1px solid #d8e1ee;
        background: #fff;
    }

    .dtr-table-wrap {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        height: 100%;
    }

    .dtr-table-wrap table {
        margin-bottom: 0;
    }

    .dtr-table-wrap table thead th {
        background: #f1f5f9;
        color: #0f172a;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .dtr-table-wrap table tbody td {
        color: #1e293b;
        font-weight: 500;
        vertical-align: middle;
    }

    .dtr-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.8rem;
    }

    .dtr-section-head h6 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
    }

    .dtr-table-col {
        display: flex;
        flex-direction: column;
    }

    .dtr-table-col .dtr-table-wrap {
        flex: 1 1 auto;
    }

    .dtr-footer-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        margin-top: 1.4rem;
    }
</style>

<div class="modal fade dtr-modal-theme" id="dtr_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="text-center">
                    <div class="modal-icon">
                        <i class="bi bi-clock-history fs-3"></i>
                    </div>
                    <h5 class="modal-title text-white fs-1">Daily Time Record Viewer</h5>
                </div>
                <button type="button" class="btn-close fs-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="dtr-card dtr-controls mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-control" id="month">
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
                                <select class="form-control" id="year">
                                    <option value="">Select year</option>
                                    @for($year = 2023; $year <= \Carbon\Carbon::now()->format('Y'); $year++)
                                        <option>{{ $year }}</option>
                                    @endfor
                                </select>
                                <label for="year" class="form-label">Year</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-control" id="cutoff">
                                    <option value="full" selected>Whole Month</option>
                                    <option value="first">1st Half (1-15)</option>
                                    <option value="second">2nd Half (16-end)</option>
                                </select>
                                <label for="cutoff">Printing Period</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-action btn-view w-100" id="btn_print" title="Print DTR">
                                <i class="bi bi-printer"></i>
                                PRINT DTR
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dtr-card dtr-controls mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="enable_automation" name="enable_automation">
                                    <option value="yes" selected>Yes</option>
                                    <option value="no">No</option>
                                </select>
                                <label for="enable_automation">Enable Automation</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="show_day_names" name="show_day_names">
                                    <option value="yes" selected>Yes</option>
                                    <option value="no">No</option>
                                </select>
                                <label for="show_day_names">Show Weekday Names</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="show_in_charge_name" name="show_in_charge_name">
                                    <option value="yes" selected>Yes</option>
                                    <option value="no">No</option>
                                </select>
                                <label for="show_in_charge_name">Show In Charge Name</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="reg_days_am" name="reg_days_am">
                                <label for="reg_days_am">Reg. days (AM)</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="reg_days_pm" name="reg_days_pm">
                                <label for="reg_days_pm">Reg. days (PM)</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="sat_days_am" name="sat_days_am">
                                <label for="sat_days_am">Saturday (AM)</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="sat_days_pm" name="sat_days_pm">
                                <label for="sat_days_pm">Saturday (PM)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-6 dtr-table-col">
                        <div class="dtr-section-head">
                            <h6 class="mt-1">DTR</h6>
                        </div>
                        <div class="table-responsive dtr-table-wrap mt-2">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                    </tr>
                                </thead>
                                <tbody id="dtr_list">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6 dtr-table-col">
                        <div class="dtr-section-head">
                            <h6>Raw Data</h6>
                            @if($rawLogManagers)
                                <button class="btn btn-action btn-edit" onclick="addRawLog()" title="Add Raw Log">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            @endif
                        </div>
                        <div class="table-responsive dtr-table-wrap">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        @if($rawLogManagers)
                                            <th colspan="2"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody id="raw_list">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dtr-modal-theme" id="add_log_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="text-center w-100">
                    <div class="modal-icon">
                        <i class="bi bi-plus-circle fs-3"></i>
                    </div>
                    <h5 class="modal-title text-white fs-1">Add Time Log</h5>
                </div>
            </div>
            <div class="modal-body">
                <div class="dtr-card">
                    <div class="mb-3">
                        <label for="log_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="log_date" name="date" required>
                    </div>

                    <div class="mb-3">
                        <label for="log_time" class="form-label">Time</label>
                        <input type="time" class="form-control" id="log_time" name="time" required>
                    </div>

                    <div class="mb-3">
                        <label for="log_time" class="form-label">Status (IN/OUT)</label>
                        <select class="form-control" id="log_status">
                            <option value="IN" selected>IN</option>
                            <option value="OUT">OUT</option>
                        </select>
                    </div>
                </div>

                <div class="dtr-footer-actions">
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button type="button" class="btn btn-success" id="btn_save_log" title="Submit Log">
                        <i class="bi bi-check2-circle"></i>
                        Save Changes
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
