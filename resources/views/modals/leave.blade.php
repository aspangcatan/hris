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

{{--
    File / edit a leave (regular or LWOP). Same look as the view dialog (lv-modal).
    js/leave_application fills/reads these ids: file_leave_modal_title, is_lwop_flag, leave_form,
    date_range_panel + range_*, dates_table(_body), detail_groups_container, attachment_required_badge,
    existing_attachments(_list), leave_attachment, new_files_preview, commutation_*,
    applicant_signature_*, btn_save_leave.
--}}
<div class="modal fade premium-modal lv-modal lf-modal" id="file_leave_modal" tabindex="-1" aria-labelledby="file_leave_modal_title" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header lv-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-plus text-white" style="font-size:2.2rem"></i>
                </div>
                <div class="lv-header-text">
                    <h5 class="lv-title" id="file_leave_modal_title">Leave Application</h5>
                    <div class="lv-subtitle">Add your dates, fill in the details, then sign to submit.</div>
                </div>
            </div>

            <div class="modal-body lv-body">
                <input type="hidden" id="is_lwop_flag" value="0">

                <div class="text-white bg-danger rounded p-2 error_container d-none">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list mb-0"></ul>
                </div>

                <div id="leave_form" class="lf-form">
                    {{-- Dates --}}
                    <section class="lv-card">
                        <div class="lf-card-top">
                            <h6 class="lv-card-title mb-0"><i class="bi bi-calendar3"></i> Dates Applied for Leave</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDateRangePanel()">
                                    <i class="bi bi-calendar-range me-1"></i>Add Date Range
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">
                                    <i class="bi bi-plus-lg me-1"></i>Add Date
                                </button>
                            </div>
                        </div>

                        {{-- Filing guide. Hour-based leaves are deducted by the hours filed (8 hours = 1.000),
                             so the hours must match the filer's schedule; day-based leaves (Leave Settings >
                             Deduct by = Days) are filed as whole/half days. The day-based list is read from
                             leave_types so it follows Leave Settings. --}}
                        @php
                            $dayBasedLeaveNames = \Illuminate\Support\Facades\DB::table('hris.leave_types')
                                ->where('status', 'active')
                                ->where('deduction_unit', 'days')
                                ->orderBy('description')
                                ->pluck('description');
                        @endphp
                        <div class="alert alert-info d-flex gap-2 align-items-start py-2 px-3 mb-3 small" role="note" id="leave_hours_notice">
                            <i class="bi bi-info-circle-fill mt-1"></i>
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <strong>Vacation, Sick and other leaves counted in hours:</strong>
                                    select the hours that match your schedule on each day (8 hours = 1.000 day).
                                    <ul class="mb-0 mt-1 ps-3">
                                        <li>8-hour schedule: <strong>8</strong> (deducts 1.000)</li>
                                        <li>10-hour schedule, e.g. compressed work week: <strong>10</strong> (deducts 1.250)</li>
                                        <li>12-hour shift: <strong>12</strong> (deducts 1.500)</li>
                                        <li>Half of an 8-hour day: <strong>4</strong> (deducts 0.500)</li>
                                    </ul>
                                    <div class="mt-1">If you file fewer hours than your schedule, the hours not covered count as undertime.</div>
                                </div>
                                @if($dayBasedLeaveNames->isNotEmpty())
                                    <div>
                                        <strong>Leaves counted per day:</strong>
                                        choose <strong>Whole day</strong> (deducts 1.000) or <strong>Half day</strong> (deducts 0.500), whatever your schedule.
                                        <div class="text-muted mt-1">{{ $dayBasedLeaveNames->implode(', ') }}</div>
                                    </div>
                                @endif
                                <div>
                                    <strong>Sick Leave:</strong> if your Sick Leave credits run out, the rest is charged to Vacation Leave.
                                    If Vacation Leave can't cover it either, file the remaining days as leave without pay.
                                </div>
                            </div>
                        </div>

                        <div id="date_range_panel" class="lf-range d-none">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label" for="range_leave_type">Leave type</label>
                                    <select class="form-select" id="range_leave_type"></select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="range_start_date">Start date</label>
                                    <input type="date" class="form-control" id="range_start_date">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="range_end_date">End date</label>
                                    <input type="date" class="form-control" id="range_end_date">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="range_hours">Hours / day</label>
                                    <select class="form-select" id="range_hours">
                                        <option value="4">4</option>
                                        <option value="8" selected>8</option>
                                        <option value="10">10</option>
                                        <option value="12">12</option>
                                        <option value="16">16</option>
                                        <option value="24">24</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-1">
                                    <button type="button" class="btn btn-primary w-100" onclick="generateDateRange()">Add</button>
                                </div>
                            </div>
                            <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Weekends and declared holidays are skipped automatically.</div>
                        </div>

                        <div class="table-responsive">
                            <table class="table lv-table lf-dates mb-0" id="dates_table">
                                <thead>
                                <tr>
                                    <th style="width: 38%;">Leave type</th>
                                    <th style="width: 28%;">Date</th>
                                    <th style="width: 20%;">Duration (hrs)</th>
                                    <th style="width: 14%;" class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody id="dates_table_body"></tbody>
                            </table>
                        </div>
                    </section>

                    {{-- Details of leave (groups built by JS from LEAVE_DETAIL_CONFIG; only the ones for the
                         chosen leave types are shown) --}}
                    <section class="lv-card" id="details_leave">
                        <h6 class="lv-card-title"><i class="bi bi-card-text"></i> Details of Leave</h6>
                        <div id="detail_groups_container" class="lf-details"></div>
                    </section>

                    <div class="row g-3">
                        {{-- Attachments: optional, required for Maternity / Paternity Leave --}}
                        <div class="col-lg-7">
                            <section class="lv-card h-100" id="attachment_section">
                                <h6 class="lv-card-title">
                                    <i class="bi bi-paperclip"></i> Attachments
                                    <span id="attachment_required_badge" class="lv-pill lv-pill-rejected d-none">Required</span>
                                </h6>
                                <div class="form-text mt-0 mb-2">Optional; required for Maternity/Paternity Leave. PDF, JPG or PNG, max 5 MB each.</div>
                                {{-- Existing attachments shown when editing --}}
                                <div id="existing_attachments" class="mb-2 d-none">
                                    <div class="lv-tile-label">Current files</div>
                                    <div id="existing_attachments_list" class="d-flex flex-wrap gap-2"></div>
                                </div>
                                <input type="file" class="form-control" id="leave_attachment" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                {{-- Preview of newly selected files --}}
                                <div id="new_files_preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </section>
                        </div>

                        {{-- Commutation: set from the leave type, read-only --}}
                        <div class="col-lg-5">
                            <section class="lv-card h-100">
                                <h6 class="lv-card-title"><i class="bi bi-arrow-left-right"></i> Commutation</h6>
                                <div class="choice-group lf-choices">
                                    <div class="choice">
                                        <input type="radio" name="commutation" id="commutation_not_requested" disabled>
                                        <label for="commutation_not_requested">Not Requested</label>
                                    </div>
                                    <div class="choice">
                                        <input type="radio" name="commutation" id="commutation_requested" disabled>
                                        <label for="commutation_requested">Requested</label>
                                    </div>
                                </div>
                                <div class="form-text mt-2">Set automatically from the leave type.</div>
                            </section>
                        </div>
                    </div>

                    {{-- Signature --}}
                    <section class="lv-card">
                        <div class="lf-card-top">
                            <h6 class="lv-card-title mb-0"><i class="bi bi-pen"></i> Applicant Signature <span class="text-danger">*</span></h6>
                            <button type="button" class="btn btn-sm btn-link p-0" onclick="clearSignaturePad('applicant')">Clear</button>
                        </div>
                        <div class="form-text mt-0 mb-2">Required to submit. Filing or editing this application signs it as an acknowledgment of its current contents.</div>
                        <div class="lv-signature">
                            <canvas id="applicant_signature_canvas" style="display:block; width:100%; height:150px;"></canvas>
                            <div id="applicant_signature_placeholder" class="lv-signature-hint">Draw your signature here, or upload an image below</div>
                        </div>
                        <div class="mt-2">
                            <label class="btn btn-sm btn-outline-secondary mb-0" for="applicant_signature_upload"><i class="bi bi-upload me-1"></i>Upload signature</label>
                            <input type="file" id="applicant_signature_upload" accept="image/*" class="d-none" onchange="handleSignatureUpload('applicant', event)">
                        </div>
                    </section>
                </div>
            </div>

            <div class="modal-footer lv-footer">
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-modal-save" id="btn_save_leave">Submit</button>
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
                                <th style="width: 24%;">Work Schedule</th>
                                <th style="width: 22%;">Leave type</th>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 11%;">Duration</th>
                                <th style="width: 28%;">{{ $isLeaveApproval ? 'Disapprove' : 'Status' }}</th>
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

    // Duration cell: day-based types (Wellness, SPL...) show "1 day" / "Half day", others hours.
    function formatLeaveDuration(d) {
        if (d.deduction_unit === 'days') {
            return Number(d.duration) >= 8 ? '1 day' : 'Half day';
        }
        return `${d.duration} hrs`;
    }

    // "Work Schedule" cell of the Dates Applied table: the employee's plotted schedule on that
    // date, flagged when the hours filed don't match it (e.g. 8 hours filed on a 10-hour day).
    // Day-based leaves cover the day whatever its length, so they are never flagged.
    function renderWorkScheduleCell(d) {
        const s = d.work_schedule;
        if (!s) {
            return `<span class="text-muted small">No schedule plotted</span>`;
        }
        if (s.is_off) {
            return `<span class="fw-semibold">${lvEscape(s.label || 'Rest day')}</span><div class="small text-muted">${lvEscape(s.code || 'OFF')}</div>`;
        }
        // Hours are only used for the mismatch warning; the cell shows the schedule's label.
        const hours = Number(s.hours || 0);
        const hoursText = Number.isInteger(hours) ? hours : hours.toFixed(1);
        const filed = Number(d.duration || 0);
        const mismatch = d.deduction_unit !== 'days' && hours > 0 && filed > 0 && Math.abs(filed - hours) > 0.01
            ? `<div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill"></i> Filed ${filed} hrs, schedule is ${hoursText} hrs</div>`
            : '';
        const label = s.label || `${s.time_in} - ${s.time_out}`;
        return `<span class="fw-semibold">${lvEscape(label)}</span>`
            + `<div class="small text-muted">${lvEscape(s.code || '')}</div>`
            + mismatch;
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
