<script>
    // Route URLs (rendered by Blade, double-quoted to avoid JS lint false-positives)
    const ROUTE_INSERT_LEAVE        = "{{ route('insertLeaveApplication') }}";
    const ROUTE_UPDATE_LEAVE        = "{{ route('leave-applications.update', ':id') }}";
    const ROUTE_DELETE_LEAVE        = "{{ route('leave-applications.destroy', ':id') }}";
    const ROUTE_ADD_ATTACHMENTS     = "{{ route('leave-applications.attachments.add', ':id') }}";
    const ROUTE_DELETE_ATTACHMENT   = "{{ route('leave-applications.attachments.delete', ':id') }}";
    const ROUTE_GET_LEAVES     = "{{ route('getLeaveApplications') }}";
    const ROUTE_SHOW_LEAVE     = "{{ route('leave-applications.show', ':id') }}";
    const ROUTE_PRINT_LEAVE    = "{{ route('printLeaveForm') }}";
    const ROUTE_CREDIT_BAL     = "{{ route('getCreditBalances') }}";
    const ROUTE_CALENDAR_EVENTS = "{{ route('calendar.events') }}";

    // Credits deducted per filed hour — same minute_conversions source the server validates against
    const CREDIT_PER_HOUR = {{ (float) app(\App\Services\MinuteConversionService::class)->computeDeduction(60) }};

    const USER_SEX ="{{ strtoupper(\App\Models\Employee::where('user_id', Auth::id())->value('sex') ?? '') }}";
    const GENDER_RESTRICTED_TYPES = {
        // Keep in sync with LeaveApplicationService::FEMALE_ONLY_KEYWORDS (the server enforces it)
        female_only: ['maternity leave', 'gyne leave', 'vawc leave', 'special leave benefits for women'],
        male_only: ['paternity leave'],
    };

    const ATTACHMENT_REQUIRED_KEYWORDS = ['maternity leave', 'paternity leave'];

    // Holidays by date (YYYY-MM-DD => description). Filing on one is allowed after confirming.
    const HOLIDAYS = @json(\Illuminate\Support\Facades\DB::table('hris.calendar_holidays')->pluck('description', 'date'));

    // Escapes text typed by users (details, remarks, file names) before it goes into HTML.
    function escapeHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    let page = 1;
    let leave_application_last_page = 1;
    let editing_leave_id = null;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));

    // Signature pads, keyed by a short name ('applicant' here; 'approver' lives in js/leave_approval.blade.php).
    // Shared helpers so both files can reuse the same canvas/upload/clear mechanics.
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

    // Available leave types with balance > 0 — populated by loadCreditBalance()
    const LEAVE_TYPES = [];

    // Holiday dates (YYYY-MM-DD strings), fetched lazily on first "Add Date Range" use
    let HOLIDAY_DATES = null;

    /**
     * LEAVE_DETAIL_CONFIG — single source of truth for detail section.
     * To support a new leave type with details, add one entry here. No HTML changes needed.
     *
     * keywords : substrings matched against the leave type description (lowercase)
     * groupId  : unique DOM id for this detail group
     * label    : section heading shown above the options
     * options  : array of radio choices
     *   - label    : radio label text
     *   - hasExtra : true = show a text input beside the radio for extra info
     */
    const LEAVE_DETAIL_CONFIG = [
        {
            keywords: ['vacation leave', 'special privilege'],
            groupId:  'detail_vacation',
            label:    'In case of Vacation/Special Privilege Leave:',
            options: [
                { label: 'Within the Philippines', hasExtra: true },
                { label: 'Abroad (Specify)',        hasExtra: true },
            ],
        },
        {
            keywords: ['sick leave'],
            groupId:  'detail_sick',
            label:    'In case of Sick Leave:',
            options: [
                { label: 'In Hospital (Specify Illness)', hasExtra: true },
                { label: 'Out Patient (Specify Illness)', hasExtra: true },
            ],
            classification: {
                label:     'Classification:',
                groupName: 'detail_sick_classification',
                options:   ['Work-Related', 'Non-Work Related'],
            },
        },
        {
            keywords: ['special leave benefits for women'],
            groupId:  'detail_special_women',
            label:    'In case of Special Leave Benefits for Women:',
            options: [
                { label: '(Specify Illness)', hasExtra: true },
            ],
        },
        {
            keywords: ['study leave'],
            groupId:  'detail_study',
            label:    'In case of Study Leave:',
            options: [
                { label: "Completion of Master's Degree", hasExtra: false },
                { label: 'BAR/Board Examination Review',  hasExtra: false },
            ],
        },
    ];

    /** Build detail group HTML from LEAVE_DETAIL_CONFIG and inject into #detail_groups_container. */
    function renderDetailGroups() {
        const $container = $('#detail_groups_container');
        $container.empty();

        LEAVE_DETAIL_CONFIG.forEach(cfg => {
            const optionsHtml = cfg.options.map(opt => `
                <div class="form-check d-flex align-items-center mt-1">
                    <input class="form-check-input me-2" type="radio" name="${cfg.groupId}" data-role="primary" disabled data-has-extra="${opt.hasExtra}">
                    <label class="form-check-label me-2">${opt.label}</label>
                    ${opt.hasExtra ? '<input class="form-control w-50" type="text" disabled placeholder="Required">' : ''}
                </div>
            `).join('');

            const classificationHtml = cfg.classification ? `
                <div class="small fw-semibold mt-2 mb-1">${cfg.classification.label}</div>
                ${cfg.classification.options.map(label => `
                    <div class="form-check d-flex align-items-center mt-1">
                        <input class="form-check-input me-2" type="radio" name="${cfg.classification.groupName}" data-role="classification" disabled>
                        <label class="form-check-label me-2">${label}</label>
                    </div>
                `).join('')}
            ` : '';

            $container.append(`
                <div id="${cfg.groupId}" class="detail-group detail-disabled mt-3"
                     style="opacity:0.4; pointer-events:none;">
                    <div>${cfg.label}</div>
                    ${optionsHtml}
                    ${classificationHtml}
                </div>
            `);
        });
    }

    function resetDetailsSection() {
        $('#detail_groups_container .detail-group')
            .addClass('detail-disabled')
            .css({ opacity: '0.4', 'pointer-events': 'none' });
        $('#detail_groups_container input').prop('disabled', true).prop('checked', false).val('');
    }

    /** Enable detail groups matching leave types selected in date rows. */
    function refreshDetailsVisibility() {
        resetDetailsSection();
        const seen = new Set();
        $('.date-leave-type').each(function () {
            const lid = parseInt($(this).val());
            if (!lid || seen.has(lid)) return;
            seen.add(lid);
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            if (!lt) return;
            const lower = lt.description.toLowerCase().trim();
            const cfg   = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
            if (cfg) {
                $(`#${cfg.groupId}`)
                    .removeClass('detail-disabled')
                    .css({ opacity: '1', 'pointer-events': '' })
                    .find('input').prop('disabled', false);
            }
        });
    }

    $(document).ready(function () {
        renderDetailGroups(); // build detail section HTML from config

        // Refresh detail sections, date minimums, commutation, and attachment visibility on date-row leave type change
        $(document).on('change', ".date-leave-type", function () {
            refreshDetailsVisibility();
            updateDateInputMins();
            refreshCommutationVisibility();
            refreshAttachmentVisibility();
        });

        $("#btn_file_leave").click(async function () {
            clearForm();
            await init_leave();           // refresh balances & populate LEAVE_TYPES
            addRow();
            resetDetailsSection();
            refreshCommutationVisibility(); // set initial commutation state (no types checked yet → hidden)
            file_leave_modal.toggle();
        });

        $("#btn_file_lwop").click(async function () {
            clearForm();
            setLwopMode(true);
            await init_leave();           // refresh balances & populate LEAVE_TYPES
            addRow();
            resetDetailsSection();
            refreshCommutationVisibility();
            file_leave_modal.toggle();
        });

        $("#btn_reload").click(init_leave);
        $("#btn_search").click(() => { page = 1; getLeaves(); });

        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page) return;
            if (targetPage < 1 || targetPage > leave_application_last_page) return;
            page = targetPage;
            getLeaves();
        });

        $("#btn_save_leave").click(handleSaveLeave);

        // Init the applicant signature pad only once the modal is actually visible
        // (canvas offsetWidth/offsetHeight are 0 while display:none, which would
        // size the canvas bitmap to 0x0). Fires for both "File" and "Edit" opens
        // since both share this one modal.
        $('#file_leave_modal').on('shown.bs.modal', function () {
            initApplicantSignaturePad();
        });

        init_leave();
        getLeaves();
    });

    /** ------------------------
     *  Helpers
     * ------------------------ */
    async function fetchData(url, options = {}) {
        try {
            const response = await fetch(url, options);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || "Server error");
            return data;
        } catch (err) {
            console.error(err);
            toastr.error(err.message || "Something went wrong", "Error");
            throw err;
        }
    }

    function toggleButtonLoading(btn, isLoading, text = "SUBMIT") {
        if (isLoading) {
            btn.html(`
                <button class="btn btn-primary" type="button" disabled>
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Loading...
                </button>
            `).prop("disabled", true);
        } else {
            btn.html(text).prop("disabled", false);
        }
    }

    /** ------------------------
     *  Save Leave Application
     * ------------------------ */
    async function handleSaveLeave() {
        let is_valid = true;

        // Collect dates with per-row leave_type_id
        const date_applied_array = [];
        let allHaveLeaveType = true;
        $("#dates_table_body tr").each(function () {
            const leaveTypeId = parseInt($(this).find('.date-leave-type').val()) || null;
            const date        = $(this).find("input[type='date']").val();
            const hours       = $(this).find("select[name='leave_hours[]']").val();
            if (!leaveTypeId) allHaveLeaveType = false;
            if (date && hours) {
                date_applied_array.push({ date_applied: date, hours: parseInt(hours), leave_type_id: leaveTypeId });
            }
        });

        // Last line of defence against duplicate dates (the date picker already refuses them).
        const seenDates = new Set();
        const duplicateDates = new Set();
        date_applied_array.forEach(d => {
            if (seenDates.has(d.date_applied)) duplicateDates.add(d.date_applied);
            seenDates.add(d.date_applied);
        });
        if (duplicateDates.size > 0) {
            $("#dates_table_body input[type='date']").each(function () {
                if (duplicateDates.has($(this).val())) flashInvalid($(this));
            });
            const list = [...duplicateDates].sort().map(d => moment(d).format("MM/DD/YYYY")).join(", ");
            toastr.error(`Each date can only be filed once. Remove the duplicate: ${list}`, "Duplicate date");
            return;
        }

        // Unique leave type IDs from the date rows
        const leave_ids = [...new Set(date_applied_array.map(d => d.leave_type_id).filter(Boolean))];

        // Build per-leave-type detail map from active detail groups
        const details_map = {};
        leave_ids.forEach(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            if (!lt) return;
            const lower = lt.description.toLowerCase();
            const cfg = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
            if (!cfg) return;
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return;
            const checked = $group.find('input[type="radio"][data-role="primary"]:checked');
            if (!checked.length) return;
            const $extraInput = checked.siblings('input[type="text"]');
            let extra = ($extraInput.length ? $extraInput.val().trim() : '') || null;
            if (cfg.classification) {
                const classificationLabel = $group.find('input[data-role="classification"]:checked').siblings('label').text().trim();
                if (classificationLabel) {
                    extra = extra ? `${extra} (${classificationLabel})` : `(${classificationLabel})`;
                }
            }
            details_map[lid] = {
                detail: checked.siblings('label').text().trim(),
                extra:  extra,
            };
        });
        const commutation = $("input[name='commutation']:checked").next().text();

        // Validation
        if (date_applied_array.length < 1) {
            is_valid = false;
            toastr.error("Please specify at least one date", "Required");
        }
        if (!allHaveLeaveType) {
            is_valid = false;
            toastr.error("Please select a leave type for every date row", "Required");
        }
        // Every active detail group must have its primary reason selected
        const detailMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[type="radio"][data-role="primary"]:checked').length === 0;
        });
        if (detailMissing) {
            is_valid = false;
            toastr.error("Please provide details for all selected leave types", "Required");
        }
        // When a radio with hasExtra is selected, its text input is required
        const extraMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return false;
            const checked = $group.find('input[type="radio"][data-role="primary"]:checked');
            if (!checked.length) return false;
            if (checked.attr('data-has-extra') !== 'true') return false;
            const $extraInput = checked.siblings('input[type="text"]');
            return !$extraInput.length || !$extraInput.val().trim();
        });
        if (extraMissing) {
            is_valid = false;
            toastr.error("Please fill in the required detail text for your selected option", "Required");
        }
        // Groups with a classification sub-group require it to be selected too
        const classificationMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            if (!cfg.classification) return false;
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[data-role="classification"]:checked').length === 0;
        });
        if (classificationMissing) {
            is_valid = false;
            toastr.error("Please specify the classification (Work-Related/Non-Work Related)", "Required");
        }
        if (!commutation) {
            is_valid = false;
            toastr.error("Please specify commutation", "Required");
        }

        const applicantSignature = getSignatureDataUrl('applicant');
        if (!applicantSignature) {
            is_valid = false;
            toastr.error("Please draw or upload your signature", "Required");
        }

        // Validate attachment when any date has a type requiring it
        const attachmentRequired = date_applied_array.some(d => {
            const lt = LEAVE_TYPES.find(t => t.id === d.leave_type_id);
            return lt && ATTACHMENT_REQUIRED_KEYWORDS.some(kw => lt.description.toLowerCase().includes(kw));
        });
        const attachFiles = document.getElementById('leave_attachment')
            ? Array.from(document.getElementById('leave_attachment').files)
            : [];
        const hasExistingAttachments = $('#existing_attachments_list .attach-chip').length > 0;
        if (attachmentRequired && !editing_leave_id && attachFiles.length === 0) {
            is_valid = false;
            toastr.error("At least one attachment is required for Maternity/Paternity Leave", "Required");
        }
        if (attachmentRequired && editing_leave_id && attachFiles.length === 0 && !hasExistingAttachments) {
            is_valid = false;
            toastr.error("At least one attachment is required for Maternity/Paternity Leave", "Required");
        }

        // Weekends are not valid leave dates (also catches old applications loaded for editing)
        if (date_applied_array.some(d => isWeekendIso(d.date_applied))) {
            is_valid = false;
            toastr.error("Weekend dates cannot be filed. Please remove or change them.", "Invalid date");
        }

        // Guard: ensure hours filed per leave type do not exceed available balance.
        // Skipped entirely in LWOP mode — filing without credits is the whole point.
        const isLwop = $('#is_lwop_flag').val() === '1';
        if (!isLwop) {
            const hoursPerType = {};
            date_applied_array.forEach(d => {
                if (!d.leave_type_id) return;
                hoursPerType[d.leave_type_id] = (hoursPerType[d.leave_type_id] || 0) + parseInt(d.hours, 10);
            });
            for (const [typeId, totalHours] of Object.entries(hoursPerType)) {
                const lt = LEAVE_TYPES.find(t => t.id === parseInt(typeId, 10));
                if (!lt) continue;
                const availableHours = parseFloat(lt.balance) / CREDIT_PER_HOUR;
                if (Math.round(totalHours * CREDIT_PER_HOUR * 1000) > Math.round(parseFloat(lt.balance) * 1000)) {
                    is_valid = false;
                    toastr.error(
                        `Insufficient ${lt.description} credits. You have ${availableHours.toFixed(2)} hrs available but are filing ${totalHours} hrs.`,
                        "Insufficient Balance"
                    );
                }
            }
        }

        if (!is_valid) return;

        // LWOP is the employee's choice, but tell them first if they still have credits that
        // could cover these dates as an ordinary (with pay) leave.
        if (isLwop) {
            const hoursByType = {};
            date_applied_array.forEach(d => {
                if (d.leave_type_id) hoursByType[d.leave_type_id] = (hoursByType[d.leave_type_id] || 0) + parseInt(d.hours, 10);
            });
            const withBalance = Object.entries(hoursByType).map(([typeId, hours]) => {
                const lt = LEAVE_TYPES.find(t => t.id === parseInt(typeId, 10));
                const balance = lt ? parseFloat(lt.balance) || 0 : 0;
                return { lt, balance, needed: hours * CREDIT_PER_HOUR };
            }).filter(x => x.lt && x.balance > 0);

            if (withBalance.length > 0) {
                const list = withBalance.map(x =>
                    `${x.lt.description}: ${x.balance.toFixed(3)} day(s) available` +
                    (x.balance + 1e-9 >= x.needed ? ` - enough for this filing (${x.needed.toFixed(3)})` : ` - this filing needs ${x.needed.toFixed(3)}`)
                ).join("\n");
                if (!confirm(`You still have available leave credits:\n\n${list}\n\nYou can file this as an ordinary leave (with pay) instead.\nDo you still wish to file it as Leave Without Pay?`)) return;
            }
        }

        // Holidays may be filed (credits are still charged), but only after the employee confirms.
        const holidayDates = [...new Set(date_applied_array.map(d => d.date_applied))].filter(d => HOLIDAYS[d]).sort();
        if (holidayDates.length > 0) {
            const list = holidayDates.map(d => `${moment(d).format("MM/DD/YYYY")} - ${HOLIDAYS[d]}`).join("\n");
            const which = holidayDates.length === 1 ? "That day is a Holiday" : "These days are Holidays";
            if (!confirm(`${which}:\n\n${list}\n\nDo you wish to proceed?`)) return;
        }

        const params = {
            details_map,
            commutation,
            dates: date_applied_array,  // each item has { date_applied, hours, leave_type_id }
            applicant_signature: applicantSignature,
            is_lwop: isLwop,
        };

        const btn = $("#btn_save_leave");
        toggleButtonLoading(btn, true);

        try {
            const isEdit = editing_leave_id !== null;
            const url    = isEdit
                ? ROUTE_UPDATE_LEAVE.replace(':id', editing_leave_id)
                : ROUTE_INSERT_LEAVE;

            let fetchOptions;
            if (isEdit) {
                fetchOptions = {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                };
            } else {
                const fd = new FormData();
                fd.append('data', JSON.stringify(params));
                attachFiles.forEach(f => fd.append('attachments[]', f));
                fetchOptions = {
                    method: "POST",
                    headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') },
                    body: fd
                };
            }

            const body = await fetchData(url, fetchOptions);

            // For edit mode: upload any newly selected files separately
            if (isEdit && attachFiles.length > 0) {
                const fd2 = new FormData();
                attachFiles.forEach(f => fd2.append('attachments[]', f));
                await fetch(ROUTE_ADD_ATTACHMENTS.replace(':id', editing_leave_id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    body: fd2
                });
            }

            toastr.success(body.message || (isEdit ? "Leave updated successfully" : "Leave submitted successfully"), "Information");
            clearForm();
            getLeaves();
            getLwopHistory();
            init_leave();
        } catch {
            // error already handled in fetchData
        } finally {
            toggleButtonLoading(btn, false);
        }
    }

    /** ------------------------
     *  Dynamic Rows
     * ------------------------ */
    // Auto-sets the commutation radio based on leave types selected in date rows.
    function refreshCommutationVisibility() {
        const typeIds = getSelectedLeaveTypeIds();
        const requested = typeIds.some(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            return lt && lt.commutation === 1;
        });
        $('#commutation_not_requested').prop('checked', !requested);
        $('#commutation_requested').prop('checked', requested);
    }

    function refreshAttachmentVisibility() {
        const typeIds = getSelectedLeaveTypeIds();
        const needsAttachment = typeIds.some(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            return lt && ATTACHMENT_REQUIRED_KEYWORDS.some(kw => lt.description.toLowerCase().includes(kw));
        });
        // Section is always visible (optional for all types); only flag it as required
        $("#attachment_required_badge").toggleClass('d-none', !needsAttachment);
    }

    // Live preview of newly selected files
    $(document).on('change', '#leave_attachment', function () {
        const $preview = $("#new_files_preview").empty();
        Array.from(this.files).forEach(f => {
            $preview.append(`
            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-2 px-2">
                <i class="bi bi-paperclip"></i>
                <span style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(f.name)}</span>
            </span>`);
        });
    });

    async function deleteExistingAttachment(attachmentId, chipEl) {
        if (!confirm('Remove this attachment?')) return;
        try {
            const res = await fetch(ROUTE_DELETE_ATTACHMENT.replace(':id', attachmentId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                }
            });
            const body = await res.json();
            if (!res.ok) { toastr.error(body.error || 'Failed to delete.', 'Error'); return; }
            $(chipEl).closest('.attach-chip').remove();
            if ($('#existing_attachments_list .attach-chip').length === 0) {
                $('#existing_attachments').addClass('d-none');
            }
        } catch { toastr.error('Network error.', 'Error'); }
    }

    function renderExistingAttachments(attachments) {
        const $list = $('#existing_attachments_list').empty();
        if (!attachments || attachments.length === 0) {
            $('#existing_attachments').addClass('d-none');
            return;
        }
        attachments.forEach(a => {
            const deleteBtn = a.id
                ? `<button type="button" class="btn-close ms-1" style="font-size:.65rem"
                       onclick="deleteExistingAttachment(${a.id}, this)" title="Remove"></button>`
                : '';
            $list.append(`
            <span class="attach-chip badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-2 px-2">
                <a href="${a.url}" target="_blank" class="text-dark text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-paperclip"></i>
                    <span style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(a.original_name)}</span>
                </a>
                ${deleteBtn}
            </span>`);
        });
        $('#existing_attachments').removeClass('d-none');
    }

    // Unique leave type IDs currently selected in any date row.
    function getSelectedLeaveTypeIds() {
        const ids = [];
        const seen = new Set();
        $('.date-leave-type').each(function () {
            const lid = parseInt($(this).val());
            if (lid && !seen.has(lid)) { seen.add(lid); ids.push(lid); }
        });
        return ids;
    }

    // Advance notice (days_prior) is counted in working days — weekends are skipped.
    // Returns the local ISO date (YYYY-MM-DD) that many working days from today.
    function addWorkingDaysFromToday(workingDays) {
        const d = new Date();
        while (workingDays > 0) {
            d.setDate(d.getDate() + 1);
            if (d.getDay() !== 0 && d.getDay() !== 6) workingDays--;
        }
        return toLocalIsoDate(d);
    }

    function isWeekendIso(iso) {
        const day = new Date(iso + 'T00:00:00').getDay();
        return day === 0 || day === 6;
    }

    // Weekends are not valid leave dates. Native date inputs cannot grey out
    // weekdays in the picker, so a weekend pick is rejected and cleared here.
    //
    // When the date is typed with the keyboard, the browser reports a "complete" date after every
    // digit of the year (0002, 0020, 0202, 2026), so a year below 1900 means the user is still
    // typing: the checks wait until the year is complete, or until the user leaves the field.
    $(document).on('change', "#dates_table_body input[type='date']", function () {
        if (!isStillTypingYear($(this).val())) validateDateRow(this);
    });

    $(document).on('blur', "#dates_table_body input[type='date']", function () {
        const val = $(this).val();
        if (val && isStillTypingYear(val)) {
            $(this).val('');
            toastr.error("Please enter a complete date (MM/DD/YYYY).", "Invalid date");
            return;
        }
        validateDateRow(this);
    });

    function isStillTypingYear(iso) {
        return !!iso && parseInt(iso.slice(0, 4), 10) < 1900;
    }

    // Weekend and duplicate checks for one date input. Clears the value when it is not allowed.
    function validateDateRow(input) {
        const val = $(input).val();
        if (!val) return;

        if (isWeekendIso(val)) {
            $(input).val('');
            toastr.error("Weekends cannot be selected. Please pick a weekday.", "Invalid date");
            return;
        }

        // A date can only be listed once (no splitting a day into two rows, even across types).
        const $twin = findDateInput(val, input);
        if ($twin) {
            $(input).val('');
            flashInvalid($twin);
            toastr.error(`${moment(val).format("MM/DD/YYYY")} is already in the list. A date can only be filed once.`, "Duplicate date");
        }
    }

    // The other date input already holding $iso (ignoring $except), or null.
    function findDateInput(iso, except) {
        if (!iso) return null;
        let found = null;
        $("#dates_table_body input[type='date']").each(function () {
            if (this !== except && $(this).val() === iso) { found = $(this); return false; }
        });
        return found;
    }

    // Briefly outline a field red so the user sees which row it clashes with.
    function flashInvalid($input) {
        $input.addClass('is-invalid');
        setTimeout(() => $input.removeClass('is-invalid'), 2500);
    }

    // Earliest selectable date (YYYY-MM-DD) for a leave type, or '' for no limit.
    // A negative days_prior (e.g. Sick Leave, filed on return) means backdating is
    // allowed, so there is no limit; the rest need days_prior working days of notice.
    function minDateForType(lt) {
        if (lt && parseInt(lt.days_prior, 10) < 0) return '';
        return addWorkingDaysFromToday(lt ? lt.days_prior : 0);
    }

    function setDateMin($input, minDate) {
        if (minDate) $input.attr('min', minDate); else $input.removeAttr('min');
    }

    // Each date row's min follows that row's own leave type; clears values that fall before it.
    function updateDateInputMins() {
        $("#dates_table_body tr").each(function () {
            const lt = LEAVE_TYPES.find(t => t.id === parseInt($(this).find('.date-leave-type').val(), 10));
            const minDate = minDateForType(lt);
            const $date = $(this).find("input[type='date']");
            setDateMin($date, minDate);
            if (minDate && $date.val() && $date.val() < minDate) $date.val('');
        });
    }

    function buildLeaveTypeOptions(selectedId) {
        const isLwop = $('#is_lwop_flag').val() === '1';
        return LEAVE_TYPES
            .filter(lt => isLwop || parseFloat(lt.balance) > 0)
            .map(lt =>
                `<option value="${lt.id}" ${lt.id === selectedId ? 'selected' : ''}>${lt.description}</option>`
            ).join('');
    }

    function addRow(selectedLeaveTypeId) {
        const minDate = minDateForType(LEAVE_TYPES.find(t => t.id === selectedLeaveTypeId));
        const opts    = buildLeaveTypeOptions(selectedLeaveTypeId || null);
        $("#dates_table_body").append(`
        <tr>
            <td>
                <select class="form-control form-control-sm date-leave-type" name="leave_type_ids[]">
                    <option value="">-- Select Type --</option>
                    ${opts}
                </select>
            </td>
            <td><input type="date" class="form-control form-control-sm" name="leave_dates[]" ${minDate ? `min="${minDate}"` : ''}></td>
            <td>
                <select class="form-control form-control-sm" name="leave_hours[]">
                    <option value="4">4</option>
                    <option value="8" selected>8</option>
                    <option value="10">10</option>
                    <option value="12">12</option>
                    <option value="16">16</option>
                    <option value="24">24</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">Remove</button></td>
        </tr>
        `);
    }

    function removeRow(button) {
        if ($("#dates_table_body tr").length <= 1) {
            toastr.error("Must specify at least one date for leave", "Information");
            return;
        }
        $(button).closest("tr").remove();
    }

    /** ------------------------
     *  Add Date Range
     * ------------------------ */
    function toggleDateRangePanel() {
        const $panel = $("#date_range_panel");
        if ($panel.hasClass("d-none")) {
            $("#range_leave_type").html('<option value="">-- Select Type --</option>' + buildLeaveTypeOptions(null));
            $panel.removeClass("d-none");
        } else {
            $panel.addClass("d-none");
        }
    }

    async function loadHolidayDates() {
        if (HOLIDAY_DATES !== null) return HOLIDAY_DATES;
        try {
            const events = await fetchData(ROUTE_CALENDAR_EVENTS);
            HOLIDAY_DATES = events.map(e => e.start);
        } catch {
            HOLIDAY_DATES = [];
        }
        return HOLIDAY_DATES;
    }

    // Formats a Date using its LOCAL calendar fields (not toISOString(), which
    // converts to UTC first — in a positive-UTC-offset timezone like this
    // app's Asia/Manila, that silently shifts the date back by one day).
    function toLocalIsoDate(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    // Minimum allowed start date for the range panel ('' = no limit), scoped to its own selected leave type.
    function getRangeMinDate() {
        const lid = parseInt($("#range_leave_type").val());
        return minDateForType(LEAVE_TYPES.find(t => t.id === lid));
    }

    $(document).on('change', '#range_leave_type', function () {
        const minDate = getRangeMinDate();
        setDateMin($("#range_start_date"), minDate);
        if (minDate && $("#range_start_date").val() && $("#range_start_date").val() < minDate) $("#range_start_date").val('');
    });

    $(document).on('change', '#range_start_date', function () {
        $("#range_end_date").attr('min', $(this).val());
    });

    async function generateDateRange() {
        const leaveTypeId = parseInt($("#range_leave_type").val());
        const startVal = $("#range_start_date").val();
        const endVal = $("#range_end_date").val();
        const hours = $("#range_hours").val();

        if (!leaveTypeId) { toastr.error("Please select a leave type for the range", "Required"); return; }
        if (!startVal || !endVal) { toastr.error("Please specify both a start and end date", "Required"); return; }
        if (endVal < startVal) { toastr.error("End date must be on or after the start date", "Required"); return; }

        const holidays = await loadHolidayDates();

        let added = 0;
        const alreadyListed = [];
        const cursor = new Date(startVal + 'T00:00:00');
        const end = new Date(endVal + 'T00:00:00');

        while (cursor <= end) {
            const iso = toLocalIsoDate(cursor);
            const day = cursor.getDay();
            const isWeekend = day === 0 || day === 6;
            const isHoliday = holidays.includes(iso);

            if (!isWeekend && !isHoliday && findDateInput(iso, null)) {
                alreadyListed.push(moment(iso).format("MM/DD/YYYY"));   // never add a date twice
            } else if (!isWeekend && !isHoliday) {
                addRow(leaveTypeId);
                const $row = $("#dates_table_body tr:last");
                $row.find("input[type='date']").val(iso);
                $row.find("select[name='leave_hours[]']").val(hours);
                added++;
            }

            cursor.setDate(cursor.getDate() + 1);
        }

        if (alreadyListed.length > 0) {
            toastr.warning(`Skipped ${alreadyListed.length} date(s) already in the list: ${alreadyListed.join(", ")}`, "Duplicate dates");
        }

        if (added === 0) {
            if (alreadyListed.length === 0) {
                toastr.error("No working days in that range (all weekends/holidays)", "Nothing to add");
            }
            return;
        }

        // addRow() sets each new row's leave-type <select> via a pre-marked
        // "selected" option rather than a real user interaction, so it never
        // fires a native change event — run the same reactive updates the
        // per-row change handler runs, or details/commutation/attachment/date
        // minimums silently stay stuck in their pre-selection state.
        refreshDetailsVisibility();
        updateDateInputMins();
        refreshCommutationVisibility();
        refreshAttachmentVisibility();

        $("#range_start_date").val('');
        $("#range_end_date").val('');
        $("#date_range_panel").addClass("d-none");
        toastr.success(`Added ${added} date${added === 1 ? '' : 's'} to the application`, "Success");
    }

    /** ------------------------
     *  Init Leave Types + Balances
     * ------------------------ */
    async function init_leave() {
        // Populates LEAVE_TYPES for date-row dropdowns & renders main-page credit cards.
        // Leave type is now selected per date row — no Section A checkboxes.
        await loadCreditBalance();
    }

    async function loadCreditBalance() {
        try {
            const data = await fetchData(ROUTE_CREDIT_BAL);
            $("#credit_balances, #legend_list").empty();
            LEAVE_TYPES.length = 0;

            data.forEach(it => {
                const bal = parseFloat(it.balance);
                const balanceClass = bal > 0 ? "text-success" : (bal < 0 ? "text-danger" : "");

                // Always show on the main page credit panel
                $("#credit_balances").append(`
                    <div class="col-md-6">
                        <span class="fw-bold">${it.code}:</span>
                        <span class="ms-1 ${balanceClass}">${formatLeaveCreditBalance(it.balance)}</span>
                    </div>
                `);
                $("#legend_list").append(`
                    <div class="col-md-6">
                        <span class="fw-bold">${it.code}:</span>
                        <span class="ms-1">${it.description}</span>
                    </div>
                `);

                LEAVE_TYPES.push({ id: it.id, description: it.description, code: it.code, balance: it.balance, days_prior: it.days_prior ?? 0, commutation: it.commutation ?? 0 });
            });

            return data;
        } catch {
            return [];
        }
    }

    /** ------------------------
     *  Get Leave Applications
     * ------------------------ */
    async function getLeaves() {
        $("#leave_history_list").html("<tr><td colspan='4'>Loading data, please wait...</td></tr>");
        $("#pagination_links").empty();
        $("#page_indicator").text("");

        try {
            const payload = await fetchData(`${ROUTE_GET_LEAVES}?page=${page}`);
            const data       = payload.results    || [];
            const pagination = payload.pagination || null;

            $("#leave_history_list").empty();
            $("#pagination_links").empty();
            $("#page_indicator").text("");

            if (!data.length) {
                $("#pagination_container").addClass("d-none");
                $("#leave_history_list").html("<tr><td colspan='4'>No record found</td></tr>");
                return;
            }

            $("#pagination_container").removeClass("d-none");
            let max_visible = data.length;

            if (pagination) {
                page = pagination.current_page;
                leave_application_last_page = pagination.last_page;
                $("#page_items_count").text(`${pagination.from} - ${pagination.to} of ${pagination.total}`);
                $("#page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
                renderLeaveApplicationPaginationLinks(pagination);
            } else {
                $("#page_items_count").text(`${((page - 1) * 10) + 1} - ${((page - 1) * 10) + max_visible}`);
                $("#page_indicator").text(`Page ${page}`);
            }

            data.slice(0, max_visible).forEach(it => {
                const dates  = Object.values(it.dates_applied).map(d => moment(d.date_applied).format("MM/DD/YYYY"));
                const status = it.status === "Approved" ? `<span class="badge bg-success">APPROVED</span>`
                    : it.status === "Rejected" ? `<span class="badge bg-danger">REJECTED</span>`
                        : `<span class="badge bg-secondary">PENDING</span>`;

                const editItem = it.is_editable
                    ? `<li><a class="dropdown-item" href="javascript:void(0)" onclick="editApplication(${it.id})"><i class="bi bi-pencil-fill text-warning me-2"></i> Edit</a></li>`
                    : '';
                const deleteItem = it.is_deletable
                    ? `<li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="cancelApplication(${it.id})"><i class="bi bi-trash-fill me-2"></i> Delete</a></li>`
                    : '';
                // A rejected application is not printed, so it gets no Print option.
                const printItem = String(it.status || '').toLowerCase() === 'rejected'
                    ? ''
                    : `<li><a class="dropdown-item" href="javascript:void(0)" onclick="printApplication(${it.id})"><i class="bi bi-printer text-primary me-2"></i> Print</a></li>`;

                $("#leave_history_list").append(`
                <tr id="leave_id_${it.id}">
                    <td>${escapeHtml(it.description)}</td>
                    <td>${dates.join(", ")}</td>
                    <td>${status}</td>
                    <td>
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                ${editItem}
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewApplication(${it.id})">
                                        <i class="bi bi-eye-fill text-success me-2"></i> View
                                    </a>
                                </li>
                                ${printItem}
                                ${deleteItem}
                            </ul>
                        </div>
                    </td>
                </tr>
                `);
            });
        } catch {
            $("#leave_history_list").html("<tr><td colspan='4'>Error loading data</td></tr>");
        }
    }

    function renderLeaveApplicationPaginationLinks(pagination) {
        const $links = $("#pagination_links");
        if (!$links.length || !pagination || !pagination.last_page) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();
        $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

        if (start > 1) {
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
            if (start > 2) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
        }

        for (let i = start; i <= end; i++) {
            $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
        }

        if (end < last) {
            if (end < last - 1) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
        }

        $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
    }

    /** ------------------------
     *  Delete Application
     * ------------------------ */
    async function cancelApplication(id) {
        if (!confirm("Are you sure you want to delete this leave application? Credits will be restored.")) return;

        try {
            const url = ROUTE_DELETE_LEAVE.replace(':id', id);
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
            });
            const body = await response.json();
            if (!response.ok) {
                toastr.error(body.message || 'Failed to delete leave application.', 'Error');
                return;
            }
            toastr.success(body.message || 'Leave application deleted.', 'Success');
            getLeaves();
            getLwopHistory();
            loadCreditBalance();
        } catch {
            toastr.error('An error occurred while deleting the leave application.', 'Error');
        }
    }

    /** ------------------------
     *  View / Print Application
     * ------------------------ */
    async function printApplication(id) {
        window.open(`${ROUTE_PRINT_LEAVE}?id=${id}`, '_blank');
    }

    async function editApplication(id) {
        try {
            const data = await fetchData(ROUTE_SHOW_LEAVE.replace(':id', id), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            await init_leave();
            setLwopMode(!!data.is_lwop);
            $("#dates_table_body").empty();
            resetDetailsSection();

            // Pre-fill date rows (each row includes leave_type_id)
            (data.dates || []).forEach(d => {
                const opts        = buildLeaveTypeOptions(d.leave_type_id || null);
                const dateValue   = moment(d.date).format('YYYY-MM-DD');
                // Build row WITHOUT value attribute — browsers clear the value when value < min.
                // Set it via .val() after appending so the min constraint is bypassed.
                const $row = $(`
                    <tr>
                        <td>
                            <select class="form-control form-control-sm date-leave-type" name="leave_type_ids[]">
                                <option value="">-- Select Type --</option>
                                ${opts}
                            </select>
                        </td>
                        <td><input type="date" class="form-control form-control-sm" name="leave_dates[]"></td>
                        <td>
                            <select class="form-control form-control-sm" name="leave_hours[]">
                                <option value="4"  ${d.duration == 4  ? 'selected' : ''}>4</option>
                                <option value="8"  ${d.duration == 8  ? 'selected' : ''}>8</option>
                                <option value="10" ${d.duration == 10 ? 'selected' : ''}>10</option>
                                <option value="12" ${d.duration == 12 ? 'selected' : ''}>12</option>
                                <option value="16" ${d.duration == 16 ? 'selected' : ''}>16</option>
                                <option value="24" ${d.duration == 24 ? 'selected' : ''}>24</option>
                            </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">Remove</button></td>
                    </tr>
                `);
                $("#dates_table_body").append($row);
                // Set date AFTER appending — bypasses browser min-constraint clearing
                $row.find('input[name="leave_dates[]"]').val(dateValue);
            });

            // Enable detail groups based on the pre-filled date row types
            refreshDetailsVisibility();
            updateDateInputMins();
            refreshCommutationVisibility();
            refreshAttachmentVisibility(); // show attachment section if leave type requires it

            // Pre-fill detail radios using leave_id reconstructed from dates by index
            if (Array.isArray(data.details_per_type)) {
                data.details_per_type.forEach(dt => {
                    if (!dt.detail) return;
                    const lt = LEAVE_TYPES.find(t => t.id === dt.leave_id);
                    if (!lt) return;
                    const lower = lt.description.toLowerCase().trim();
                    const cfg   = LEAVE_DETAIL_CONFIG.find(c => c.keywords.some(kw => lower.includes(kw)));
                    if (!cfg) return;
                    const $group = $(`#${cfg.groupId}`);
                    if ($group.hasClass('detail-disabled')) return;
                    $group.find('input[type="radio"][data-role="primary"]').each(function () {
                        if ($(this).siblings('label').text().trim() === dt.detail) {
                            $(this).prop('checked', true);
                            if ($(this).attr('data-has-extra') === 'true' && dt.extra) {
                                let extraText = dt.extra;
                                if (cfg.classification) {
                                    const m = dt.extra.match(/^(.*)\s\((.+)\)$/);
                                    if (m && cfg.classification.options.includes(m[2])) {
                                        extraText = m[1].trim();
                                        $group.find('input[data-role="classification"]').each(function () {
                                            if ($(this).siblings('label').text().trim() === m[2]) {
                                                $(this).prop('checked', true);
                                            }
                                        });
                                    }
                                }
                                $(this).siblings('input[type="text"]').val(extraText);
                            }
                            return false;
                        }
                    });
                });
            }

            editing_leave_id = id;
            $("#btn_save_leave").text("UPDATE");

            // Populate existing attachments — include legacy single attachment if new table is empty
            let existingAttachments = data.attachments || [];
            if (existingAttachments.length === 0 && data.attachment_url) {
                existingAttachments = [{ id: null, url: data.attachment_url, original_name: 'View Attachment' }];
            }
            renderExistingAttachments(existingAttachments);

            file_leave_modal.toggle();
        } catch {
            toastr.error("Error loading leave application for editing.", "Error");
        }
    }

    async function viewApplication(id) {
        try {
            const data = await fetchData(ROUTE_SHOW_LEAVE.replace(':id', id), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            renderLeaveCreditBalances(data.credit_balances);
            $('#leave_name').text(data.name);
            $('#leave_type').text(data.type);
            $('#leave_commutation').text(data.commutation);
            $('#lwop_badge').toggleClass('d-none', !data.is_lwop);

            // Show all attachments (new multi-attachment array + legacy single)
            renderLeaveAttachments(data);
            $('#leave_details_container').html(renderLeaveDetailsHtml(data));

            $('#dates_applied_body').html(
                data.dates.map(d => {
                    const state = d.is_cancelled
                        ? `<span class="lv-pill lv-pill-rejected">Cancelled</span>`
                        : d.final_disapproved
                            ? `<span class="lv-pill lv-pill-rejected">Disapproved</span>`
                            : `<span class="lv-pill lv-pill-muted">Included</span>`;
                    return `<tr><td>${escapeHtml(d.leave_type || '')}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hrs</td><td>${state}</td></tr>`;
                }).join("")
            );

            $('#step0').toggleClass('d-none', !data.approvals.hr_compliance);
            if (data.approvals.hr_compliance) {
                updateStepStatus('#step0', data.approvals.hr_compliance);
            }
            updateStepStatus('#step1', data.approvals.sh);
            updateStepStatus('#step2', data.approvals.dh);
            updateStepStatus('#step3', data.approvals.hr);

            renderLeaveRemarks(data.approvals);

            $('#viewLeaveModal').modal('show');
        } catch {
            alert("Error loading leave application.");
        }
    }

    function renderLeaveDetailsHtml(data) {
        const items = Array.isArray(data.details_per_type) ? data.details_per_type : [];
        if (items.length === 0) return '<span class="text-muted">No details provided.</span>';
        return items.map(d => {
            const typeName = (data.leave_types_map && data.leave_types_map[d.leave_id]) || '';
            const parts = [d.detail, d.extra].filter(Boolean);
            const text = parts.join(' — ');
            return `<div class="mb-1"><strong>${escapeHtml(typeName)}:</strong>${text ? ' ' + escapeHtml(text) : ' <span class="text-muted">—</span>'}</div>`;
        }).join('') || '<span class="text-muted">No details provided.</span>';
    }

    function updateStepStatus(selector, approval) {
        $(selector).removeClass("approved rejected pending acknowledged");
        $(selector + " .circle").removeClass("approved rejected pending acknowledged");

        const status = ['approved', 'rejected', 'acknowledged'].includes(approval?.status) ? approval.status : 'pending';
        $(selector).addClass(status);
        $(selector + " .circle").addClass(status);
    }

    function initApplicantSignaturePad() {
        // A fresh canvas every time the modal opens - editing an application
        // requires a brand-new signature each time, never a reused/stale one.
        initSignaturePad('applicant');
    }

    function setLwopMode(isLwop) {
        $('#is_lwop_flag').val(isLwop ? '1' : '0');
        $('#file_leave_modal_title').text(isLwop ? 'LWOP Application' : 'Leave Application');
    }

    function clearForm() {
        editing_leave_id = null;
        setLwopMode(false);
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#range_leave_type").val('');
        $("#date_range_panel").addClass("d-none");
        $("#leave_attachment").val('');
        $("#new_files_preview").empty();
        $("#existing_attachments").addClass('d-none');
        $("#existing_attachments_list").empty();
        $("#attachment_required_badge").addClass('d-none');
        renderDetailGroups();
        refreshCommutationVisibility();
    }

    function formatLeaveCreditBalance(value) {
        const numericValue = parseFloat(value || 0);
        return Number.isNaN(numericValue) ? "0.000" : numericValue.toFixed(3);
    }

    /** ================================================
     *  LEAVE MONETIZATION
     * ================================================ */
    const ROUTE_MONETIZABLE_TYPES    = "{{ route('monetizableLeaveTypes') }}";
    const ROUTE_GET_MONETIZATIONS    = "{{ route('getLeaveMonetizations') }}";
    const ROUTE_STORE_MONETIZATION   = "{{ route('storeLeaveMonetization') }}";
    const ROUTE_DELETE_MONETIZATION  = "{{ route('leave-monetizations.destroy', ':id') }}";

    let monetization_page      = 1;
    let monetization_last_page = 1;
    let MONETIZABLE_TYPES      = [];
    const monetize_modal       = new bootstrap.Modal(document.getElementById('monetize_leave_modal'));

    // Wire open button
    $("#btn_file_monetization").click(async function () {
        await loadMonetizableTypes();
        $("#monetize_rows_body").empty();
        addMonetizeRow();
        monetize_modal.show();
    });

    $("#btn_add_monetize_row").click(addMonetizeRow);
    $("#btn_submit_monetization").click(handleMonetizationSubmit);

    // Load monetizable leave types into cache
    async function loadMonetizableTypes() {
        try {
            const data = await fetchData(ROUTE_MONETIZABLE_TYPES);
            MONETIZABLE_TYPES = data;
        } catch {
            toastr.error('Failed to load monetizable leave types.', 'Error');
        }
    }

    function buildMonetizeTypeOptions() {
        return MONETIZABLE_TYPES.map(t =>
            `<option value="${t.id}" data-days="${parseFloat(t.balance_days).toFixed(3)}">`
            + `${t.description} — ${parseFloat(t.balance_days).toFixed(3)} days avail.`
            + `</option>`
        ).join('');
    }

    function addMonetizeRow() {
        const row = $(`
        <tr>
            <td>
                <select class="form-select form-select-sm monetize-type-select">
                    <option value="">-- Select Type --</option>
                    ${buildMonetizeTypeOptions()}
                </select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm monetize-days-input"
                       min="0.001" step="0.001" placeholder="0.000">
            </td>
            <td class="text-muted small monetize-avail-cell">—</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMonetizeRow(this)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>`);

        row.find('.monetize-type-select').on('change', function () {
            const opt  = $(this).find(':selected');
            const days = opt.val() ? opt.data('days') : '—';
            $(this).closest('tr').find('.monetize-avail-cell').text(days ? `${days} days` : '—');
        });

        $("#monetize_rows_body").append(row);
    }

    function removeMonetizeRow(btn) {
        if ($("#monetize_rows_body tr").length <= 1) {
            toastr.error('At least one leave type is required.', 'Required');
            return;
        }
        $(btn).closest('tr').remove();
    }

    // Submit — collects all rows, sends as array
    async function handleMonetizationSubmit() {
        const items = [];
        let valid = true;
        const usedTypeIds = new Set();

        $("#monetize_rows_body tr").each(function () {
            const leaveTypeId   = parseInt($(this).find('.monetize-type-select').val(), 10);
            const daysRequested = parseFloat($(this).find('.monetize-days-input').val());

            if (!leaveTypeId || !daysRequested || daysRequested <= 0) {
                valid = false; return false;
            }
            if (usedTypeIds.has(leaveTypeId)) {
                toastr.error('Duplicate leave types detected. Each type can appear only once.', 'Error');
                valid = false; return false;
            }
            usedTypeIds.add(leaveTypeId);

            // Client-side balance guard
            const type = MONETIZABLE_TYPES.find(t => t.id === leaveTypeId);
            if (type && daysRequested > parseFloat(type.balance_days)) {
                toastr.error(
                    `Insufficient ${type.description} balance. You have ${parseFloat(type.balance_days).toFixed(3)} days available.`,
                    'Insufficient Balance'
                );
                valid = false; return false;
            }

            items.push({ leave_type_id: leaveTypeId, days_requested: daysRequested });
        });

        if (!valid) return;
        if (!items.length) {
            toastr.error('Please add at least one leave type.', 'Required');
            return;
        }

        const btn = $("#btn_submit_monetization");
        btn.prop('disabled', true).text('Submitting...');
        try {
            const response = await fetch(ROUTE_STORE_MONETIZATION, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ items }),
            });
            const body = await response.json();
            if (!response.ok) {
                toastr.error(body.message || 'Failed to submit monetization request.', 'Error');
                return;
            }
            toastr.success(body.message || 'Monetization request submitted.', 'Success');
            monetize_modal.hide();
            getMonetizations();
            loadCreditBalance();
        } catch {
            toastr.error('An error occurred. Please try again.', 'Error');
        } finally {
            btn.prop('disabled', false).text('SUBMIT');
        }
    }

    // Fetch and render monetization history
    async function getMonetizations() {
        $("#monetization_history_list").html("<tr><td colspan='5'>Loading...</td></tr>");
        try {
            const payload    = await fetchData(`${ROUTE_GET_MONETIZATIONS}?page=${monetization_page}`);
            const data       = payload.results    || [];
            const pagination = payload.pagination || null;

            $("#monetization_history_list").empty();

            if (!data.length) {
                $("#monetization_pagination_container").addClass("d-none");
                $("#monetization_history_list").html("<tr><td colspan='5'>No records found.</td></tr>");
                return;
            }

            $("#monetization_pagination_container").removeClass("d-none");

            if (pagination) {
                monetization_page      = pagination.current_page;
                monetization_last_page = pagination.last_page;
                $("#monetization_page_items_count").text(`${pagination.from} - ${pagination.to} of ${pagination.total}`);
                $("#monetization_page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
                renderMonetizationPaginationLinks(pagination);
            }

            data.forEach(it => {
                const statusBadge = it.status === 'Approved'
                    ? `<span class="badge bg-success">APPROVED</span>`
                    : it.status === 'Rejected'
                        ? `<span class="badge bg-danger">REJECTED</span>`
                        : `<span class="badge bg-secondary">PENDING</span>`;

                const actionsCell = it.is_deletable ? `
                    <div class="dropdown dropstart">
                        <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteMonetization(${it.id})"><i class="bi bi-trash-fill me-2"></i> Delete</a></li>
                        </ul>
                    </div>` : '';

                $("#monetization_history_list").append(`
                <tr id="monetization_id_${it.id}">
                    <td>${it.leave_type}</td>
                    <td>${it.days_requested}</td>
                    <td>${it.filed_date}</td>
                    <td>${statusBadge}</td>
                    <td>${actionsCell}</td>
                </tr>`);
            });
        } catch {
            $("#monetization_history_list").html("<tr><td colspan='5'>Error loading data.</td></tr>");
        }
    }

    function renderMonetizationPaginationLinks(pagination) {
        const $links  = $("#monetization_pagination_links");
        if (!$links.length || !pagination || !pagination.last_page) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();

        $links.append(`<li class="page-item ${current === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changeMonetizationPage(${current - 1})">Prev</a></li>`);

        for (let i = start; i <= end; i++) {
            $links.append(`<li class="page-item ${i === current ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="changeMonetizationPage(${i})">${i}</a></li>`);
        }

        $links.append(`<li class="page-item ${current === last ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="changeMonetizationPage(${current + 1})">Next</a></li>`);
    }

    function changeMonetizationPage(p) {
        if (p < 1 || p > monetization_last_page) return;
        monetization_page = p;
        getMonetizations();
    }

    // Delete (cancel) pending monetization request
    async function deleteMonetization(id) {
        if (!confirm('Are you sure you want to delete this monetization request? Credits will be restored.')) return;
        try {
            const url      = ROUTE_DELETE_MONETIZATION.replace(':id', id);
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
            });
            const body = await response.json();
            if (!response.ok) {
                toastr.error(body.message || 'Failed to delete request.', 'Error');
                return;
            }
            toastr.success(body.message || 'Request deleted.', 'Success');
            getMonetizations();
            loadCreditBalance();
        } catch {
            toastr.error('An error occurred.', 'Error');
        }
    }

    // Load monetization list when tab is activated
    $('#tab-monetizations').on('shown.bs.tab', function () {
        monetization_page = 1;
        getMonetizations();
    });

    /** ================================================
     *  LWOP APPLICATIONS
     * ================================================ */
    let lwop_page      = 1;
    let lwop_last_page = 1;

    async function getLwopHistory() {
        $("#lwop_history_list").html("<tr><td colspan='4'>Loading data, please wait...</td></tr>");
        $("#lwop_pagination_links").empty();
        $("#lwop_page_indicator").text("");

        try {
            const payload = await fetchData(`${ROUTE_GET_LEAVES}?page=${lwop_page}&is_lwop=1`);
            const data       = payload.results    || [];
            const pagination = payload.pagination || null;

            $("#lwop_history_list").empty();
            $("#lwop_pagination_links").empty();
            $("#lwop_page_indicator").text("");

            if (!data.length) {
                $("#lwop_pagination_container").addClass("d-none");
                $("#lwop_history_list").html("<tr><td colspan='4'>No record found</td></tr>");
                return;
            }

            $("#lwop_pagination_container").removeClass("d-none");
            let max_visible = data.length;

            if (pagination) {
                lwop_page      = pagination.current_page;
                lwop_last_page = pagination.last_page;
                $("#lwop_page_items_count").text(`${pagination.from} - ${pagination.to} of ${pagination.total}`);
                $("#lwop_page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
                renderLwopPaginationLinks(pagination);
            } else {
                $("#lwop_page_items_count").text(`${((lwop_page - 1) * 10) + 1} - ${((lwop_page - 1) * 10) + max_visible}`);
                $("#lwop_page_indicator").text(`Page ${lwop_page}`);
            }

            data.slice(0, max_visible).forEach(it => {
                const dates  = Object.values(it.dates_applied).map(d => moment(d.date_applied).format("MM/DD/YYYY"));
                const status = it.status === "Approved" ? `<span class="badge bg-success">APPROVED</span>`
                    : it.status === "Rejected" ? `<span class="badge bg-danger">REJECTED</span>`
                        : `<span class="badge bg-secondary">PENDING</span>`;

                const editItem = it.is_editable
                    ? `<li><a class="dropdown-item" href="javascript:void(0)" onclick="editApplication(${it.id})"><i class="bi bi-pencil-fill text-warning me-2"></i> Edit</a></li>`
                    : '';
                const deleteItem = it.is_deletable
                    ? `<li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="cancelApplication(${it.id})"><i class="bi bi-trash-fill me-2"></i> Delete</a></li>`
                    : '';
                // A rejected application is not printed, so it gets no Print option.
                const printItem = String(it.status || '').toLowerCase() === 'rejected'
                    ? ''
                    : `<li><a class="dropdown-item" href="javascript:void(0)" onclick="printApplication(${it.id})"><i class="bi bi-printer text-primary me-2"></i> Print</a></li>`;

                $("#lwop_history_list").append(`
                <tr id="lwop_id_${it.id}">
                    <td>${escapeHtml(it.description)}</td>
                    <td>${dates.join(", ")}</td>
                    <td>${status}</td>
                    <td>
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                ${editItem}
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewApplication(${it.id})">
                                        <i class="bi bi-eye-fill text-success me-2"></i> View
                                    </a>
                                </li>
                                ${printItem}
                                ${deleteItem}
                            </ul>
                        </div>
                    </td>
                </tr>
                `);
            });
        } catch {
            $("#lwop_history_list").html("<tr><td colspan='4'>Error loading data</td></tr>");
        }
    }

    function renderLwopPaginationLinks(pagination) {
        const $links = $("#lwop_pagination_links");
        if (!$links.length || !pagination || !pagination.last_page) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();
        $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

        if (start > 1) {
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
            if (start > 2) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
        }

        for (let i = start; i <= end; i++) {
            $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
        }

        if (end < last) {
            if (end < last - 1) $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
            $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
        }

        $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
    }

    $("body").on("click", "#lwop_pagination_links .page-link[data-page]", function (e) {
        e.preventDefault();
        const targetPage = parseInt($(this).attr("data-page"), 10);
        if (!targetPage || targetPage === lwop_page) return;
        if (targetPage < 1 || targetPage > lwop_last_page) return;
        lwop_page = targetPage;
        getLwopHistory();
    });

    // Load LWOP list when tab is activated
    $('#tab-lwop').on('shown.bs.tab', function () {
        lwop_page = 1;
        getLwopHistory();
    });
</script>
