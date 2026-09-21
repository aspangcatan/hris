<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>

<div class="modal fade premium-modal" id="leave_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between pe-3" style="padding:0">
                <div class="modal-hero" style="flex:1">
                    <i class="bi bi-calendar-plus text-white" style="font-size:2.2rem"></i>
                </div>
                <button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8"></button>
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
                <div class="modal-title-text" id="file_leave_modal_title">Leave Application</div>
                <input type="hidden" id="is_lwop_flag" value="0">

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
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="toggleDateRangePanel()">Add Date Range</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">Add Date</button>
                        </div>
                    </div>

                    <div id="date_range_panel" class="border rounded p-3 mt-2 bg-light d-none">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Leave Type</label>
                                <select class="form-control form-control-sm" id="range_leave_type"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Start Date</label>
                                <input type="date" class="form-control form-control-sm" id="range_start_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">End Date</label>
                                <input type="date" class="form-control form-control-sm" id="range_end_date">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Hours/Day</label>
                                <select class="form-control form-control-sm" id="range_hours">
                                    <option value="4">4</option>
                                    <option value="8" selected>8</option>
                                    <option value="10">10</option>
                                    <option value="12">12</option>
                                    <option value="16">16</option>
                                    <option value="24">24</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-sm btn-primary w-100" onclick="generateDateRange()">Add</button>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">Weekends and declared holidays are skipped automatically.</div>
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
                        <div class="fw-bold mt-4">Attachments
                            <small class="text-muted fw-normal">(Required for Maternity/Paternity Leave — PDF, JPG, or PNG, max 5 MB each)</small>
                        </div>
                        {{-- Existing attachments shown when editing --}}
                        <div id="existing_attachments" class="mt-2 d-none">
                            <div class="small text-muted mb-1 fw-semibold">Current Files:</div>
                            <div id="existing_attachments_list" class="d-flex flex-wrap gap-2 mb-2"></div>
                        </div>
                        {{-- New file picker (multiple) --}}
                        <div class="mt-2">
                            <input type="file" class="form-control" id="leave_attachment"
                                   accept=".pdf,.jpg,.jpeg,.png" multiple>
                        </div>
                        {{-- Client-side preview of newly selected files --}}
                        <div id="new_files_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
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

                    <hr/>
                    <div class="d-flex align-items-center justify-content-between mt-4">
                        <div class="fw-bold">Applicant Signature</div>
                        <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('applicant')">Clear</button>
                    </div>
                    <div class="small text-muted mb-2">Draw your signature below, or upload an image. Required to submit — filing or editing this application signs it as an acknowledgment of its current contents.</div>
                    <div style="border: 1.5px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; position: relative;">
                        <canvas id="applicant_signature_canvas" style="display:block; width:100%; height:150px;"></canvas>
                        <div id="applicant_signature_placeholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:10px; color:#94a3b8; font-size:12px; pointer-events:none;">Draw your signature here, or upload an image below</div>
                    </div>
                    <div class="mt-2">
                        <label class="btn btn-sm btn-outline-secondary mb-0" for="applicant_signature_upload">Upload Signature</label>
                        <input type="file" id="applicant_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('applicant', event)">
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
                        <div class="step text-center d-none" id="step0">
                            <div class="circle">1</div>
                            <div class="label">HR Compliance</div>
                        </div>
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
                    <p><strong>Type:</strong> <span id="leave_type"></span> <span id="lwop_badge" class="badge bg-warning text-dark d-none">LWOP</span></p>
                    <p><strong>Details:</strong></p>
                    <div id="leave_details_container" class="border rounded p-3 bg-light"></div>
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
                            <th style="width: 26%;">Leave Type</th>
                            <th style="width: 18%;">Date</th>
                            <th style="width: 14%;">Duration</th>
                            <th style="width: 42%;">Disapprove</th>
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

                        <div id="approver_signature_section" class="mt-3 d-none">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label mb-0"><strong>Your Signature (required to Approve):</strong></label>
                                <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('approver')">Clear</button>
                            </div>
                            <div style="border: 1.5px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; position: relative;">
                                <canvas id="approver_signature_canvas" style="display:block; width:100%; height:130px;"></canvas>
                                <div id="approver_signature_placeholder" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:10px; color:#94a3b8; font-size:12px; pointer-events:none;">Draw your signature here, or upload an image below</div>
                            </div>
                            <div class="mt-2">
                                <label class="btn btn-sm btn-outline-secondary mb-0" for="approver_signature_upload">Upload Signature</label>
                                <input type="file" id="approver_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('approver', event)">
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-4 pb-3 @if(request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval')) d-flex justify-content-between @else d-flex justify-content-end @endif align-items-center">
                <div>
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <button type="button" class="btn btn-warning d-none" id="undoApprovalBtn">
                            <i class="bi bi-arrow-counterclockwise"></i> Undo Approval
                        </button>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    @if (request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
                        <button type="button" class="btn btn-danger" id="rejectLeaveBtn" value="Rejected">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                        <button type="button" class="btn btn-success" id="approveLeaveBtn" value="Approved">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button type="button" class="btn btn-success d-none" id="acknowledgeLeaveBtn" value="Acknowledged">
                            <i class="bi bi-eye-check"></i> I Acknowledge
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
