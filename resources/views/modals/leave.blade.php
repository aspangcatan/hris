<div class="modal fade" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Leave Status</div>
                        <div class="row">
                            <div class="col-md-4">
                                <label>Type:</label>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold" id="status_leave_type">Vacation Leave</div>
                            </div>
                            <div class="col-md-4">
                                <label>Details:</label>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold" id="status_leave_details"></div>
                            </div>
                            <div class="col-md-4">
                                <label>Specific Details:</label>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold" id="status_leave_extra"></div>
                            </div>
                            <div class="col-md-4">
                                <label>Date applied:</label>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold" id="status_date_applied"></div>
                            </div>
                            <div class="col-md-4">
                                <label>Commutation:</label>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold" id="status_commutation"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="card-title">Activity Logs</div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Origin</th>
                                    <th>Remarks</th>
                                    <th>Destination</th>
                                    <th>Date forwarded</th>
                                </tr>
                                </thead>
                                <tbody id="leave_status_list">

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="file_leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body">

                <h5 class="card-title">Leave Application</h5>
                <div class="text-white bg-danger rounded p-2 mt-5 error_container d-none">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="mt-3" id="leave_form">
                    <div class="fw-bold">A. Type of Leave to be availed of</div>
                    <div class="row mt-1" id="leave_list"></div>
                    <div class="fw-bold mt-3">B. Details of leave</div>
                    <div class="mt-1">In case of Vacation/Special Privilege Leave:</div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vacation_leave">
                                <div class="d-flex align-items-center">
                                    <label class="form-check-label">
                                        Within the Philippines
                                    </label>
                                    <input class="form-control underline w-50 ms-2" type="text"
                                           id="within_philippines"/>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="vacation_leave">
                                <div class="d-flex align-items-center">
                                    <label class="form-check-label">Abroad (Specify)</label>
                                    <input class="form-control underline w-50 ms-2" type="text" id="abroad"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2">In case of Sick Leave:</div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sick_leave">
                        <div class="d-flex align-items-center">
                            <label class="form-check-label">
                                In Hospital (Specify Illness)
                            </label>
                            <input class="form-control underline w-50 ms-2" type="text" id="in_hospital"/>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sick_leave">
                        <div class="d-flex align-items-center">
                            <label class="form-check-label">
                                Out Patient (Specify Illness)
                            </label>
                            <input class="form-control underline w-50 ms-2" type="text" id="out_hospital"/>
                        </div>
                    </div>

                    <div class="mt-2">In case of Special Leave Benefits for Women:</div>
                    <div class="d-flex align-items-center">
                        <label class="form-check-label">
                            (Specify Illness)
                        </label>
                        <input class="form-control underline w-75 ms-2" type="text" id="special_leave"/>
                    </div>

                    <div class="mt-2">In case of Study Leave:</div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="study_leave">
                        <label class="form-check-label">Completion of Master's Degree</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="study_leave">
                        <label class="form-check-label">BAR/Board Examination Review</label>
                    </div>

                    <div class="mt-2">Other purpose:</div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="other_purpose">
                        <label class="form-check-label">Monetization of Leave Credits</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="other_purpose">
                        <label class="form-check-label">Terminal Leave</label>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="fw-bold">C. Date applied for leave</div>
                            <input class="datepicker form-control" id="dates_applied"/>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-bold">D. Commutation</div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="commutation">
                                        <label class="form-check-label">Not Requested</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="commutation">
                                        <label class="form-check-label">Requested</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-5">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">CLOSE</button>
                    <button class="btn btn-primary" id="btn_save_leave">SUBMIT</button>
                </div>
            </div>
        </div>
    </div>
</div>


