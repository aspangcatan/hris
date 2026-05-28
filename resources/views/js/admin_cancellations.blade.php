<script>
    const ROUTE_ADM_ALL_CANCELLATIONS = "{{ route('getAllCancellationsAdmin') }}";
    const ROUTE_ADM_SHOW_CANCELLATION = "{{ route('leave-cancellations.show', ':id') }}";

    let admPage = 1, admLastPage = 1;

    $(function () {
        getAdminCancellations();

        let admSearchTimer;
        $('#adm_search_employee').on('input', function () {
            clearTimeout(admSearchTimer);
            admSearchTimer = setTimeout(() => { admPage = 1; getAdminCancellations(); }, 400);
        });

        $('body').on('click', '#adm_pagination_links .page-link[data-page]', function (e) {
            e.preventDefault();
            const p = parseInt($(this).data('page'));
            if (!p || p === admPage || p < 1 || p > admLastPage) return;
            admPage = p;
            getAdminCancellations();
        });
    });

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------
    async function getAdminCancellations() {
        $('#adm_list').html('<tr><td colspan="6">Loading...</td></tr>');
        const search = $('#adm_search_employee').val().trim();
        try {
            const res  = await fetch(`${ROUTE_ADM_ALL_CANCELLATIONS}?page=${admPage}&search_employee=${encodeURIComponent(search)}`);
            const json = await res.json();
            renderAdmList(json.data, json.pagination);
        } catch {
            $('#adm_list').html('<tr><td colspan="6">Error loading data.</td></tr>');
        }
    }

    function renderAdmList(data, pag) {
        const $body = $('#adm_list').empty();
        if (!data.length) {
            $body.html('<tr><td colspan="6" class="text-muted">No records found.</td></tr>');
            $('#adm_pagination_container').hide(); return;
        }

        data.forEach(r => {
            const name  = [r.fname, r.mname, r.lname].filter(Boolean).join(' ');
            const dates = r.dates && r.dates.length
                ? r.dates.map(d => moment(d).format('MM/DD/YYYY')).join(', ')
                : '—';
            const badge = admBadge(r.status);
            $body.append(`
                <tr>
                    <td>${name}</td>
                    <td><small>${dates}</small></td>
                    <td>${r.reason}</td>
                    <td>${moment(r.created_at).format('MM/DD/YYYY')}</td>
                    <td>${badge}</td>
                    <td>
                        <button class="btn btn-sm" onclick="viewAdminCancellation(${r.id})">
                            <i class="bi bi-eye-fill text-success"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        admLastPage = pag.last_page;
        const from = (pag.current_page - 1) * pag.per_page + 1;
        $('#adm_page_count').text(`${from} – ${from + data.length - 1} of ${pag.total}`);
        $('#adm_pagination_container').show();
        renderAdmPagination(pag);
    }

    function renderAdmPagination(p) {
        const $ul = $('#adm_pagination_links').empty();
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
    async function viewAdminCancellation(id) {
        try {
            const res  = await fetch(ROUTE_ADM_SHOW_CANCELLATION.replace(':id', id));
            const data = await res.json();
            renderAdmModal(data);
        } catch { toastr.error('Error loading cancellation.', 'Error'); }
    }

    function renderAdmModal(data) {
        setAdmStep('#cs_step1', data.approvals.sh);
        setAdmStep('#cs_step2', data.approvals.dh);
        setAdmStep('#cs_step3', data.approvals.hr);

        $('#adm_name').text(data.name);
        $('#adm_reason').text(data.reason);
        $('#adm_dates').html(data.dates.map(d =>
            `<li class="list-group-item py-1">${moment(d.date_applied).format('MM/DD/YYYY')} — ${d.hours} hrs</li>`
        ).join(''));

        const roles = { sh: 'Supervisor', dh: 'Division Head', hr: 'Human Resource' };
        let rhtml = '';
        Object.entries(roles).forEach(([key, label]) => {
            const a = data.approvals[key];
            if (a?.remarks) rhtml += `<div class="mb-2"><strong>${label}:</strong><p class="mb-0">${a.remarks}</p><small class="text-muted">Status: ${a.status}</small></div>`;
        });
        $('#adm_remarks').html(rhtml || '<p class="text-muted mb-0">No remarks yet.</p>');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewAdminCancellationModal')).show();
    }

    function setAdmStep(sel, a) {
        const s = a?.status || 'pending';
        $(sel).removeClass('approved rejected pending').addClass(s);
        $(sel + ' .circle').attr('class', 'circle ' + s);
    }

    function admBadge(s) {
        if (s === 'Approved') return '<span class="badge bg-success">APPROVED</span>';
        if (s === 'Rejected') return '<span class="badge bg-danger">REJECTED</span>';
        return '<span class="badge bg-secondary">PENDING</span>';
    }
</script>
