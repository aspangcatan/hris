<div class="modal fade premium-modal" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-plus text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <iframe title="Leave Application" class="w-100" style="min-height: 700px;max-height: 700px"
                        id="leave_frame"></iframe>
            </div>
            <hr/>
            @if(Route::currentRouteName() == "leave_approval")
                <div class="modal-footer">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-action btn-delete me-1 d-none" id="btn_reject_leave" title="Reject"><i class="bi bi-x"></i></button>
                        <button class="btn btn-action btn-edit ms-1" id="btn_approve_leave" title="Approve"><i class="bi bi-check2"></i></button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="file_leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-plus text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Leave Application</div>

                <div class="text-white bg-danger rounded p-2 error_container d-none">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list mb-0"></ul>
                </div>

                <div class="mt-4" id="leave_form">
                    <!-- Date Applied for Leave -->
                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="fw-bold">Date applied for leave</div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">Add Date</button>
                    </div>

                    <table class="table table-bordered table-sm mt-2" id="dates_table">
                        <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Duration (hrs)</th>
                            <th style="width: 5%">Action</th>
                        </tr>
                        </thead>
                        <tbody id="dates_table_body">
                        </tbody>
                    </table>

                    <hr/>

                    <!-- Details of Leave (groups rendered dynamically by JS from LEAVE_DETAIL_CONFIG) -->
                    <div id="details_leave">
                        <div class="fw-bold mt-4">Details of leave</div>
                        <div id="detail_groups_container"></div>
                    </div>

                    <!-- Attachment — required for Maternity / Paternity Leave -->
                    <div id="attachment_section" style="display:none;">
                        <hr/>
                        <div class="fw-bold mt-4">Attachment
                            <small class="text-muted fw-normal">(Required for Maternity/Paternity Leave — PDF, JPG, or PNG, max 5 MB)</small>
                        </div>
                        <div class="mt-2">
                            <input type="file" class="form-control" id="leave_attachment"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>

                    <hr/>
                    <!-- Commutation — auto-set from leave type; read-only -->
                    <div class="fw-bold mt-4">Commutation</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_not_requested" disabled>
                                <label class="form-check-label" for="commutation_not_requested">Not Requested</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="commutation" id="commutation_requested" disabled>
                                <label class="form-check-label" for="commutation_requested">Requested</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">CLOSE</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_leave">SUBMIT</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="viewLeaveModal" tabindex="-1" aria-labelledby="viewLeaveModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-check text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body p-4">
                <div class="modal-title-text">Leave Application</div>
                <!-- Steps -->
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

                <!-- Leave Info -->
                <div class="mt-5">
                    <p><strong>Name:</strong> <span id="leave_name"></span></p>
                    <p><strong>Type:</strong> <span id="leave_type"></span></p>
                    <p><strong>Details:</strong></p>
                    <textarea id="leave_details" class="form-control" rows="4" readonly></textarea>
                    <p class="mt-3"><strong>Commutation:</strong> <span id="leave_commutation"></span></p>
                </div>

                <!-- Attachment -->
                <div id="view_attachment_row" class="mt-3" style="display:none;">
                    <p><strong>Attachment:</strong>
                        <a id="view_attachment_link" href="#" target="_blank" class="ms-1">
                            <i class="bi bi-paperclip"></i> View File
                        </a>
                    </p>
                </div>

                <!-- Dates Applied -->
                <div class="mt-3">
                    <p><strong>Dates Applied:</strong></p>
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th style="width: 30%;">Date</th>
                            <th style="width: 25%;">Duration</th>
                        </tr>
                        </thead>
                        <tbody id="dates_applied_body">
                        </tbody>
                    </table>
                </div>

                <!-- Remarks Section -->
                <div class="mt-4">
                    <label class="form-label"><strong>Remarks History:</strong></label>
                    <div id="remarks_history" class="border rounded p-3 mb-3 bg-light"
                         style="max-height: 200px; overflow-y: auto;">
                    </div>

                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <label for="leave_remarks" class="form-label"><strong>Add Your Remark:</strong></label>
                        <textarea id="leave_remarks"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Enter your remarks here..."></textarea>
                    @endif
                </div>
            </div>

            @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                <div class="modal-footer border-top-0 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-warning" id="undoApprovalBtn" style="display:none;">
                            <i class="bi bi-arrow-counterclockwise"></i> Undo Approval
                        </button>
                    </div>
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
