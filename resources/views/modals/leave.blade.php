<div class="modal fade" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Application</h5>
                <button type="button" class="btn" data-bs-dismiss="modal" aria-label="Close"
                        style="background: transparent">X
                </button>
            </div>
            <div class="modal-body">
                <iframe title="Leave Application" class="w-100" style="min-height: 700px;max-height: 700px"
                        id="leave_frame"></iframe>
            </div>
            <hr/>
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
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-body">

                <h5 class="fw-bold mb-3">Leave Application</h5>

                <div class="text-white bg-danger rounded p-2 error_container d-none">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list mb-0"></ul>
                </div>

                <div class="mt-4" id="leave_form">
                    <!-- A. Type of Leave -->
                    <div class="fw-bold">A. Type of Leave to be availed of</div>
                    <div class="row mt-2" id="leave_list"></div>

                    <hr/>

                    <!-- B. Details of Leave -->
                    <div class="fw-bold mt-4">B. Details of leave</div>

                    <div class="mt-2">In case of Vacation/Special Privilege Leave:</div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-check d-flex align-items-center">
                                <input class="form-check-input me-2" type="radio" name="vacation_leave">
                                <label class="form-check-label me-2">Within the Philippines</label>
                                <input class="form-control w-50" type="text" id="within_philippines"/>
                            </div>
                        </div>
                        <div class="col-md-12 mt-2">
                            <div class="form-check d-flex align-items-center">
                                <input class="form-check-input me-2" type="radio" name="vacation_leave">
                                <label class="form-check-label me-2">Abroad (Specify)</label>
                                <input class="form-control w-50" type="text" id="abroad"/>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">In case of Sick Leave:</div>
                    <div class="form-check d-flex align-items-center mt-1">
                        <input class="form-check-input me-2" type="radio" name="sick_leave">
                        <label class="form-check-label me-2">In Hospital (Specify Illness)</label>
                        <input class="form-control w-50" type="text" id="in_hospital"/>
                    </div>
                    <div class="form-check d-flex align-items-center mt-2">
                        <input class="form-check-input me-2" type="radio" name="sick_leave">
                        <label class="form-check-label me-2">Out Patient (Specify Illness)</label>
                        <input class="form-control w-50" type="text" id="out_hospital"/>
                    </div>

                    <div class="mt-3">In case of Special Leave Benefits for Women:</div>
                    <div class="d-flex align-items-center mt-1">
                        <label class="form-check-label me-2">(Specify Illness)</label>
                        <input class="form-control w-75" type="text" id="special_leave"/>
                    </div>

                    <div class="mt-3">In case of Study Leave:</div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="study_leave">
                        <label class="form-check-label">Completion of Master's Degree</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="study_leave">
                        <label class="form-check-label">BAR/Board Examination Review</label>
                    </div>

                    <hr/>

                    <!-- C. Leave Dates -->
                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="fw-bold">C. Date applied for leave</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">Add Date
                        </button>
                    </div>

                    <table class="table table-bordered table-sm mt-2" id="dates_table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Duration (hrs)</th>
                            <th style="width: 5%">Action</th>
                        </tr>
                        </thead>
                        <tbody id="dates_table_body">
                        <tr>
                            <td><input type="date" class="form-control" name="leave_dates[]"></td>
                            <td><input type="number" class="form-control" name="leave_hours[]" min="1" max="24"></td>
                            <td>
                                <button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <hr/>

                    <!-- D. Commutation -->
                    <div class="fw-bold mt-4">D. Commutation</div>
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

                <div class="text-end mt-4">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">CLOSE</button>
                    <button class="btn btn-primary" id="btn_save_leave">SUBMIT</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewLeaveModal" tabindex="-1" aria-labelledby="viewLeaveModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border border-dark">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title" id="viewLeaveModalLabel">Leave Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-5">
                    <div class="steps d-flex justify-content-between position-relative">
                        <div class="step text-center" id="step1">
                            <div class="circle">1</div>
                            <div class="label">Supervisor</div>
                        </div>
                        <div class="step text-center" id="step2">
                            <div class="circle">2</div>
                            <div class="label">Division Head</div>
                        </div>
                        <div class="step text-center" id="step3">
                            <div class="circle">3</div>
                            <div class="label">Human Resource</div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <p><strong>Name:</strong> <span id="leave_name"></span></p>
                    <p><strong>Type:</strong> <span id="leave_type"></span></p>
                    <p><strong>Details:</strong></p>
                    <textarea id="leave_details" class="form-control" rows="4" readonly></textarea>
                    <p class="mt-3"><strong>Commutation:</strong> <span id="leave_commutation"></span></p>
                </div>

                <div class="mt-3">
                    <p><strong>Dates Applied:</strong></p>
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Date</th>
                            <th style="width: 25%;">Duration</th>
                        </tr>
                        </thead>
                        <tbody id="dates_applied_body"></tbody>
                    </table>
                </div>

                <!-- Remarks Section -->
                <div class="mt-4">
                    <label for="leave_remarks" class="form-label"><strong>Remarks:</strong></label>
                    <textarea id="leave_remarks"
                              class="form-control"
                              rows="3"
                              placeholder="Enter remarks here..."
                              @if(request()->routeIs('leave_application')) readonly @endif>
                    </textarea>

                </div>
            </div>

            @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                <div class="modal-footer border-top-0 d-flex justify-content-end">
                    <div>
                        <button type="button" class="btn btn-danger" id="rejectLeaveBtn" value="Rejected">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn btn-success" id="approveLeaveBtn" value="Approved">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>


