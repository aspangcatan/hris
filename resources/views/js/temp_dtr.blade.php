@include('js.personnel')

<script>

    let page = 0;
    let employee_id = 0;
    $(document).ready(function () {
        $("#btn_search").click(function () {
            employee_id = 0;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
        });

        $("#btn_next").click(function () {
            page++;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_cancel").click(function () {
            $(this).addClass("d-none");
            $("#btn_save").text("SAVE");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
        });

        getEmployee({fname: "", lname: "", page: 0});
    });

    function editEmployee(id) {
        employee_id = id;
        $("#btn_save").text("SAVE");
        $("#btn_cancel").addClass("d-none");
        $("#dtr_modal").modal("show");
        $("#month").val("1");
        $("#year").val("2023");
        $("#absent").val("0");
        $("#late").val("0");
        $("#filter_year").val("");
        $("#dtr_list").empty();
    }

    async function store() {
        const month = $("#month").val();
        const year = $("#year").val();
        const absent = $("#absent").val();
        const late = $("#late").val();

        const params = {
            user_id: employee_id,
            month: month,
            year: year,
            absent: absent,
            late: late
        }

        const response = await fetch('{{ route('storeDtr') }}', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(params)
        });

        try {
            const data = await response.json();
            if (response.ok) {
                toastr.success(data.message, "Information");
                $("#btn_cancel").addClass("d-none");
                $("#btn_save").text("SAVE");
                getDTRLogs();
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (error) {
            console.error('Error parsing JSON response', error);
            toastr.error('Something went wrong, please try again');
        }
    }

    function editDtr(id) {
        $("#btn_save").text("UPDATE");
        $("#btn_cancel").removeClass("d-none");

        const month = $("#dtr_" + id + " td:eq(0)").attr('data-value');
        const year = $("#filter_year").val();
        const absent = $("#dtr_" + id + " td:eq(1)").text();
        const late = $("#dtr_" + id + " td:eq(2)").text();
        $("#month").val(month);
        $("#year").val(year);
        $("#absent").val(absent);
        $("#late").val(late);
    }

    async function deleteDtr(id) {
        if (confirm("Are you sure you want to delete this?")) {
            const response = await fetch('{{ route('deleteDtr') }}', {
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
                    getDTRLogs();
                } else {
                    toastr.error(data.error || 'Something went wrong, please try again');
                }
            } catch (err) {
                toastr.error('Something went wrong, please try again');
            }
        }
    }

    async function getDTRLogs() {
        const year = $("#filter_year").val();
        const response = await fetch('{{ route('getDTRLogs') }}?user_id=' + employee_id +
            "&year=" + year, {
            method: "GET"
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#dtr_list").empty();
                if (data.length < 1) {
                    $("#dtr_list").append("<tr><td colspan='5'>No record</td></tr>");
                    return;
                }

                data.forEach(it => {
                    const tr = `
                        <tr id="dtr_` + it.id + `">
                            <td data-value="` + it.month + `">` + moment().month(it.month - 1).format("MMMM") + `</td>
                            <td>` + it.absent + `</td>
                            <td>` + it.late + `</td>
                            <td>
                                <button class="btn btn-sm btn-transparent" onclick="editDtr(` + it.id + `)">
                                    <i class="bi bi-pencil-fill text-success"></i>
                                </button>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-transparent" onclick="deleteDtr(` + it.id + `)">
                                    <i class="bi bi-trash-fill text-danger"></i>
                                </button>
                            </td>
                        </tr>`;
                    $("#dtr_list").append(tr);
                });
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (error) {
            toastr.error('Something went wrong, please try again');
        }
    }
</script>
