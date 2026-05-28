<script>
    const ROUTE_GET_APPROVED_DATES    = "{{ route('getApprovedDates') }}";
    const ROUTE_FILE_CANCELLATION     = "{{ route('fileLeaveCancellation') }}";
    const ROUTE_MY_CANCELLATIONS      = "{{ route('getMyCancellations') }}";
    const ROUTE_SHOW_CANCELLATION     = "{{ route('leave-cancellations.show', ':id') }}";

    let cancelPage = 1, cancelLastPage = 1;

    $(function () {
        getCancellations();

        $('#btn_file_cancellation').on('click', openFilingModal);
        $('#btn_submit_cancellation').on('click', submitCancellation);

        $('body').on('click', '#cancel_pagination_links .page-link[data-page]', function (e) {
            e.preventDefault();
            const p = parseInt($(this).data('page'));
            if (!p || p === cancelPage || p < 1 || p > cancelLastPage) return;
            cancelPage = p;
            getCancellations();
        });
    });

    // -----------------------------------------------------------------------
    // Filing modal
    // -----------------------------------------------------------------------
    async function openFilingModal() {
        $('#cancel_reason').val('');
        $('#cancel_dates_container').hide().empty();
        $('#cancel_dates_empty').hide();
        $('#cancel_dates_loading').show();
        const modal = new bootstrap.Modal(document.getElementById('fileCancellationModal'));
        modal.show();

        try {
            const dates = await (await fetch(ROUTE_GET_APPROVED_DATES)).json();
            $('#cancel_dates_loading').hide();

            if (!dates.length) { $('#cancel_dates_empty').show(); return; }

            // Group by leave_application_id
            const groups = {};
            dates.forEach(d => {
                if (!groups[d.leave_application_id]) groups[d.leave_application_id] = { type: d.leave_type, dates: [] };
                groups[d.leave_application_id].dates.push(d);
            });

            Object.entries(groups).forEach(([appId, grp]) => {
                $('#cancel_dates_container').append(`<div class="fw-semibold text-muted small mb-1 mt-2">${grp.type}</div>`);
                grp.dates.forEach(d => {
                    const label = moment(d.date_applied).format('MM/DD/YYYY') + ` (${d.hours} hrs)`;
                    $('#cancel_dates_container').append(`
                        <div class="form-check">
                            <input class="form-check-input cancel-date-cb" type="checkbox" value="${d.id}" id="cd_${d.id}">
                            <label class="form-check-label" for="cd_${d.id}">${label}</label>
                        </div>
                    `);
                });
            });

            $('#cancel_dates_container').show();
        } catch {
            $('#cancel_dates_loading').text('Failed to load dates.');
        }
    }

    async function submitCancellation() {
        const dateIds = $('.cancel-date-cb:checked').map(function () { return parseInt(this.value); }).get();
        const reason  = $('#cancel_reason').val().trim();

        if (!dateIds.length) { toastr.error('Select at least one date to cancel.', 'Required'); return; }
        if (!reason)         { toastr.error('Please provide a reason.', 'Required'); return; }

        const btn = $('#btn_submit_cancellation').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm"></span> Submitting...');

        try {
            const res = await fetch(ROUTE_FILE_CANCELLATION, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({ date_ids: dateIds, reason })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error');
            toastr.success(data.message, 'Success');
            bootstrap.Modal.getInstance(document.getElementById('fileCancellationModal')).hide();
            cancelPage = 1;
            getCancellations();
        } catch (e) {
            toastr.error(e.message, 'Error');
        } finally {
            $('#btn_submit_cancellation').prop('disabled', false).html('Submit');
        }
    }

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------
    async function getCancellations() {
        $('#cancellation_list').html('<tr><td colspan="5">Loading...</td></tr>');
        try {
            const res  = await fetch(`${ROUTE_MY_CANCELLATIONS}?page=${cancelPage}`);
            const json = await res.json();
            renderCancellations(json.data, json.pagination);
        } catch {
            $('#cancellation_list').html('<tr><td colspan="5">Error loading data.</td></tr>');
        }
    }

    function renderCancellations(data, pag) {
        const $body = $('#cancellation_list').empty();
        if (!data.length) {
            $body.html('<tr><td colspan="5" class="text-muted">No cancellations filed.</td></tr>');
            $('#cancel_pagination_container').hide(); return;
        }

        data.forEach(r => {
            const dates  = r.dates.map(d => moment(d).format('MM/DD/YYYY')).join(', ');
            const badge  = statusBadge(r.status);
            $body.append(`
                <tr>
                    <td>${dates}</td>
                    <td>${r.reason}</td>
                    <td>${moment(r.created_at).format('MM/DD/YYYY')}</td>
                    <td>${badge}</td>
                    <td>
                        <button class="btn btn-sm" onclick="viewCancellation(${r.id})">
                            <i class="bi bi-eye-fill text-success"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        cancelLastPage = pag.last_page;
        const from = (pag.current_page - 1) * pag.per_page + 1;
        $('#cancel_page_count').text(`${from} – ${from + data.length - 1} of ${pag.total}`);
        $('#cancel_pagination_container').show();
        renderCancelPagination(pag);
    }

    function renderCancelPagination(p) {
        const $ul = $('#cancel_pagination_links').empty();
        const c = p.current_page, last = p.last_page;
        $ul.append(`<li class="page-item${c<=1?' disabled':''}"><a class="page-link" href="#" data-page="${c-1}">Prev</a></li>`);
        for (let i = Math.max(1,c-2); i <= Math.min(last,c+2); i++) {
            $ul.append(`<li class="page-item${i===c?' active':''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
        }
        $ul.append(`<li class="page-item${c>=last?' disabled':''}"><a class="page-link" href="#" data-page="${c+1}">Next</a></li>`);
    }

    // -----------------------------------------------------------------------
    // View modal
    // -----------------------------------------------------------------------
    async function viewCancellation(id) {
        try {
            const res  = await fetch(ROUTE_SHOW_CANCELLATION.replace(':id', id));
            const data = await res.json();
            renderCancellationModal(data);
        } catch { toastr.error('Error loading cancellation.', 'Error'); }
    }

    function renderCancellationModal(data) {
        setCancelStep('#cs_step1', data.approvals.sh);
        setCancelStep('#cs_step2', data.approvals.dh);
        setCancelStep('#cs_step3', data.approvals.hr);

        $('#vc_reason').text(data.reason);
        $('#vc_dates').html(data.dates.map(d =>
            `<li class="list-group-item py-1">${moment(d.date_applied).format('MM/DD/YYYY')} — ${d.hours} hrs</li>`
        ).join(''));

        const roles = { sh: 'Supervisor', dh: 'Division Head', hr: 'Human Resource' };
        let rhtml = '';
        Object.entries(roles).forEach(([key, label]) => {
            const a = data.approvals[key];
            if (a?.remarks) rhtml += `<div class="mb-2"><strong>${label}:</strong><p class="mb-0">${a.remarks}</p><small class="text-muted">Status: ${a.status}</small></div>`;
        });
        $('#vc_remarks').html(rhtml || '<p class="text-muted mb-0">No remarks yet.</p>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewCancellationModal')).show();
    }

    function setCancelStep(sel, a) {
        const s = a?.status || 'pending';
        $(sel).removeClass('approved rejected pending').addClass(s);
        $(sel + ' .circle').attr('class', 'circle ' + s);
    }

    function statusBadge(s) {
        if (s === 'Approved') return '<span class="badge bg-success">APPROVED</span>';
        if (s === 'Rejected') return '<span class="badge bg-danger">REJECTED</span>';
        return '<span class="badge bg-secondary">PENDING</span>';
    }
</script>
