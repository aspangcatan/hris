<script>
    let page = 0;
    let leave_id = 0;
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
    $(document).ready(function () {
        $('.datepicker').datepicker({
            multidate: true
        });

        $("#btn_search").click(function () {
            employee_id = 0;
            getLeaves();
        });

        $("#btn_next").click(function () {
            page++;
            getLeaves();
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            getLeaves();
        });

        $("#btn_approve_leave").click(function () {
            fetch('{{ route('approveLeave')}}', {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: leave_id})
            })
                .then(res => res.json())
                .then(data => {
                    toastr.success(data.message, "Information");
                    leave_modal.toggle();
                    getLeaves();
                }).catch(err => {
                toastr.error(err, "Ooops");
            });
        });

        getLeaves();
    });

    function getLeaves() {
        $("#btn_next").prop("disabled", true);
        $("#btn_prev").prop("disabled", true);
        $("#leave_history_list").empty();
        $("#leave_history_list").append("<tr><td colspan='6'>Loading data, please wait...</td></tr>");
        fetch('{{ route('getLeaveForApproval') }}?page=' + page, {
                method: "GET"
            }
        )
            .then(res => res.json())
            .then(data => {
                $("#btn_next").prop("disabled", false);
                $("#btn_prev").prop("disabled", false);
                $("#pagination_container").addClass("d-none")
                $("#leave_approval_list").empty();
                $("#btn_next").addClass("d-none");
                if (data.length < 1) {
                    $("#leave_approval_list").append("<tr><td colspan='6'>No record found</td></tr>");
                    return;
                }

                $("#pagination_container").removeClass("d-none")
                let max_visible = data.length;
                if (max_visible == 11) {
                    max_visible = 10;
                    $("#btn_next").removeClass("d-none");
                }

                $("#page_items_count").text(((page * 10) + 1) + " - " + ((page * 10) + max_visible));
                for (let i = 0; i < max_visible; i++) {
                    const it = data[i];
                    console.log(it);
                    const tr = `
                    <tr id='leave_id_` + it.id + `'>
                        <td>` + it.lname + ", " + it.fname + " " + it.mname + `</td>
                        <td>` + it.description + `</td>
                        <td>` + it.date_applied + `</td>
                        <td><span class="badge bg-secondary">` + it.status + `</span></td>
                        <td>
                            <button type='button' class="btn btn-sm" style="background: transparent" onClick="viewStatus(` + it.id + `)">
                                <i class="bi bi-eye-fill text-success"></i>
                            </button>
                        </td>
                    <tr>`;
                    $("#leave_approval_list").append(tr);
                }
            }).catch(err => {
            console.log(err);
        })
    }

    function viewStatus(id) {
        $("#leave_frame").attr("src", "{{ url('/print_leave?id=') }}" + id);
        // Fetch leave logs
        fetch('{{ route('getApplicationLogs') }}?id=' + id, {
                method: "GET"
            }
        )
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error("Error:", data.error);
                    return;
                }

                let section = data.logs.section || {remarks: "-", date_approved: "-"};
                let division = data.logs.division || {remarks: "-", date_approved: "-"};
                let hr = data.logs.hr || {remarks: "-", date_approved: "-"};

                $("#leave_status_logs tr:eq(0) td:eq(1)").html(getBadge(section.remarks));
                $("#leave_status_logs tr:eq(0) td:eq(2)").text(section.date_approved);

                $("#leave_status_logs tr:eq(1) td:eq(1)").html(getBadge(division.remarks));
                $("#leave_status_logs tr:eq(1) td:eq(2)").text(division.date_approved);

                $("#leave_status_logs tr:eq(2) td:eq(1)").html(getBadge(hr.remarks));
                $("#leave_status_logs tr:eq(2) td:eq(2)").text(hr.date_approved);

                leave_modal.toggle();
            })
            .catch(error => console.error("Fetch error:", error));
    }

    function clearForm() {
        $("#leave_form input").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
    }


    function getBadge(remarks) {
        if (remarks === "Approved") {
            return `<span class="badge bg-success px-2 py-1 rounded-pill">Approved</span>`;
        } else if (remarks !== "-") {
            return `<span class="badge bg-secondary px-2 py-1 rounded-pill">${remarks}</span>`;
        } else {
            return `<span class="badge bg-light text-dark px-2 py-1 rounded-pill">-</span>`;
        }
    }
</script>
