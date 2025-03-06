<script>
    let page = 0;
    let leave_id = 0;
    $(document).ready(function () {
        $("#btn_next").click(function () {
            page++;
            getLeaveTypes();
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            getLeaveTypes();
        });

        $("#btn_search_employee").click(function () {
            page = 0;
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
        const response = await fetch('{{ route('getLeaveTypes') }}', {
            method: "GET"
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#leave_list").empty();
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
</script>
