<div class="modal fade premium-modal" id="dtr_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-check text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="civil_service_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="mt-3 text-secondary">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-0">
                                <div class="card-body p-0">
                                    <div class="form-floating">
                                        <select class="form-control bg-white" id="filter_year" onchange="getDTRLogs()">
                                            <option></option>
                                            @for($i=2023; $i<= \Carbon\Carbon::now()->year; $i++)
                                                <option>{{ $i }}</option>
                                            @endfor
                                        </select>
                                        <label for="floatingInput">Filter year</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card mt-1">
                                <div class="card-body">
                                    <div class="card-title">Employee Record</div>
                                    <div class="table-responsive">
                                        <table class="table text-center">
                                            <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Absent</th>
                                                <th>Late/Undertime</th>
                                                <th colspan="2" style="width: 8%"></th>
                                            </tr>
                                            </thead>
                                            <tbody id="dtr_list"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title">Manage DTR</div>
                                    <div class="form-floating mb-3">
                                        <select class="form-control" id="month">
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
                                        <label for="floatingInput">Month</label>
                                    </div>
                                    <div class="form-floating mb-3">
                                        <select class="form-control" id="year">
                                            @for($i=2023; $i<= \Carbon\Carbon::now()->year; $i++)
                                                <option>{{ $i }}</option>
                                            @endfor
                                        </select>
                                        <label for="floatingInput">Year</label>
                                    </div>
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="absent">
                                        <label for="floatingInput">Absent</label>
                                    </div>
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="late">
                                        <label for="floatingInput">Late/Undertime</label>
                                    </div>
                                    <div class="text-end mt-4">
                                        <button class="btn btn-secondary btn-modal-close" id="btn_cancel" onclick="cancel()">CANCEL</button>
                                        <button class="btn btn-success btn-modal-save" id="btn_save" onClick="store()">SAVE</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
