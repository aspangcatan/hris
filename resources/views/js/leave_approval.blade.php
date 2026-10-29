<script>
    let page = 1, leave_id = 0, leave_approval_last_page = 1, activeFilter = 'pending';
    let current_approval_role = null;
    let current_dates = [];
    let current_approvals = {};
    let approverSignatureNeeded = false;
    const leaveApprovalViewButtonHtml = `<i class="bi bi-eye-fill text-success"></i>`;

    // Signature pad helpers — 'approver' key. Duplicated from js/leave_application.blade.php
    // since this page never loads that file (each page loads only its own JS companion).
    const SIGNATURE_PADS = {};
    const SIGNATURE_HAS_CONTENT = {};

    function initSignaturePad(key) {
        const canvas = document.getElementById(`${key}_signature_canvas`);
        if (!canvas) return null;
        if (SIGNATURE_PADS[key]) {
            SIGNATURE_PADS[key].off();
        }
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);

        const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)' });
        pad.addEventListener('beginStroke', function () {
            SIGNATURE_HAS_CONTENT[key] = true;
            updateSignaturePlaceholder(key);
        });
        SIGNATURE_PADS[key] = pad;
        SIGNATURE_HAS_CONTENT[key] = false;
        return pad;
    }

    function updateSignaturePlaceholder(key) {
        const placeholder = document.getElementById(`${key}_signature_placeholder`);
        if (!placeholder) return;
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (SIGNATURE_PADS[key] && !SIGNATURE_PADS[key].isEmpty());
        placeholder.style.display = hasContent ? 'none' : 'flex';
    }

    function clearSignaturePad(key) {
        const pad = SIGNATURE_PADS[key];
        if (!pad) return;
        pad.clear();
        SIGNATURE_HAS_CONTENT[key] = false;
        const upload = document.getElementById(`${key}_signature_upload`);
        if (upload) upload.value = '';
        updateSignaturePlaceholder(key);
    }

    function handleSignatureUpload(key, event) {
        const file = event.target.files[0];
        const pad = SIGNATURE_PADS[key];
        if (!file || !pad) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.getElementById(`${key}_signature_canvas`);
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const w = canvas.width / ratio;
                const h = canvas.height / ratio;
                const scale = Math.min(w / img.width, h / img.height, 1);
                const drawW = img.width * scale;
                const drawH = img.height * scale;
                const x = (w - drawW) / 2;
                const y = (h - drawH) / 2;

                pad.clear();
                canvas.getContext('2d').drawImage(img, x, y, drawW, drawH);
                SIGNATURE_HAS_CONTENT[key] = true;
                updateSignaturePlaceholder(key);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function getSignatureDataUrl(key) {
        const pad = SIGNATURE_PADS[key];
        const hasContent = SIGNATURE_HAS_CONTENT[key] || (pad && !pad.isEmpty());
        if (!hasContent) return null;
        return pad.toDataURL('image/png');
    }

    $(function () {
        $("#approveLeaveBtn, #rejectLeaveBtn").click(handleApproval);
        $("#acknowledgeLeaveBtn").click(function () {
            submitApproval(this, this.value, null);
        });
        $("#undoApprovalBtn").click(handleUndoApproval);

        $("[data-filter]").on("click", function () {
            $("[data-filter]").removeClass("active");
            $(this).addClass("active");
            activeFilter = $(this).data("filter");
            page = 1;
            getLeaves();
        });

        $("#search_employee").on("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                page = 1;
                getLeaves();
            }
        });

        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page || targetPage < 1 || targetPage > leave_approval_last_page) return;
            page = targetPage;
            getLeaves();
        });

        $('#viewLeaveModal').on('shown.bs.modal', function () {
            if (approverSignatureNeeded) {
                initSignaturePad('approver');
            }
        });

        getLeaves();
    });

    function getLeaves() {
        const search_employee = $("#search_employee").val().trim();
        $("#leave_approval_list").html("<tr><td colspan='5'>Loading...</td></tr>");

        fetch(`{{ route('getLeaveForApproval') }}?page=${page}&search_employee=${search_employee}&filter=${activeFilter}`)
            .then(r => r.json())
            .then(resp => renderLeaves(resp.data, resp.pagination))
            .catch(() => $("#leave_approval_list").html("<tr><td colspan='5'>Error</td></tr>"));
    }

    function renderLeaves(data, pagination) {
        $("#leave_approval_list").empty();
        $("#pagination_links").empty();
        $("#page_indicator").text("");

        if (!data.length) {
            $("#leave_approval_list").html("<tr><td colspan='5'>No records</td></tr>");
            $("#pagination_container").hide();
            return;
        }

        $("#pagination_container").show();

        data.forEach(it => $("#leave_approval_list").append(`
        <tr>
            <td>${it.lname}, ${it.fname} ${it.mname ?? ""}</td>
            <td>${it.description} ${it.is_lwop ? '<span class="badge bg-warning text-dark ms-1">LWOP</span>' : ''}</td>
            <td>${(it.dates_applied ? it.dates_applied.split(',') : []).map(d => moment(d).format("MM/DD/YYYY")).join(", ")}</td>
            <td>${moment(it.created_at).format("MM/DD/YYYY hh:mm:ss")}</td>
            <td><button class="btn btn-sm leave-view-btn" onclick="viewApplication(${it.id}, this)">${leaveApprovalViewButtonHtml}</button></td>
        </tr>
    `));

        const from = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const to = from + data.length - 1;
        leave_approval_last_page = pagination.last_page;
        $("#page_items_count").text(`${from} - ${to} of ${pagination.total}`);
        $("#page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
        renderLeaveApprovalPaginationLinks(pagination);
    }

    function renderLeaveApprovalPaginationLinks(pagination) {
        const $links = $("#pagination_links");
        if (!$links.length || !pagination || !pagination.last_page) return;

        const current = pagination.current_page;
        const last = pagination.last_page;
        const start = Math.max(1, current - 2);
        const end = Math.min(last, current + 2);

        $links.empty();
        $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

        if (start > 1) {
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
            if (start > 2) {
                $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
            }
        }

        for (let i = start; i <= end; i++) {
            $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
        }

        if (end < last) {
            if (end < last - 1) {
                $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
            }
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
        }

        $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
    }

    function setLeaveApprovalButtonLoading(button, isLoading) {
        if (!button) return;
        const $button = $(button);
        $button.prop("disabled", isLoading);
        $button.html(isLoading
            ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
            : leaveApprovalViewButtonHtml);
    }

    function viewApplication(id, triggerButton = null) {
        leave_id = id;
        setLeaveApprovalButtonLoading(triggerButton, true);
        fetch(`{{ route('leave-applications.show', ':id') }}`.replace(':id', id))
            .then(r => r.json())
            .then(data => renderLeaveModal(data))
            .catch(() => alert("Error loading leave application."))
            .finally(() => setLeaveApprovalButtonLoading(triggerButton, false));
    }

    function renderDateDisapproveCell(d, role) {
        if (role === 'SH' || role === 'DH') {
            const checked = role === 'SH'
                ? !!d.sh_flagged_disapprove
                : (!!d.sh_flagged_disapprove || !!d.final_disapproved);
            const remark = role === 'SH'
                ? (d.sh_disapproval_remark || '')
                : (d.dh_disapproval_remark || d.sh_disapproval_remark || '');
            const label = role === 'SH' ? 'Flag for disapproval' : 'Disapprove this date';

            return `
                <div class="form-check">
                    <input type="checkbox" class="form-check-input date-disapprove-cb" data-date-id="${d.id}" id="dd_${d.id}" ${checked ? 'checked' : ''}>
                    <label class="form-check-label small" for="dd_${d.id}">${label}</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1 date-disapprove-remark" data-date-id="${d.id}" placeholder="Reason (optional)" value="${String(remark).replace(/"/g, '&quot;')}" style="${checked ? '' : 'display:none;'}">
            `;
        }

        return d.final_disapproved ? `<span class="badge bg-danger">Disapproved</span>` : '';
    }

    $(document).on('change', '.date-disapprove-cb', function () {
        $(this).closest('td').find('.date-disapprove-remark').toggle(this.checked);
    });

    function collectDisapprovedDates() {
        const result = [];
        $('#dates_applied_body .date-disapprove-cb:checked').each(function () {
            const remark = $(this).closest('td').find('.date-disapprove-remark').val().trim();
            result.push({ date_applied_id: $(this).data('date-id'), remark: remark || null });
        });
        return result;
    }

    function renderLeaveModal(data) {
        current_dates = data.dates || [];
        current_approvals = data.approvals || {};
        renderLeaveCreditBalances(data.credit_balances);
        $('#leave_name').text(data.name);
        $('#leave_type').text(data.type);
        $('#leave_commutation').text(data.commutation);
        $('#lwop_badge').toggleClass('d-none', !data.is_lwop);
        $('#leave_details_container').html(renderLeaveDetailsHtml(data));

        const allAttachments = (data.attachments && data.attachments.length > 0)
            ? data.attachments
            : (data.attachment_url ? [{ id: null, url: data.attachment_url, original_name: 'View Attachment' }] : []);
        if (allAttachments.length > 0) {
            const links = allAttachments.map(a =>
                `<a href="${a.url}" target="_blank" class="me-2"><i class="bi bi-paperclip"></i> ${a.original_name}</a>`
            ).join('');
            $('#view_attachment_row').html(`<p><strong>Attachments:</strong> ${links}</p>`).show();
        } else {
            $('#view_attachment_row').hide();
        }

        $('#dates_applied_body').html(data.dates.map(d =>
            `<tr><td>${d.leave_type || ''}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td><td>${renderDateDisapproveCell(d, data.current_approval_role)}</td></tr>`
        ).join(""));

        $('#step0').toggleClass('d-none', !data.approvals.hr_compliance);
        if (data.approvals.hr_compliance) {
            updateStepStatus('#step0', data.approvals.hr_compliance);
        }
        updateStepStatus('#step1', data.approvals.sh);
        updateStepStatus('#step2', data.approvals.dh);
        updateStepStatus('#step3', data.approvals.hr);

        let remarks = ["hr_compliance", "sh", "dh", "hr"].map(role => {
            let a = data.approvals[role];
            if (!a?.remarks) return "";
            const label = role === "hr_compliance" ? "HR COMPLIANCE" : role.toUpperCase();
            return `
            <div class="mb-2">
                <strong>${label}:</strong>
                <p class="mb-0">${a.remarks}</p>
                <small class="text-muted">Status: ${a.status}</small>
            </div>
        `;
        }).join("") || `<p class="text-muted">No remarks yet.</p>`;

        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        current_approval_role = data.current_approval_role;

        // HR's step is an acknowledgement of the Division Head's decision, whatever it was,
        // so HR gets "I Acknowledge" instead of Approve/Reject. (HR Compliance keeps approve/reject.)
        const acknowledgeOnly = canAct && current_approval_role === 'HR';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct && !acknowledgeOnly);
        $('#acknowledgeLeaveBtn').toggleClass('d-none', !acknowledgeOnly).toggle(acknowledgeOnly);
        $('#undoApprovalBtn').toggleClass('d-none', !data.can_undo);

        $('#hr_action_section').toggleClass('d-none', !acknowledgeOnly);
        if (acknowledgeOnly) prefillHrActionFields(data);

        approverSignatureNeeded = canAct && (current_approval_role === 'SH' || current_approval_role === 'DH');
        $('#approver_signature_section').toggleClass('d-none', !approverSignatureNeeded);

        $('#viewLeaveModal').modal('show');
    }

    // 7.A defaults to today; 7.C defaults to the days the Division Head left approved
    // (all with pay, or all without pay for an LWOP application). HR may edit before acknowledging.
    function prefillHrActionFields(data) {
        const saved = data.hr_action || {};
        const approvedDays = (data.dates || [])
            .filter(d => !d.is_cancelled && !d.final_disapproved)
            .reduce((sum, d) => sum + (parseFloat(d.duration) || 0), 0) / 8;
        const pick = (value, fallback) => (value !== null && value !== undefined) ? parseFloat(value) : fallback;

        $('#cert_as_of').val(saved.cert_as_of || moment().format('YYYY-MM-DD'));
        $('#approved_days_with_pay').val(pick(saved.approved_days_with_pay, data.is_lwop ? 0 : approvedDays));
        $('#approved_days_without_pay').val(pick(saved.approved_days_without_pay, data.is_lwop ? approvedDays : 0));
        $('#approved_others').val(saved.approved_others || '');
    }

    function collectHrActionFields() {
        if (current_approval_role !== 'HR') return {};
        const num = sel => $(sel).val() === '' ? null : $(sel).val();
        return {
            cert_as_of: $('#cert_as_of').val() || null,
            approved_days_with_pay: num('#approved_days_with_pay'),
            approved_days_without_pay: num('#approved_days_without_pay'),
            approved_others: $('#approved_others').val().trim() || null,
        };
    }

    function renderLeaveDetailsHtml(data) {
        const items = Array.isArray(data.details_per_type) ? data.details_per_type : [];
        if (items.length === 0) return '<span class="text-muted">No details provided.</span>';
        return items.map(d => {
            const typeName = (data.leave_types_map && data.leave_types_map[d.leave_id]) || '';
            const parts = [d.detail, d.extra].filter(Boolean);
            const text = parts.join(' — ');
            return `<div class="mb-1"><strong>${typeName}:</strong>${text ? ' ' + text : ' <span class="text-muted">—</span>'}</div>`;
        }).join('') || '<span class="text-muted">No details provided.</span>';
    }

    function updateStepStatus(sel, a) {
        $(sel).removeClass("approved rejected pending acknowledged")
            .addClass(a?.status || "pending");
        $(sel + " .circle").attr("class", "circle " + (a?.status || "pending"));
    }

    function escapeHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    // Dates the Section Head flagged for disapproval that the Division Head
    // has left unticked, i.e. is about to approve anyway.
    function getShFlaggedDatesBeingApproved() {
        return current_dates.filter(d => d.sh_flagged_disapprove && !$(`#dd_${d.id}`).is(':checked'));
    }

    // Approval steps in chain order: [role, key in data.approvals, label]
    const APPROVAL_STEPS = [
        ['HR_COMPLIANCE', 'hr_compliance', 'HR Compliance'],
        ['SH', 'sh', 'Section Head'],
        ['DH', 'dh', 'Division Head'],
        ['HR', 'hr', 'HR'],
    ];

    // Earlier approvers (before the current role) who rejected this leave.
    function getPriorRejections() {
        const myIndex = APPROVAL_STEPS.findIndex(s => s[0] === current_approval_role);
        if (myIndex < 0) return [];
        return APPROVAL_STEPS.slice(0, myIndex)
            .map(([, key, label]) => ({label, approval: current_approvals[key]}))
            .filter(s => String(s.approval?.status || '').toLowerCase() === 'rejected');
    }

    let confirmFlaggedApproval = null;

    // Shared confirmation modal: {title, intro, itemsHtml, question, confirmLabel}
    function showApprovalWarning({title, intro, itemsHtml, question, confirmLabel}, onConfirm) {
        $('#leaveFlagWarningLabel').text(title);
        $('#leave_flag_warning_intro').text(intro);
        $('#leave_flag_warning_list').html(itemsHtml);
        $('#leave_flag_warning_question').text(question);
        $('#btn_confirm_flagged_approve').text(confirmLabel);
        confirmFlaggedApproval = onConfirm;
        $('#leave_flag_warning_modal').modal('show');
    }

    function showFlaggedApprovalWarning(dates, onConfirm) {
        showApprovalWarning({
            title: 'Section Head Flagged This Leave',
            intro: 'The Section Head flagged the following date(s) for disapproval. You are about to approve them anyway.',
            itemsHtml: dates.map(d => `
                <li class="list-group-item">
                    <div class="fw-semibold">${escapeHtml(d.leave_type || '')} — ${moment(d.date).format("MM/DD/YYYY")}</div>
                    <div class="small text-muted">Reason: ${d.sh_disapproval_remark ? escapeHtml(d.sh_disapproval_remark) : 'No reason given'}</div>
                </li>`).join(''),
            question: 'Do you want to continue with the approval?',
            confirmLabel: 'APPROVE ANYWAY',
        }, onConfirm);
    }

    function showRejectedLeaveWarning(rejections, onConfirm) {
        showApprovalWarning({
            title: 'This Leave Was Rejected',
            intro: 'You are trying to approve a leave that was rejected. Approving will overturn the rejection: ',
            itemsHtml: rejections.map(r => `
                <li class="list-group-item">
                    <div class="fw-semibold">Rejected by ${escapeHtml(r.label)}</div>
                    <div class="small text-muted">Remarks: ${r.approval.remarks ? escapeHtml(r.approval.remarks) : 'No remarks given'}</div>
                </li>`).join(''),
            question: 'Would you like to proceed?',
            confirmLabel: 'PROCEED',
        }, onConfirm);
    }

    $(document).on('click', '#btn_confirm_flagged_approve', function () {
        const proceed = confirmFlaggedApproval;
        confirmFlaggedApproval = null;
        $('#leave_flag_warning_modal').modal('hide');
        if (proceed) proceed();
    });

    function handleApproval() {
        const status = this.value;
        const requiresSignature = status === 'Approved' && (current_approval_role === 'SH' || current_approval_role === 'DH');
        const signature = requiresSignature ? getSignatureDataUrl('approver') : null;

        if (requiresSignature && !signature) {
            toastr.error("Please draw or upload your signature before approving", "Required");
            return;
        }

        const button = this;

        // Warn before approving a leave an earlier approver rejected (approving overturns the rejection)
        if (status === 'Approved') {
            const rejections = getPriorRejections();
            if (rejections.length > 0) {
                showRejectedLeaveWarning(rejections, () => submitApproval(button, status, signature, true));
                return;
            }
        }

        // Warn the Division Head before approving dates the Section Head flagged for disapproval
        if (status === 'Approved' && current_approval_role === 'DH') {
            const overridden = getShFlaggedDatesBeingApproved();
            if (overridden.length > 0) {
                showFlaggedApprovalWarning(overridden, () => submitApproval(button, status, signature));
                return;
            }
        }

        submitApproval(button, status, signature);
    }

    function submitApproval(button, status, signature, overturnRejection = false) {
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        fetch('{{ route('updateApprovalStatus') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({
                id: leave_id,
                remarks: $("#leave_remarks").val().trim(),
                status: status,
                disapproved_dates: collectDisapprovedDates(),
                signature: signature,
                overturn_rejection: overturnRejection,
                ...collectHrActionFields()
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }

    function handleUndoApproval() {
        if (!confirm("Are you sure you want to undo your approval? The application will be returned to pending status.")) return;
        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Undoing...');
        fetch('{{ route('undoApproval') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({id: leave_id})
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }

    /** ================================================
     *  MONETIZATION APPROVAL
     * ================================================ */
    let monetization_approval_page        = 1;
    let monetization_approval_last_page   = 1;
    let active_monetization_filter        = 'pending';
    let current_monetization_id           = 0;
    const monetize_approve_modal          = new bootstrap.Modal(document.getElementById('monetize_approve_modal'));

    // Filter buttons
    $("[data-monetization-filter]").on("click", function () {
        $("[data-monetization-filter]").removeClass("active");
        $(this).addClass("active");
        active_monetization_filter = $(this).data("monetization-filter");
        monetization_approval_page = 1;
        getMonetizationApprovals();
    });

    // Search
    $("#search_employee_monetization").on("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            monetization_approval_page = 1;
            getMonetizationApprovals();
        }
    });

    // Approve / Reject buttons
    $("#btn_approve_monetization").click(function () {
        handleMonetizationApproval('approved');
    });
    $("#btn_reject_monetization").click(function () {
        handleMonetizationApproval('rejected');
    });

    // Load when tab activates
    $('#tab-monetization-approval').on('shown.bs.tab', function () {
        monetization_approval_page = 1;
        getMonetizationApprovals();
    });

    function getMonetizationApprovals() {
        const search = $("#search_employee_monetization").val().trim();
        $("#monetization_approval_list").html("<tr><td colspan='5'>Loading...</td></tr>");

        fetch(`{{ route('getMonetizationApprovals') }}?page=${monetization_approval_page}&search_employee=${search}&filter=${active_monetization_filter}`)
            .then(r => r.json())
            .then(resp => renderMonetizationApprovals(resp.data, resp.pagination))
            .catch(() => $("#monetization_approval_list").html("<tr><td colspan='5'>Error loading data.</td></tr>"));
    }

    function renderMonetizationApprovals(data, pagination) {
        $("#monetization_approval_list").empty();

        if (!data || !data.length) {
            $("#monetization_approval_list").html("<tr><td colspan='5'>No requests found.</td></tr>");
            $("#monetization_approval_pagination_container").hide();
            return;
        }

        $("#monetization_approval_pagination_container").show();

        data.forEach(it => {
            $("#monetization_approval_list").append(`
            <tr>
                <td>${it.employee}</td>
                <td>${it.leave_type}</td>
                <td>${it.days_requested}</td>
                <td>${it.filed_date}</td>
                <td>
                    <button class="btn btn-sm" onclick="viewMonetizationRequest(${it.id}, ${JSON.stringify(it).replace(/"/g, '&quot;')})">
                        <i class="bi bi-eye-fill text-success"></i>
                    </button>
                </td>
            </tr>`);
        });

        monetization_approval_last_page = pagination.last_page;
        const from = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const to   = from + data.length - 1;
        $("#monetization_approval_page_items_count").text(`${from} - ${to} of ${pagination.total}`);
        $("#monetization_approval_page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
        renderMonetizationApprovalPaginationLinks(pagination);
    }

    function renderMonetizationApprovalPaginationLinks(pagination) {
        const $links  = $("#monetization_approval_pagination_links");
        if (!$links.length || !pagination) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();
        $links.append(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${current - 1})">Prev</a></li>`);
        for (let i = start; i <= end; i++) {
            $links.append(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${i})">${i}</a></li>`);
        }
        $links.append(`<li class="page-item ${current >= last ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${current + 1})">Next</a></li>`);
    }

    function changeMonetizationApprovalPage(p) {
        if (p < 1 || p > monetization_approval_last_page) return;
        monetization_approval_page = p;
        getMonetizationApprovals();
    }

    function viewMonetizationRequest(id, data) {
        current_monetization_id = id;
        $("#ma_employee").text(data.employee);
        $("#ma_leave_type").text(data.leave_type);
        $("#ma_days").text(data.days_requested + ' days');
        $("#ma_filed_date").text(data.filed_date);
        $("#ma_remarks").val('');
        monetize_approve_modal.show();
    }

    function handleMonetizationApproval(status) {
        const remarks = $("#ma_remarks").val().trim();
        const btn     = status === 'approved' ? $("#btn_approve_monetization") : $("#btn_reject_monetization");
        btn.prop('disabled', true);

        fetch("{{ route('updateMonetizationApprovalStatus') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            body: JSON.stringify({ id: current_monetization_id, status, remarks }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                toastr.success(data.message || 'Status updated.', 'Success');
                monetize_approve_modal.hide();
                getMonetizationApprovals();
            })
            .catch(err => toastr.error(err.message || 'Error updating status.', 'Error'))
            .finally(() => btn.prop('disabled', false));
    }
</script>
