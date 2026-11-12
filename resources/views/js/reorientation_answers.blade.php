<script>
    const ROUTE_REOANS_LIST       = "{{ route('reorientation.answers.list') }}";
    const ROUTE_REOANS_CHECK      = "{{ route('reorientation.answers.check') }}";
    const ROUTE_REOANS_SUBMISSION = "{{ route('reorientation.employee_submission') }}";

    const REOANS_STATUS = {
        not_started: {label: 'Not yet', cls: 'bg-secondary'},
        submitted:   {label: 'Pending', cls: 'bg-warning text-dark'},
        passed:      {label: 'Passed',  cls: 'bg-success'},
        failed:      {label: 'Failed',  cls: 'bg-danger'},
    };

    let reoansModule = parseInt($('#reoans_tabs .nav-link.active').data('module')) || 1;
    let reoansPage = 1, reoansLastPage = 1;
    let reoansRows = {};          // submission_id -> row, for the current page
    let reoansActionIds = [];     // submission ids the confirmation modal will update
    let reoansActionStatus = null;
    let reoansViewId = null;      // submission open in the answers modal

    function reoansEscape(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function reoansDate(value) {
        return value ? moment(value).format('MM/DD/YYYY h:mm A') : '—';
    }

    function reoansName(r) {
        const mname = r.mname && r.mname.toUpperCase() !== 'N/A' ? r.mname : '';
        return [r.lname ? r.lname + ',' : '', r.fname, mname, r.suffix].filter(Boolean).join(' ');
    }

    function reoansStatus() {
        return $('input[name="reoans_status"]:checked').val();
    }

    $(function () {
        getReoAnswers();

        $('#reoans_tabs').on('click', '.nav-link', function () {
            $('#reoans_tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            reoansModule = parseInt($(this).data('module'));
            reoansPage = 1;
            getReoAnswers();
        });

        $('input[name="reoans_status"]').on('change', function () {
            reoansPage = 1;
            getReoAnswers();
        });

        let searchTimer;
        $('#reoans_search').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { reoansPage = 1; getReoAnswers(); }, 400);
        });

        $('body').on('click', '#reoans_pagination_links .page-link[data-page]', function (e) {
            e.preventDefault();
            const p = parseInt($(this).data('page'));
            if (!p || p === reoansPage || p < 1 || p > reoansLastPage) return;
            reoansPage = p;
            getReoAnswers();
        });

        $('#reoans_select_all').on('change', function () {
            $('#reoans_list .reoans-row-check').prop('checked', this.checked);
            reoansUpdateSelection();
        });
        $('#reoans_list').on('change', '.reoans-row-check', reoansUpdateSelection);

        $('#reoans_list').on('click', '.reoans-view-btn', function () {
            openReoAnswer(parseInt($(this).data('id')));
        });

        $('.reoans-action-btn').on('click', function () {
            const ids = $('#reoans_list .reoans-row-check:checked').map((i, el) => parseInt($(el).val())).get();
            if (!ids.length) return;
            openReoAction(ids, $(this).data('status'));
        });

        $('.reoans-single-btn').on('click', function () {
            if (!reoansViewId) return;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reoansViewModal')).hide();
            openReoAction([reoansViewId], $(this).data('status'));
        });

        $('#btn_reoans_action_confirm').on('click', submitReoAction);
    });

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------
    async function getReoAnswers() {
        const status = reoansStatus();
        const checkable = status !== 'not_started';
        $('.reoans-check-col, .reoans-view-col').toggle(checkable);
        $('#reoans_bulk').toggleClass('d-none', !checkable);
        $('#reoans_select_all').prop('checked', false);
        reoansUpdateSelection();

        const cols = checkable ? 8 : 6;
        $('#reoans_list').html(`<tr><td colspan="${cols}" class="reoans-empty">Loading...</td></tr>`);

        const params = new URLSearchParams({
            module_no: reoansModule,
            status: status,
            search: $('#reoans_search').val().trim(),
            page: reoansPage,
        });

        try {
            const res = await fetch(ROUTE_REOANS_LIST + '?' + params.toString());
            const json = await res.json();
            if (!res.ok) throw new Error(json.error);
            // Ignore a late response for a tab or filter the user already left
            if (parseInt(params.get('module_no')) !== reoansModule || params.get('status') !== reoansStatus()) return;
            renderReoCounts(json.counts);
            renderReoAnswers(json.data, json.pagination, checkable);
        } catch (e) {
            $('#reoans_list').html(`<tr><td colspan="${cols}" class="reoans-empty">Could not load the list.</td></tr>`);
            $('#reoans_pagination_container').hide();
        }
    }

    function renderReoCounts(counts) {
        Object.keys(counts).forEach(no => {
            const pending = counts[no].submitted;
            $(`.reoans-tab-pending[data-module="${no}"]`).text(pending).toggleClass('d-none', !pending);
        });
        const c = counts[reoansModule] || {};
        Object.keys(REOANS_STATUS).forEach(s => $(`[data-count="${s}"]`).text(c[s] || 0));
    }

    function renderReoAnswers(data, pag, checkable) {
        const $body = $('#reoans_list').empty();
        reoansRows = {};

        // A bulk action can empty the last page: step back to the new last page
        if (!data.length && pag.current_page > 1 && pag.last_page < pag.current_page) {
            reoansPage = Math.max(1, pag.last_page);
            getReoAnswers();
            return;
        }

        if (!data.length) {
            $body.html(`<tr><td colspan="${checkable ? 8 : 6}" class="reoans-empty">No employees found.</td></tr>`);
            $('#reoans_pagination_container').hide();
            return;
        }

        data.forEach(r => {
            if (r.submission_id) reoansRows[r.submission_id] = r;
            const checker = r.checker_fname
                ? reoansEscape(r.checker_fname + ' ' + r.checker_lname) + `<br><span class="small text-muted">${reoansDate(r.checked_at)}</span>`
                : '—';

            $body.append(`<tr>
                ${checkable ? `<td class="text-center"><input type="checkbox" class="form-check-input reoans-row-check" value="${r.submission_id}"></td>` : ''}
                <td class="fw-semibold">${reoansEscape(reoansName(r))}</td>
                <td>${reoansEscape(r.section || '—')}</td>
                <td>${reoansDate(r.submitted_at)}</td>
                <td>${r.attempt_no || '—'}</td>
                <td>${checker}</td>
                <td><div class="reoans-remarks">${reoansEscape(r.remarks || '—')}</div></td>
                ${checkable ? `<td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary reoans-view-btn" data-id="${r.submission_id}"><i class="bi bi-eye"></i></button></td>` : ''}
            </tr>`);
        });

        reoansLastPage = pag.last_page;
        const from = (pag.current_page - 1) * pag.per_page + 1;
        $('#reoans_page_count').text(`${from} – ${from + data.length - 1} of ${pag.total}`);
        $('#reoans_pagination_container').show();
        renderReoPagination(pag);
    }

    function renderReoPagination(p) {
        const $ul = $('#reoans_pagination_links').empty();
        const c = p.current_page, last = p.last_page;
        $ul.append(`<li class="page-item${c <= 1 ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${c - 1}">Prev</a></li>`);
        for (let i = Math.max(1, c - 2); i <= Math.min(last, c + 2); i++) {
            $ul.append(`<li class="page-item${i === c ? ' active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
        }
        $ul.append(`<li class="page-item${c >= last ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${c + 1}">Next</a></li>`);
    }

    function reoansUpdateSelection() {
        const $all = $('#reoans_list .reoans-row-check');
        const checked = $all.filter(':checked').length;
        $('#reoans_selected_count').text(checked);
        $('.reoans-action-btn').prop('disabled', checked === 0);
        $('#reoans_select_all')
            .prop('checked', $all.length > 0 && checked === $all.length)
            .prop('indeterminate', checked > 0 && checked < $all.length);
    }

    // -----------------------------------------------------------------------
    // Answers modal
    // -----------------------------------------------------------------------
    async function openReoAnswer(submissionId) {
        const r = reoansRows[submissionId];
        if (!r) return;
        try {
            const res = await fetch(ROUTE_REOANS_SUBMISSION + '?user_id=' + r.user_id + '&module_no=' + reoansModule);
            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.error || 'Something went wrong, please try again');
                return;
            }

            reoansViewId = data.submission_id;
            const s = REOANS_STATUS[data.status] || REOANS_STATUS.not_started;
            $('#reoans_view_name').text(reoansName(r));
            $('#reoans_view_title').html(`Module ${data.module_no}: ${reoansEscape(data.title)} <span class="badge ${s.cls} ms-1">${s.label}</span>`
                + (data.attempt_no > 1 ? ` <span class="ms-1">Attempt ${data.attempt_no}</span>` : ''));

            let html = '';
            data.items.forEach((it, i) => {
                html += `<div class="border rounded-3 p-3 mb-2 bg-light">
                    <div class="fw-semibold mb-2" style="white-space:pre-wrap">${i + 1}. ${reoansEscape(it.question)}</div>
                    <div class="bg-white border rounded-3 p-2" style="white-space:pre-wrap">${reoansEscape(it.answer)}</div>
                </div>`;
            });
            if (data.remarks) {
                html += `<div class="mt-3"><span class="fw-semibold">Remarks:</span> <span style="white-space:pre-wrap">${reoansEscape(data.remarks)}</span></div>`;
            }
            $('#reoans_view_answers').html(html || '<div class="text-muted">No answers.</div>');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reoansViewModal')).show();
        } catch (e) {
            toastr.error('Something went wrong, please try again');
        }
    }

    // -----------------------------------------------------------------------
    // Pass / Fail
    // -----------------------------------------------------------------------
    function openReoAction(ids, status) {
        reoansActionIds = ids;
        reoansActionStatus = status;
        const failed = status === 'failed';
        const who = ids.length === 1 && reoansRows[ids[0]]
            ? reoansName(reoansRows[ids[0]])
            : `${ids.length} employees`;

        $('#reoans_action_title').text(failed ? 'Mark as Failed' : 'Mark as Passed');
        $('#reoans_action_text').html(`Mark the Module ${reoansModule} post test of <strong>${reoansEscape(who)}</strong> as <strong>${failed ? 'FAILED' : 'PASSED'}</strong>?`);
        $('#reoans_action_required').toggle(failed);
        $('#reoans_action_hint').text(failed
            ? 'Required. The same remarks are sent to every selected employee so they know what to improve.'
            : 'Optional.');
        $('#reoans_action_remarks').val(ids.length === 1 && reoansRows[ids[0]] ? (reoansRows[ids[0]].remarks || '') : '');
        $('#btn_reoans_action_confirm')
            .removeClass('btn-success btn-danger')
            .addClass(failed ? 'btn-danger' : 'btn-success')
            .text(failed ? 'Mark as Failed' : 'Mark as Passed');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('reoansActionModal')).show();
    }

    async function submitReoAction() {
        const remarks = $('#reoans_action_remarks').val().trim();
        if (reoansActionStatus === 'failed' && !remarks) {
            toastr.warning('Please add remarks so the employees know what to improve.');
            $('#reoans_action_remarks').focus();
            return;
        }

        const $btn = $('#btn_reoans_action_confirm').prop('disabled', true);
        try {
            const res = await fetch(ROUTE_REOANS_CHECK, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                body: JSON.stringify({
                    module_no: reoansModule,
                    submission_ids: reoansActionIds,
                    status: reoansActionStatus,
                    remarks: remarks,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.error || 'Something went wrong, please try again');
                return;
            }
            toastr.success(data.message);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reoansActionModal')).hide();
            getReoAnswers();
        } catch (e) {
            toastr.error('Something went wrong, please try again');
        } finally {
            $btn.prop('disabled', false);
        }
    }
</script>
