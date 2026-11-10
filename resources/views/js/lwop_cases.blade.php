<script>
    // Cases on the page, by id (fills the modal when editing).
    let lwopCasesById = {};

    const LWOP_STATUS_TEXT = {lwop: 'LWOP', present: 'PRESENT', absent: 'ABSENT', paid_leave: 'PAID LEAVE'};

    $(document).ready(function () {
        $("#btn_add_lwop_case").click(function () {
            openLwopCaseModal(null);
        });
        $("#lwop_case_friday, #lwop_case_saturday, #lwop_case_sunday, #lwop_case_monday").on("change", showLwopCaseResult);
        getLwopCases();
    });

    function escapeLwopHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    // LWOP days in the Fri-Sat-Sun-Mon stretch for a case (filed LWOP days + weekend days marked LWOP).
    function lwopCaseDays(c) {
        return (c.friday === 'lwop' ? 1 : 0) + (c.saturday ? 1 : 0) + (c.sunday ? 1 : 0) + (c.monday === 'lwop' ? 1 : 0);
    }

    function lwopCaseResultText(c) {
        const days = lwopCaseDays(c);
        const weekend = c.saturday && c.sunday ? 'Sat and Sun are LWOP'
            : (c.saturday ? 'only Sat is LWOP' : (c.sunday ? 'only Sun is LWOP' : 'weekend is present'));
        return `${days} ${days === 1 ? 'day' : 'days'} LWOP (${weekend})`;
    }

    function readLwopCaseForm() {
        return {
            friday: $("#lwop_case_friday").val(),
            saturday: $("#lwop_case_saturday").val() === "1",
            sunday: $("#lwop_case_sunday").val() === "1",
            monday: $("#lwop_case_monday").val(),
        };
    }

    function showLwopCaseResult() {
        $("#lwop_case_result").text("Result: " + lwopCaseResultText(readLwopCaseForm()));
    }

    // One modal for both: null = add a case, an item = edit that case.
    function openLwopCaseModal(item) {
        $("#lwop_case_id").val(item ? item.id : "");
        $("#lwop_case_modal_title").text(item ? "Edit Case" : "Add Case");
        $("#lwop_case_friday").val(item ? item.friday : "lwop");
        $("#lwop_case_saturday").val(item ? (item.saturday ? "1" : "0") : "1");
        $("#lwop_case_sunday").val(item ? (item.sunday ? "1" : "0") : "1");
        $("#lwop_case_monday").val(item ? item.monday : "present");
        $("#lwop_case_remarks").val(item ? (item.remarks || "") : "");
        showLwopCaseResult();
        $("#lwop_case_modal").modal("show");
    }

    function editLwopCase(id) {
        const item = lwopCasesById[id];
        if (item) openLwopCaseModal(item);
    }

    async function lwopCaseRequest(url, method, body) {
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: body ? JSON.stringify(body) : undefined
            });
            const data = await response.json();
            if (response.ok) return {ok: true, data: data, message: data.message || 'Saved'};
            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
            return {ok: false, message: firstError || data.message || 'Something went wrong, please try again'};
        } catch (error) {
            return {ok: false, message: 'Something went wrong, please try again'};
        }
    }

    async function saveLwopCase() {
        const params = Object.assign(readLwopCaseForm(), {
            id: parseInt($("#lwop_case_id").val(), 10) || null,
            remarks: $("#lwop_case_remarks").val() || null,
        });

        const result = await lwopCaseRequest('{{ route('lwop_settings.save') }}', 'POST', params);
        if (result.ok) {
            $("#lwop_case_modal").modal("hide");
            toastr.success(result.message, "Information");
            getLwopCases();
        } else {
            toastr.error(result.message);
        }
    }

    async function deleteLwopCase(id) {
        const item = lwopCasesById[id];
        if (!item || !confirm(`Delete case: FRI ${LWOP_STATUS_TEXT[item.friday]}, MON ${LWOP_STATUS_TEXT[item.monday]}?`)) return;

        const result = await lwopCaseRequest('{{ route('lwop_settings.delete') }}', 'DELETE', {id: id});
        if (result.ok) {
            toastr.success(result.message, "Information");
            getLwopCases();
        } else {
            toastr.error(result.message);
        }
    }

    function lwopCell(isLwop, extraClass) {
        return `<td class="${extraClass || ''} ${isLwop ? 'is-lwop' : 'is-present'}">${isLwop ? 'LWOP' : 'PRESENT'}</td>`;
    }

    function lwopStatusCell(status) {
        const cls = status === 'lwop' ? 'is-lwop' : (status === 'present' ? 'is-present' : '');
        return `<td class="${cls}">${escapeLwopHtml(LWOP_STATUS_TEXT[status] || status)}</td>`;
    }

    async function getLwopCases() {
        const result = await lwopCaseRequest('{{ route('lwop_settings.list') }}', 'GET');
        if (!result.ok) {
            toastr.error(result.message, "Ooops");
            return;
        }

        lwopCasesById = {};
        const $tbody = $("#lwop_case_list").empty();
        const rows = result.data.data || [];
        if (!rows.length) {
            $tbody.append('<tr><td colspan="8" class="text-muted">No cases: every weekend counts as present. Click "Add Case".</td></tr>');
            return;
        }

        rows.forEach((c, i) => {
            lwopCasesById[c.id] = c;
            $tbody.append(`
                <tr>
                    <td>${i + 1}</td>
                    ${lwopStatusCell(c.friday)}
                    ${lwopCell(c.saturday, 'day-off')}
                    ${lwopCell(c.sunday, 'day-off')}
                    ${lwopStatusCell(c.monday)}
                    <td class="small">${escapeLwopHtml(lwopCaseResultText(c))}</td>
                    <td class="small text-start">${escapeLwopHtml(c.remarks || '')}${c.changed_by ? `<div class="text-muted">${escapeLwopHtml(c.changed_by)}</div>` : ''}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" onclick="editLwopCase(${c.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteLwopCase(${c.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`);
        });
    }
</script>
