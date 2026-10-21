@if(permission_check('reorientation.check'))
<script>
    const ROUTE_REO_EMPLOYEE_MODULES    = "{{ route('reorientation.employee_modules') }}";
    const ROUTE_REO_EMPLOYEE_SUBMISSION = "{{ route('reorientation.employee_submission') }}";
    const ROUTE_REO_CHECK               = "{{ route('reorientation.check') }}";

    const REO_CHECK_STATUS = {
        not_started: {label: 'Not started',      cls: 'bg-secondary'},
        submitted:   {label: 'For checking',     cls: 'bg-warning text-dark'},
        passed:      {label: 'Passed',           cls: 'bg-success'},
        failed:      {label: 'Failed',           cls: 'bg-danger'},
    };

    let reoCheckSubmissionId = null;
    let reoCheckModuleNo = null;

    function reoCheckEscape(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function reoCheckDate(value) {
        return value ? moment(value).format('MM/DD/YYYY h:mm A') : '—';
    }

    $(function () {
        $('#reorientation_list').on('click', '.reo-view-btn', function () {
            openReoCheck(parseInt($(this).data('module')));
        });
        $('#btn_reo_check_close').on('click', () => $('#reo_check_panel').addClass('d-none'));
        $('.reo-check-btn').on('click', function () { submitReoCheck($(this).data('status')); });
    });

    async function getReorientation(userId) {
        $('#reo_check_panel').addClass('d-none');
        $('#reorientation_list').html("<tr><td colspan='5' class='text-center text-muted py-4'>Loading re-orientation...</td></tr>");
        try {
            const res = await fetch(ROUTE_REO_EMPLOYEE_MODULES + '?user_id=' + userId);
            const modules = await res.json();
            if (!res.ok) throw new Error(modules.error);
            if (userId !== employee_id) return;

            let html = '';
            modules.forEach(m => {
                const s = REO_CHECK_STATUS[m.status] || REO_CHECK_STATUS.not_started;
                html += `<tr>
                    <td><span class="leave-credit-title">Module ${m.module_no}</span><br><span class="text-muted">${reoCheckEscape(m.title)}</span></td>
                    <td><span class="badge ${s.cls}">${s.label}</span>${m.attempt_no > 1 ? `<br><span class="small text-muted">Attempt ${m.attempt_no}</span>` : ''}</td>
                    <td>${reoCheckDate(m.submitted_at)}</td>
                    <td>${m.checked_by ? reoCheckEscape(m.checked_by) + '<br><span class="small text-muted">' + reoCheckDate(m.checked_at) + '</span>' : '—'}</td>
                    <td>${m.submission_id
                        ? `<button type="button" class="btn btn-sm btn-outline-primary reo-view-btn" data-module="${m.module_no}"><i class="bi bi-eye"></i> ${m.status === 'submitted' ? 'Check' : 'View'}</button>`
                        : '<span class="text-muted small">No submission</span>'}</td>
                </tr>`;
            });
            $('#reorientation_list').html(html);
        } catch (e) {
            $('#reorientation_list').html("<tr><td colspan='5' class='leave-credit-empty py-4'>Could not load re-orientation records.</td></tr>");
        }
    }

    async function openReoCheck(moduleNo) {
        try {
            const res = await fetch(ROUTE_REO_EMPLOYEE_SUBMISSION + '?user_id=' + employee_id + '&module_no=' + moduleNo);
            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.error || 'Something went wrong, please try again');
                return;
            }

            reoCheckSubmissionId = data.submission_id;
            reoCheckModuleNo = moduleNo;
            const s = REO_CHECK_STATUS[data.status] || REO_CHECK_STATUS.not_started;
            $('#reo_check_title').html(`Module ${data.module_no}: ${reoCheckEscape(data.title)} <span class="badge ${s.cls} ms-1">${s.label}</span>`);

            let html = '';
            data.items.forEach((it, i) => {
                html += `<div class="border rounded-3 p-3 mb-2 bg-light">
                    <div class="fw-semibold mb-2" style="white-space:pre-wrap">${i + 1}. ${reoCheckEscape(it.question)}</div>
                    <div class="bg-white border rounded-3 p-2" style="white-space:pre-wrap">${reoCheckEscape(it.answer)}</div>
                </div>`;
            });
            $('#reo_check_answers').html(html);
            $('#reo_check_remarks').val(data.remarks || '');
            $('#reo_check_panel').removeClass('d-none');
            document.getElementById('reo_check_panel').scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (e) {
            toastr.error('Something went wrong, please try again');
        }
    }

    async function submitReoCheck(status) {
        const remarks = $('#reo_check_remarks').val().trim();
        if (status === 'failed' && !remarks) {
            toastr.warning('Please add remarks so the employee knows what to improve.');
            $('#reo_check_remarks').focus();
            return;
        }
        if (!confirm(`Mark this post test as ${status.toUpperCase()}?`)) return;

        const $btns = $('.reo-check-btn').prop('disabled', true);
        try {
            const res = await fetch(ROUTE_REO_CHECK, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                body: JSON.stringify({submission_id: reoCheckSubmissionId, status: status, remarks: remarks}),
            });
            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.error || 'Something went wrong, please try again');
                return;
            }
            toastr.success(data.message);
            await getReorientation(employee_id);
            if ($('#filter_reo_pending').is(':checked')) {
                getEmployee({
                    fname: $('#search_fname').val().trim(),
                    lname: $('#search_lname').val().trim(),
                    page: 1,
                    no_employee_no: $('#filter_no_employee_no').is(':checked') ? 1 : 0,
                });
            }
        } catch (e) {
            toastr.error('Something went wrong, please try again');
        } finally {
            $btns.prop('disabled', false);
        }
    }
</script>
@endif
