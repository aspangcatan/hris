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

                    <!-- Attachment — optional for all leave types, required for Maternity / Paternity Leave -->
                    <div id="attachment_section">
                        <hr/>
                        <div class="fw-bold mt-4">Attachments
                            <span id="attachment_required_badge" class="badge bg-danger ms-1 d-none">Required</span>
                            <small class="text-muted fw-normal">(Optional; required for Maternity/Paternity Leave — PDF, JPG, or PNG, max 5 MB each)</small>
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

{{--
    Leave Application view - shared by the employee's "View" (leave_application) and the
    approvers' review (leave_approval). The scripts fill these ids: leave_name, lwop_badge,
    leave_type, leave_commutation, view_attachment_row, leave_details_container,
    leave_credit_balance_section/_body, dates_applied_body, remarks_history, step0-step3
    (.circle), and on the approval page leave_remarks, hr_action_section, the HR fields,
    approver_signature_section and the action buttons.
--}}
@php($isLeaveApproval = request()->routeIs('leave_approval.*') || request()->routeIs('leave_approval'))
<div class="modal fade premium-modal lv-modal" id="viewLeaveModal" tabindex="-1" aria-labelledby="viewLeaveModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header lv-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-check text-white" style="font-size:2.2rem"></i>
                </div>
                <div class="lv-header-text">
                    <h5 class="lv-title" id="viewLeaveModalLabel">Leave Application</h5>
                    <div class="lv-subtitle">
                        <i class="bi bi-person"></i>
                        <span id="leave_name"></span>
                        <span id="lwop_badge" class="lv-pill lv-pill-warn d-none">LWOP</span>
                    </div>
                </div>
            </div>

            <div class="modal-body lv-body">
                {{-- Approval progress: numbered by visible step; done steps show a tick / cross --}}
                <div class="lv-steps">
                    <div class="lv-step d-none" id="step0">
                        <div class="circle"></div>
                        <div class="lv-step-label">HR Compliance</div>
                        <div class="lv-step-status"></div>
                    </div>
                    <div class="lv-step" id="step1">
                        <div class="circle"></div>
                        <div class="lv-step-label">Supervisor</div>
                        <div class="lv-step-status"></div>
                    </div>
                    <div class="lv-step" id="step2">
                        <div class="circle"></div>
                        <div class="lv-step-label">Division Head</div>
                        <div class="lv-step-status"></div>
                    </div>
                    <div class="lv-step" id="step3">
                        <div class="circle"></div>
                        <div class="lv-step-label">Human Resource</div>
                        <div class="lv-step-status"></div>
                    </div>
                </div>

                {{-- At a glance --}}
                <div class="lv-summary">
                    <div class="lv-tile">
                        <div class="lv-tile-label">Leave type</div>
                        <div class="lv-tile-value" id="leave_type"></div>
                    </div>
                    <div class="lv-tile">
                        <div class="lv-tile-label">Commutation</div>
                        <div class="lv-tile-value" id="leave_commutation"></div>
                    </div>
                    <div class="lv-tile" id="view_attachment_row" style="display:none;"></div>
                </div>

                {{-- Leave credit balance - only the leave types filed in this application --}}
                <section class="lv-card d-none" id="leave_credit_balance_section">
                    <h6 class="lv-card-title"><i class="bi bi-wallet2"></i> Leave Credit Balance</h6>
                    <div class="table-responsive">
                        <table class="table lv-table mb-0">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th class="text-end">Total earned</th>
                                <th class="text-end">Less this application</th>
                                <th class="text-end">Balance</th>
                            </tr>
                            </thead>
                            <tbody id="leave_credit_balance_body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="lv-card">
                    <h6 class="lv-card-title"><i class="bi bi-card-text"></i> Details of Leave</h6>
                    <div class="lv-details" id="leave_details_container"></div>
                </section>

                <section class="lv-card">
                    <h6 class="lv-card-title"><i class="bi bi-calendar3"></i> Dates Applied</h6>
                    <div class="table-responsive">
                        <table class="table lv-table mb-0">
                            <thead>
                            <tr>
                                <th style="width: 30%;">Leave type</th>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 15%;">Duration</th>
                                <th style="width: 35%;">{{ $isLeaveApproval ? 'Disapprove' : 'Status' }}</th>
                            </tr>
                            </thead>
                            <tbody id="dates_applied_body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="lv-card">
                    <h6 class="lv-card-title"><i class="bi bi-chat-left-text"></i> Remarks History</h6>
                    <div class="lv-remarks" id="remarks_history"></div>
                </section>

                @if ($isLeaveApproval)
                    <section class="lv-card lv-card-action">
                        <h6 class="lv-card-title"><i class="bi bi-pencil-square"></i> Your Decision</h6>

                        <label for="leave_remarks" class="form-label">Remarks</label>
                        <textarea id="leave_remarks" class="form-control" rows="3"
                                  placeholder="Add a remark for the employee and the next approver (optional)"></textarea>

                        {{-- HR only: CSC Form 6 section 7.A / 7.C, printed on the leave form --}}
                        <div id="hr_action_section" class="lv-subsection d-none">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="cert_as_of" class="form-label">7.A Certification of leave credits &mdash; as of</label>
                                    <input type="date" class="form-control" id="cert_as_of">
                                </div>
                            </div>
                            <div class="form-label mt-3">7.C Approved for</div>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-3">
                                    <label for="approved_days_with_pay" class="form-label lv-sublabel">Days with pay</label>
                                    <input type="number" class="form-control" id="approved_days_with_pay" min="0" step="any">
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label for="approved_days_without_pay" class="form-label lv-sublabel">Days without pay</label>
                                    <input type="number" class="form-control" id="approved_days_without_pay" min="0" step="any">
                                </div>
                                <div class="col-md-6">
                                    <label for="approved_others" class="form-label lv-sublabel">Others (specify)</label>
                                    <input type="text" class="form-control" id="approved_others" maxlength="255">
                                </div>
                            </div>
                            <div id="hr_action_guide" class="form-text mt-2"></div>
                        </div>

                        <div id="approver_signature_section" class="lv-subsection d-none">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0">Your signature <span class="text-danger">*</span> <span class="lv-sublabel">(required to approve)</span></label>
                                <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('approver')">Clear</button>
                            </div>
                            <div class="lv-signature">
                                <canvas id="approver_signature_canvas" style="display:block; width:100%; height:130px;"></canvas>
                                <div id="approver_signature_placeholder" class="lv-signature-hint">Draw your signature here, or upload an image below</div>
                            </div>
                            <div class="mt-2">
                                <label class="btn btn-sm btn-outline-secondary mb-0" for="approver_signature_upload"><i class="bi bi-upload me-1"></i>Upload signature</label>
                                <input type="file" id="approver_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('approver', event)">
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            <div class="modal-footer lv-footer">
                @if ($isLeaveApproval)
                    <button type="button" class="btn btn-outline-warning me-auto d-none" id="undoApprovalBtn">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Undo Approval
                    </button>
                @endif
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                @if ($isLeaveApproval)
                    <button type="button" class="btn btn-danger" id="rejectLeaveBtn" value="Rejected">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                    <button type="button" class="btn btn-success" id="approveLeaveBtn" value="Approved">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-success d-none" id="acknowledgeLeaveBtn" value="Acknowledged">
                        <i class="bi bi-check2-all me-1"></i>I Acknowledge
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    function renderLeaveCreditBalances(rows) {
        rows = rows || [];
        const fmt = v => Number(v || 0).toFixed(3);
        $('#leave_credit_balance_body').html(rows.map(r =>
            `<tr><td>${r.type}</td><td class="text-end">${fmt(r.total_earned)}</td><td class="text-end">${fmt(r.less)}</td><td class="text-end fw-bold">${fmt(r.balance)}</td></tr>`
        ).join(''));
        $('#leave_credit_balance_section').toggleClass('d-none', rows.length === 0);
    }

    function lvEscape(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    // Attachments tile of the leave view (multi-attachment list, or the legacy single file).
    function renderLeaveAttachments(data) {
        const files = (data.attachments && data.attachments.length > 0)
            ? data.attachments
            : (data.attachment_url ? [{ url: data.attachment_url, original_name: 'View attachment' }] : []);
        if (files.length === 0) {
            $('#view_attachment_row').hide().empty();
            return;
        }
        const links = files.map(a =>
            `<a href="${a.url}" target="_blank" rel="noopener" class="lv-file"><i class="bi bi-paperclip"></i><span>${lvEscape(a.original_name)}</span></a>`
        ).join('');
        $('#view_attachment_row')
            .html(`<div class="lv-tile-label">Attachments</div><div class="lv-files">${links}</div>`)
            .show();
    }

    // Remarks history as a short timeline: approver, decision, remark.
    function renderLeaveRemarks(approvals) {
        const steps = [
            ['hr_compliance', 'HR Compliance'],
            ['sh', 'Supervisor'],
            ['dh', 'Division Head'],
            ['hr', 'Human Resource'],
        ];
        const html = steps.map(([key, label]) => {
            const a = (approvals || {})[key];
            if (!a || !a.remarks) return '';
            const status = String(a.status || 'pending').toLowerCase();
            return `
                <div class="lv-remark ${lvEscape(status)}">
                    <div class="lv-remark-top">
                        <span class="lv-remark-role">${label}</span>
                        <span class="lv-pill lv-pill-${lvEscape(status)}">${lvEscape(status)}</span>
                    </div>
                    <p class="lv-remark-text">${lvEscape(a.remarks)}</p>
                </div>`;
        }).join('');
        $('#remarks_history').html(html || '<p class="lv-empty">No remarks yet.</p>');
    }
</script>
