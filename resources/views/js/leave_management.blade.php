<script>
    let page = 1;
    let leave_types_last_page = 1;
    // Leave types on the current page, by id (fills the modal when editing).
    let leaveTypesById = {};

    // Columns of the list, in order, and how each value is shown (editing uses the modal).
    const LEAVE_COLUMNS = [
        {key: 'description', type: 'text'},
        {key: 'code', type: 'text'},
        {key: 'status', type: 'select', options: [['active', 'Active'], ['inactive', 'Inactive']]},
        {key: 'monthly_earned', type: 'select', options: [['1', 'Yes'], ['0', 'No']]},
        {key: 'monetizable', type: 'select', options: [['0', 'No'], ['1', 'Yes']]},
        {key: 'days_prior', type: 'number', step: '1'},
        {key: 'commutation', type: 'select', options: [['0', 'No'], ['1', 'Yes']]},
        {key: 'default_credit', type: 'number', step: '0.001', min: '0', nullable: true},
        {key: 'min_monetize_days', type: 'number', step: '1', min: '0', nullable: true},
        {key: 'min_retained_days', type: 'number', step: '1', min: '0', nullable: true},
        {key: 'deduction_unit', type: 'select', options: [['hours', 'Hours'], ['days', 'Days']]},
    ];

    $(document).ready(function () {
        $("#btn_search_employee").click(function () {
            page = 1;
            getLeaveTypes();
        });

        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page) return;
            if (targetPage < 1 || targetPage > leave_types_last_page) return;
            page = targetPage;
            getLeaveTypes();
        });

        $("#btn_add_leave").click(function () {
            openLeaveTypeModal(null);
        });

        getLeaveTypes();
    });

    // One modal for both: null = add a new leave type, an item = edit that leave type.
    function openLeaveTypeModal(item) {
        const val = (v, fallback) => (v === undefined || v === null || v === '') ? fallback : String(v);

        $("#leave_type_id").val(item ? item.id : "");
        $("#leave_type_modal_title").text(item ? "Edit Leave Type" : "Add Leave Type");
        $("#description").val(item ? item.description : "");
        $("#code").val(item ? item.code : "");
        $("#leave_status").val(val(item?.status, "active"));
        $("#monthly_earned").val(val(item?.monthly_earned, item ? "0" : "1"));
        $("#deduct_tardiness").val(val(item?.deduct_tardiness, "0"));
        $("#deduction_unit").val(item?.deduction_unit === "days" ? "days" : "hours");
        $("#monetizable").val(val(item?.monetizable, "0"));
        $("#days_prior").val(val(item?.days_prior, "0"));
        $("#commutation").val(val(item?.commutation, "0"));
        $("#default_credit").val(item ? val(item.default_credit, "") : "");
        $("#min_monetize_days").val(item ? val(item.min_monetize_days, "") : "10");
        $("#min_retained_days").val(item ? val(item.min_retained_days, "") : "5");
        $("#leave_type_modal").modal("show");
    }

    function editLeave(id) {
        const item = leaveTypesById[id];
        if (item) openLeaveTypeModal(item);
    }

    function escapeLeaveHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    // ---------------------------------------------------------------- Add / edit (modal)
    async function store() {
        const id = parseInt($("#leave_type_id").val(), 10) || null;
        const params = {
            id: id, // set when editing: the server updates that row instead of adding one
            description: $("#description").val(),
            code: $("#code").val(),
            status: $("#leave_status").val(),
            monthly_earned: $("#monthly_earned").val(),
            deduct_tardiness: $("#deduct_tardiness").val(),
            deduction_unit: $("#deduction_unit").val(),
            monetizable: $("#monetizable").val(),
            days_prior: $("#days_prior").val(),
            commutation: $("#commutation").val(),
            default_credit: $("#default_credit").val() || null,
            min_monetize_days: $("#min_monetize_days").val() === "" ? null : $("#min_monetize_days").val(),
            min_retained_days: $("#min_retained_days").val() === "" ? null : $("#min_retained_days").val()
        };

        const result = await saveLeaveType(params);
        if (result.ok) {
            $("#leave_type_modal").modal("hide");
            toastr.success(result.message, "Information");
            getLeaveTypes();
        } else {
            toastr.error(result.message);
        }
    }

    async function saveLeaveType(params) {
        try {
            const response = await fetch('{{ route('insertUpdateLeaveType') }}', {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            });
            const data = await response.json();
            return response.ok
                ? {ok: true, message: data.message || 'Saved'}
                : {ok: false, message: data.error || 'Something went wrong, please try again'};
        } catch (error) {
            return {ok: false, message: 'Something went wrong, please try again'};
        }
    }

    // ---------------------------------------------------------------- List rows
    function displayValue(col, item) {
        const raw = item[col.key];
        if (col.type === 'select') {
            const fallback = col.options[0][0];
            const value = (raw === undefined || raw === null || raw === '') ? fallback : String(raw);
            const option = col.options.find(o => o[0] === value);
            return escapeLeaveHtml(option ? option[1] : value);
        }
        if (col.nullable && (raw === undefined || raw === null || raw === '')) {
            return '—';
        }
        return escapeLeaveHtml(raw ?? '');
    }

    function renderLeaveRow(item) {
        const cells = LEAVE_COLUMNS.map(col => `<td>${displayValue(col, item)}</td>`).join('');
        return `
            <tr id="leave_${item.id}">
                ${cells}
                <td>
                    <button class="btn btn-sm btn-transparent" onclick="editLeave(${item.id})" title="Edit">
                        <i class="bi bi-pencil-fill text-success"></i>
                    </button>
                </td>
                <td>
                    <button class="btn btn-sm btn-transparent" onclick="deleteLeave(${item.id})" title="Delete">
                        <i class="bi bi-trash-fill text-danger"></i>
                    </button>
                </td>
            </tr>`;
    }

    // ---------------------------------------------------------------- Delete
    async function deleteLeave(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            const response = await fetch("{{ route('deleteLeaveType') }}", {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            });

            try {
                const data = await response.json();
                if (response.ok) {
                    toastr.success(data.message, "Information");
                    getLeaveTypes();
                } else {
                    toastr.error(data.error || 'Something went wrong, please try again');
                }
            } catch (error) {
                toastr.error(error, "Ooops");
            }
        }
    }

    // ---------------------------------------------------------------- List
    async function getLeaveTypes() {
        const response = await fetch('{{ route('getLeaveTypes') }}?page=' + page, {
            method: "GET"
        });

        try {
            const payload = await response.json();
            if (!response.ok) {
                toastr.error(payload.error || "Something went wrong, please try again");
                return;
            }

            $("#leave_list").empty();
            $("#pagination_links").empty();
            $("#page_indicator").text("");
            leaveTypesById = {};

            const data = payload.results || [];
            const pagination = payload.pagination || null;

            if (!data.length) {
                $("#leave_list").append(`<tr><td colspan="${LEAVE_COLUMNS.length + 2}">No record found</td></tr>`);
                $("#pagination_container").addClass("d-none");
                return;
            }

            $("#pagination_container").removeClass("d-none");
            if (pagination) {
                page = pagination.current_page;
                leave_types_last_page = pagination.last_page;
                $("#page_items_count").text(pagination.from + " - " + pagination.to + " of " + pagination.total);
                $("#page_indicator").text("Page " + pagination.current_page + " of " + pagination.last_page);
                renderLeaveTypesPaginationLinks(pagination);
            }

            data.forEach(it => {
                leaveTypesById[it.id] = it;
                $("#leave_list").append(renderLeaveRow(it));
            });
        } catch (error) {
            toastr.error(error, "Ooops");
        }
    }

    function renderLeaveTypesPaginationLinks(pagination) {
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
</script>
