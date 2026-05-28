<script>
    // Route URLs (rendered by Blade, double-quoted to avoid JS lint false-positives)
    const ROUTE_INSERT_LEAVE   = "{{ route('insertLeaveApplication') }}";
    const ROUTE_UPDATE_LEAVE   = "{{ route('leave-applications.update', ':id') }}";
    const ROUTE_GET_LEAVES     = "{{ route('getLeaveApplications') }}";
    const ROUTE_SHOW_LEAVE     = "{{ route('leave-applications.show', ':id') }}";
    const ROUTE_PRINT_LEAVE    = "{{ route('printLeaveForm') }}";
    const ROUTE_CREDIT_BAL     = "{{ route('getCreditBalances') }}";

    const USER_SEX = "{{ strtoupper(\App\Models\Employee::where('user_id', Auth::id())->value('sex') ?? '') }}";
    const GENDER_RESTRICTED_TYPES = {
        female_only: ['maternity leave', 'gyne leave', 'vawc leave'],
        male_only: ['paternity leave'],
    };

    const ATTACHMENT_REQUIRED_KEYWORDS = ['maternity leave', 'paternity leave'];

    let page = 1;
    let leave_application_last_page = 1;
    let editing_leave_id = null;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));

    // Available leave types with balance > 0 — populated by loadCreditBalance()
    const LEAVE_TYPES = [];

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
                { label: 'Work-Related', hasExtra: false },
                { label: 'Non-Work Related', hasExtra: false },
            ],
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
                    <input class="form-check-input me-2" type="radio" name="${cfg.groupId}" disabled data-has-extra="${opt.hasExtra}">
                    <label class="form-check-label me-2">${opt.label}</label>
                    ${opt.hasExtra ? '<input class="form-control w-50" type="text" disabled placeholder="Required">' : ''}
                </div>
            `).join('');

            $container.append(`
                <div id="${cfg.groupId}" class="detail-group detail-disabled mt-3"
                     style="opacity:0.4; pointer-events:none;">
                    <div>${cfg.label}</div>
                    ${optionsHtml}
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
            const checked = $group.find('input[type="radio"]:checked');
            if (!checked.length) return;
            const $extraInput = checked.siblings('input[type="text"]');
            details_map[lid] = {
                detail: checked.siblings('label').text().trim(),
                extra:  ($extraInput.length ? $extraInput.val().trim() : '') || null,
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
        // Every active detail group must have a radio selected
        const detailMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            return !$group.hasClass('detail-disabled')
                && $group.find('input[type="radio"]:checked').length === 0;
        });
        if (detailMissing) {
            is_valid = false;
            toastr.error("Please provide details for all selected leave types", "Required");
        }
        // When a radio with hasExtra is selected, its text input is required
        const extraMissing = LEAVE_DETAIL_CONFIG.some(cfg => {
            const $group = $(`#${cfg.groupId}`);
            if ($group.hasClass('detail-disabled')) return false;
            const checked = $group.find('input[type="radio"]:checked');
            if (!checked.length) return false;
            if (checked.attr('data-has-extra') !== 'true') return false;
            const $extraInput = checked.siblings('input[type="text"]');
            return !$extraInput.length || !$extraInput.val().trim();
        });
        if (extraMissing) {
            is_valid = false;
            toastr.error("Please fill in the required detail text for your selected option", "Required");
        }
        if (!commutation) {
            is_valid = false;
            toastr.error("Please specify commutation", "Required");
        }

        // Validate attachment when any date has a type requiring it
        const attachmentRequired = date_applied_array.some(d => {
            const lt = LEAVE_TYPES.find(t => t.id === d.leave_type_id);
            return lt && ATTACHMENT_REQUIRED_KEYWORDS.some(kw => lt.description.toLowerCase().includes(kw));
        });
        const attachFile = document.getElementById('leave_attachment') && document.getElementById('leave_attachment').files[0];
        if (attachmentRequired && !editing_leave_id && !attachFile) {
            is_valid = false;
            toastr.error("An attachment is required for Maternity/Paternity Leave", "Required");
        }

        if (!is_valid) return;

        const params = {
            details_map,
            commutation,
            dates: date_applied_array,  // each item has { date_applied, hours, leave_type_id }
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
                if (attachFile) fd.append('attachment', attachFile);
                fetchOptions = {
                    method: "POST",
                    headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') },
                    body: fd
                };
            }

            const body = await fetchData(url, fetchOptions);

            toastr.success(body.message || (isEdit ? "Leave updated successfully" : "Leave submitted successfully"), "Information");
            clearForm();
            getLeaves();
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
        $("#attachment_section").toggle(needsAttachment);
        if (!needsAttachment) $("#leave_attachment").val('');
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

    // Returns the ISO date string (YYYY-MM-DD) of the earliest allowed leave date
    // based on the highest days_prior among types currently selected in date rows.
    function getMinLeaveDate() {
        let maxPrior = 0;
        getSelectedLeaveTypeIds().forEach(lid => {
            const lt = LEAVE_TYPES.find(t => t.id === lid);
            if (lt && lt.days_prior > maxPrior) maxPrior = lt.days_prior;
        });
        const d = new Date();
        d.setDate(d.getDate() + maxPrior);
        return d.toISOString().split('T')[0];
    }

    // Updates the min attribute on all existing date rows; clears values that fall before the new min.
    function updateDateInputMins() {
        const minDate = getMinLeaveDate();
        $("#dates_table_body input[type='date']").each(function () {
            $(this).attr('min', minDate);
            if ($(this).val() && $(this).val() < minDate) $(this).val('');
        });
    }

    function buildLeaveTypeOptions(selectedId) {
        return LEAVE_TYPES.map(lt =>
            `<option value="${lt.id}" ${lt.id === selectedId ? 'selected' : ''}>${lt.description}</option>`
        ).join('');
    }

    function addRow(selectedLeaveTypeId) {
        const minDate = getMinLeaveDate();
        const opts    = buildLeaveTypeOptions(selectedLeaveTypeId || null);
        $("#dates_table_body").append(`
        <tr>
            <td>
                <select class="form-control form-control-sm date-leave-type" name="leave_type_ids[]">
                    <option value="">-- Select Type --</option>
                    ${opts}
                </select>
            </td>
            <td><input type="date" class="form-control form-control-sm" name="leave_dates[]" min="${minDate}"></td>
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

                $("#leave_history_list").append(`
                <tr id="leave_id_${it.id}">
                    <td>${it.description}</td>
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
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="printApplication(${it.id})">
                                        <i class="bi bi-printer text-primary me-2"></i> Print
                                    </a>
                                </li>
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
            $("#dates_table_body").empty();
            resetDetailsSection();

            // Pre-fill date rows (each row includes leave_type_id)
            (data.dates || []).forEach(d => {
                const opts    = buildLeaveTypeOptions(d.leave_type_id || null);
                const minDate = getMinLeaveDate();
                $("#dates_table_body").append(`
                    <tr>
                        <td>
                            <select class="form-control form-control-sm date-leave-type" name="leave_type_ids[]">
                                <option value="">-- Select Type --</option>
                                ${opts}
                            </select>
                        </td>
                        <td><input type="date" class="form-control form-control-sm" name="leave_dates[]" min="${minDate}" value="${d.date}"></td>
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
            });

            // Enable detail groups based on the pre-filled date row types
            refreshDetailsVisibility();
            updateDateInputMins();
            refreshCommutationVisibility();

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
                    $group.find('input[type="radio"]').each(function () {
                        if ($(this).siblings('label').text().trim() === dt.detail) {
                            $(this).prop('checked', true);
                            if ($(this).attr('data-has-extra') === 'true' && dt.extra) {
                                $(this).siblings('input[type="text"]').val(dt.extra);
                            }
                            return false;
                        }
                    });
                });
            }

            editing_leave_id = id;
            $("#btn_save_leave").text("UPDATE");
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

            $('#leave_name').text(data.name);
            $('#leave_type').text(data.type);
            $('#leave_commutation').text(data.commutation);

            if (data.attachment_url) {
                $('#view_attachment_link').attr('href', data.attachment_url);
                $('#view_attachment_row').show();
            } else {
                $('#view_attachment_row').hide();
            }
            $('#leave_details').val((data.details || '') + ':' + (data.extra || ''));

            $('#dates_applied_body').html(
                data.dates.map(d => `<tr><td>${d.leave_type || ''}</td><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td></tr>`).join("")
            );

            updateStepStatus('#step1', data.approvals.sh);
            updateStepStatus('#step2', data.approvals.dh);
            updateStepStatus('#step3', data.approvals.hr);

            let remarksHtml = "";
            ["sh", "dh", "hr"].forEach(role => {
                if (data.approvals[role]?.remarks) {
                    const label = role === "sh" ? "Supervisor"
                        : role === "dh" ? "Division Head" : "Human Resource";
                    remarksHtml += `
                        <div class="mb-2">
                            <strong>${label}:</strong>
                            <p class="mb-0">${data.approvals[role].remarks}</p>
                        </div><hr>
                    `;
                }
            });
            $('#remarks_history').html(remarksHtml || `<p class="text-muted mb-0">No remarks yet.</p>`);

            $('#viewLeaveModal').modal('show');
        } catch {
            alert("Error loading leave application.");
        }
    }

    function updateStepStatus(selector, approval) {
        $(selector).removeClass("approved rejected pending");
        $(selector + " .circle").removeClass("approved rejected pending");

        if (approval?.status === 'approved') {
            $(selector).addClass("approved");
            $(selector + " .circle").addClass("approved");
        } else if (approval?.status === 'rejected') {
            $(selector).addClass("rejected");
            $(selector + " .circle").addClass("rejected");
        } else {
            $(selector).addClass("pending");
            $(selector + " .circle").addClass("pending");
        }
    }

    function clearForm() {
        editing_leave_id = null;
        $("#btn_save_leave").text("SUBMIT");
        $("#leave_form input").not("[type='radio'], [type='checkbox']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
        $("#dates_table_body").empty();
        $("#leave_attachment").val('');
        $("#attachment_section").hide();
        renderDetailGroups();
        refreshCommutationVisibility();
    }

    function formatLeaveCreditBalance(value) {
        const numericValue = parseFloat(value || 0);
        return Number.isNaN(numericValue) ? "0.000" : numericValue.toFixed(3);
    }
</script>
