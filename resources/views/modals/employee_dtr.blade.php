<div class="modal fade" id="dtr_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">DTR Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 mt-3">
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
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
                            <label for="floatingInput">Month</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <select class="form-control" id="year">
                                <option value="">Select year</option>
                                @for($year=2023; $year<= \Carbon\Carbon::now()->format('Y'); $year++)
                                    <option>{{ $year }}</option>
                                @endfor
                            </select>
                            <label for="year" class="form-label">Year</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <select class="form-control" id="cutoff">
                                <option value="full" selected>Whole Month</option>
                                <option value="first">1st Half (1–15)</option>
                                <option value="second">2nd Half (16–end)</option>
                            </select>
                            <label for="floatingInput">Printing Period</label>
                        </div>
                    </div>
                    <!-- DTR Table -->
                    <div class="col-md-3">
                        <button class="btn btn-danger" style="padding: 1rem 1rem;" id="btn_print">
                            <i class="bi bi-printer mr-2"></i>
                            PRINT DTR
                        </button>

                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="reg_days_am" name="reg_days_am">
                            <label for="reg_days_am">Reg. days (AM)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="reg_days_pm" name="reg_days_pm">
                            <label for="reg_days_pm">Reg. days (PM)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="sat_days_am" name="sat_days_am">
                            <label for="sat_days_am">Saturday (AM)</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating mb-3">
                             <input type="text" class="form-control" id="sat_days_pm" name="sat_days_pm">
                            <label for="sat_days_pm">Saturday (PM)</label>
                        </div>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <h3>DTR</h3>
                            <table class="table table-bordered text-center">
                                <thead class="table-dark">
                                <tr>
                                    <th>Day</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                </tr>
                                </thead>
                                <tbody id="dtr_list">
                                <!-- More rows can be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <div class="d-flex justify-content-between">
                                <h3>Raw Data</h3>
                                <div>
                                    @if(in_array(strtolower((string) session('access_rights')), ['admin', 'it'], true))
                                        <button class="btn btn-success" onclick="addRawLog()">ADD RAW LOG</button>
                                    @endif
                                </div>
                            </div>

                            <table class="table table-bordered text-center">
                                <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th colspan="2"></th>
                                </tr>
                                </thead>
                                <tbody id="raw_list">

                                <!-- More rows can be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="add_log_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Time Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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

                <div class="text-end">
                    <button type="button" class="btn btn-primary" id="btn_save_log">Submit Log</button>
                </div>
                </form>
            </div>

        </div>
    </div>
</div>

