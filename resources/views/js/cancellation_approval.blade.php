<script>
    const ROUTE_CA_FOR_APPROVAL  = "{{ route('getCancellationForApproval') }}";
    const ROUTE_CA_UPDATE_STATUS = "{{ route('updateCancellationApprovalStatus') }}";
    const ROUTE_CA_SHOW          = "{{ route('leave-cancellations.show', ':id') }}";

    let caPage = 1, caLastPage = 1, caActiveFilter = 'pending', caCurrentId = null;

    $(function () {
        getCancellationsForApproval();

        $('[data-filter]').on('click', function () {
            $('[data-filter]').removeClass('active');
            $(this).addClass('active');
            caActiveFilter = $(this).data('filter');
            caPage = 1;
            getCancellationsForApproval();
        });

        let caSearchTimer;
        $('#ca_search_employee').on('input', function () {
            clearTimeout(caSearchTimer);
            caSearchTimer = setTimeout(() => { caPage = 1; getCancellationsForApproval(); }, 400);
        });

        $('body').on('click', '#ca_pagination_links .page-link[data-page]', function (e) {
            e.preventDefault();
            const p = parseInt($(this).data('page'));
            if (!p || p === caPage || p < 1 || p > caLastPage) return;
            caPage = p;
            getCancellationsForApproval();
        });

        $('#ca_approve_btn').on('click', function () { handleCaAction('Approved'); });
        $('#ca_reject_btn').on('click',  function () { handleCaAction('Rejected'); });
    });

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------
    async function getCancellationsForApproval() {
        $('#ca_list').html('<tr><td colspan="5">Loading...</td></tr>');
        const search = $('#ca_search_employee').val().trim();
        try {
            const res  = await fetch(`${ROUTE_CA_FOR_APPROVAL}?page=${caPage}&filter=${caActiveFilter}&search_employee=${encodeURIComponent(search)}`);
            const json = await res.json();
            renderCaList(json.data, json.pagination);
        } catch {
            $('#ca_list').html('<tr><td colspan="5">Error loading data.</td></tr>');
        }
    }

    function renderCaList(data, pag) {
        const $body = $('#ca_list').empty();
        if (!data.length) {
            $body.html('<tr><td colspan="5" class="text-muted">No records.</td></tr>');
            $('#ca_pagination_container').hide(); return;
        }

        data.forEach(r => {
            const name = [r.fname, r.mname, r.lname].filter(Boolean).join(' ');
            const badge = caBadge(r.status);
            $body.append(`
                <tr>
                    <td>${name}</td>
                    <td><small>${caDates(r)}</small></td>
                    <td>${r.reason}</td>
                    <td>${moment(r.created_at).format('MM/DD/YYYY')}</td>
                    <td>
                        <button class="btn btn-sm" onclick="viewCancellationApproval(${r.id})">
                            <i class="bi bi-eye-fill text-success"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        caLastPage = pag.last_page;
        const from = (pag.current_page - 1) * pag.per_page + 1;
        $('#ca_page_count').text(`${from} – ${from + data.length - 1} of ${pag.total}`);
        $('#ca_pagination_container').show();
        renderCaPagination(pag);
    }

    function caDates(r) {
        if (r.dates && r.dates.length) return r.dates.map(d => moment(d).format('MM/DD/YYYY')).join(', ');
        return '—';
    }

    function renderCaPagination(p) {
        const $ul = $('#ca_pagination_links').empty();
        const c = p.current_page, last = p.last_page;
        $ul.append(`<li class="page-item${c<=1?' disabled':''}"><a class="page-link" href="#" data-page="${c-1}">Prev</a></li>`);
        for (let i = Math.max(1,c-2); i <= Math.min(last,c+2); i++) {
            $ul.append(`<li class="page-item${i===c?' active':''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
        }
        $ul.append(`<li class="page-item${c>=last?' disabled':''}"><a class="page-link" href="#" data-page="${c+1}">Next</a></li>`);
    }

    // -----------------------------------------------------------------------
    // View / Approval modal
    // -----------------------------------------------------------------------
    async function viewCancellationApproval(id) {
        caCurrentId = id;
        try {
            const res  = await fetch(ROUTE_CA_SHOW.replace(':id', id));
            const data = await res.json();
            renderCaModal(data);
        } catch { toastr.error('Error loading cancellation.', 'Error'); }
    }

    function renderCaModal(data) {
        setCancelStep('#cs_step1', data.approvals.sh);
        setCancelStep('#cs_step2', data.approvals.dh);
        setCancelStep('#cs_step3', data.approvals.hr);

        $('#ca_name').text(data.name);
        $('#ca_reason').text(data.reason);
        $('#ca_dates').html(data.dates.map(d =>
            `<li class="list-group-item py-1">${moment(d.date_applied).format('MM/DD/YYYY')} — ${d.hours} hrs</li>`
        ).join(''));

        const roles = { sh: 'Supervisor', dh: 'Division Head', hr: 'Human Resource' };
        let rhtml = '';
        Object.entries(roles).forEach(([key, label]) => {
            const a = data.approvals[key];
            if (a?.remarks) rhtml += `<div class="mb-2"><strong>${label}:</strong><p class="mb-0">${a.remarks}</p><small class="text-muted">Status: ${a.status}</small></div>`;
        });
        $('#ca_remarks_history').html(rhtml || '<p class="text-muted mb-0">No remarks yet.</p>');
        $('#ca_remarks_input').val('');

        const canAct = data.can_act && caActiveFilter === 'pending';
        $('#ca_approve_btn, #ca_reject_btn').toggle(canAct);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewCancellationApprovalModal')).show();
    }

    function setCancelStep(sel, a) {
        const s = a?.status || 'pending';
        $(sel).removeClass('approved rejected pending').addClass(s);
        $(sel + ' .circle').attr('class', 'circle ' + s);
    }

    // -----------------------------------------------------------------------
    // Approve / Reject
    // -----------------------------------------------------------------------
    async function handleCaAction(status) {
        if (!caCurrentId) return;
        const remarks = $('#ca_remarks_input').val().trim();
        const label   = status === 'Approved' ? 'approve' : 'reject';
        if (!confirm(`Are you sure you want to ${label} this cancellation request?`)) return;

        const $btn = status === 'Approved' ? $('#ca_approve_btn') : $('#ca_reject_btn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        try {
            const res = await fetch(ROUTE_CA_UPDATE_STATUS, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body: JSON.stringify({ id: caCurrentId, status, remarks })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error');
            toastr.success(data.message, 'Success');
            bootstrap.Modal.getInstance(document.getElementById('viewCancellationApprovalModal')).hide();
            caPage = 1;
            getCancellationsForApproval();
        } catch (e) {
            toastr.error(e.message, 'Error');
        } finally {
            $btn.prop('disabled', false).html(status === 'Approved'
                ? '<i class="bi bi-check-circle"></i> Approve'
                : '<i class="bi bi-x-circle"></i> Reject');
        }
    }

    function caBadge(s) {
        if (s === 'Approved') return '<span class="badge bg-success">APPROVED</span>';
        if (s === 'Rejected') return '<span class="badge bg-danger">REJECTED</span>';
        return '<span class="badge bg-secondary">PENDING</span>';
    }
</script>
