<div class="modal fade" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <iframe title="Leave Application" class="w-100" style="min-height: 700px;max-height: 700px"
                        id="leave_frame"></iframe>
            </div>
            <hr/>
            <div class="table-responsive pe-2 ps-2">
                <table class="table">
                    <thead>
                    <tr>
                        <th style="width: 40%">Department</th>
                        <th style="width: 30%">Status</th>
                        <th style="width: 30%">Date Approved</th>
                    </tr>
                    </thead>
                    <tbody id="leave_status_logs">
                    <tr>
                        <td>Section Head</td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Division Head</td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Human Resource</td>
                        <td></td>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            @if(Route::currentRouteName() == "leave_approval")
                <div class="modal-footer">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-lg btn-danger me-1 d-none" id="btn_reject_leave">REJECT</button>
                        <button class="btn btn-lg btn-success ms-1" id="btn_approve_leave">APPROVE</button>
                    </div>
                </div>
            @endif
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


