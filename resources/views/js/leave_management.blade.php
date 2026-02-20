<script>
    let page = 1;
    let leave_types_last_page = 1;
    let leave_id = 0;
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
            $("#description").val("");
            $("#code").val("");
            $("#monthly_points_earned").val("");
            $("#minute_deduction").val("");
            $("#leave_type_modal").modal("show");
        });

        getLeaveTypes();
    });

    async function store() {
        const description = $("#description").val();
        const code = $("#code").val();
        const monthly_points_earned = $("#monthly_points_earned").val();
        const minute_deduction = $("#minute_deduction").val();
        const day_deduction = $("#day_deduction").val();

        const params = {
            description: description,
            code: code,
            monthly_points_earned: monthly_points_earned,
            minute_deduction: minute_deduction,
            day_deduction: day_deduction
        }

        const response = await fetch('{{ route('insertUpdateLeaveType') }}', {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(params)
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#leave_type_modal").modal("hide");
                toastr.success(data.message, "Information");
                getLeaveTypes();
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (error) {
            toastr.error(error, "Ooops");
        }
    }

    function editLeave(id) {
        const description = $("#leave_" + id + " td:eq(0)").text();
        const code = $("#leave_" + id + " td:eq(1)").text();
        const monthly_points_earned = $("#leave_" + id + " td:eq(2)").text();
        const minute_deduction = $("#leave_" + id + " td:eq(3)").text();
        const day_deduction = $("#leave_" + id + " td:eq(4)").text();


        $("#description").val(description);
        $("#code").val(code);
        $("#monthly_points_earned").val(monthly_points_earned);
        $("#minute_deduction").val(minute_deduction);
        $("#day_deduction").val(day_deduction);
        $("#leave_type_modal").modal("show");
    }

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

    async function getLeaveTypes() {
        const response = await fetch('{{ route('getLeaveTypes') }}?page=' + page, {
            method: "GET"
        });

        try {
            const payload = await response.json();
            if (response.ok) {
                $("#leave_list").empty();
                $("#pagination_links").empty();
                $("#page_indicator").text("");

                const data = payload.results || [];
                const pagination = payload.pagination || null;

                if (!data.length) {
                    $("#leave_list").append("<tr><td colspan='7'>No record found</td></tr>");
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
                    const tr = `
                    <tr id="leave_` + it.id + `">
                        <td>` + it.description + `</td>
                        <td>` + it.code + `</td>
                        <td>` + it.monthly_points_earned + `</td>
                        <td>` + it.minute_deduction + `</td>
                        <td>` + it.day_deduction + `</td>
                        <td>
                            <button class="btn btn-sm btn-transparent" onClick='editLeave(` + it.id + `)'>
                                <i class="bi bi-pencil-fill text-success"></i>
                            </button>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-transparent" onClick='deleteLeave(` + it.id + `)'>
                                <i class="bi bi-trash-fill text-danger"></i>
                            </button>
                        </td>
                    </tr>`;

                    $("#leave_list").append(tr);
                });
            } else {
                toastr.error(data.error || "Something went wrong, please try again");
            }

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
