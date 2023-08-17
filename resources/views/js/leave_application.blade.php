<script>
    let page = 0;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
    const LEAVE_TYPES = ["Vacation Leave", "Mandatory/Forced Leave", "Sick Leave", "Maternity Leave", "Paternity Leave", "Special Privilege",
        "Solo Parent Leave", "Study Leave", "10-Day VAWC Leave", "Rehabilitation Privilege", "Special Leave Benefits for Women",
        "Special Emergency (Calamity) Leave", "Adoption Leave", "Others"];

    $(document).ready(function () {
        $('.datepicker').datepicker({
            multidate: true
        });

        $("#btn_file_leave").click(function () {
            file_leave_modal.toggle();
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

        $("body").on("change", "input[name='leave_type']", function () {
            $("input:not(input[name='leave_type']):not('#dates_applied'):not(input[name='commutation']):not(input[name='other_purpose'])").prop("disabled", true);
            $("input[type='radio']:not(input[name='leave_type'])").prop("checked", false);
            $("input[type='text']").val("");
            switch ($(this).next().text().trim()) {
                case "Vacation Leave":
                case "Special Leave":
                    $("input[name='vacation_leave']").prop("disabled", false);
                    $("#within_philippines").val("")
                    $("#within_philippines").prop("disabled", false)
                    $("#abroad").val("")
                    $("#abroad").prop("disabled", false)
                    break;
                case "Sick Leave":
                    $("input[name='sick_leave']").prop("disabled", false);
                    $("#in_hospital").val("")
                    $("#in_hospital").prop("disabled", false)
                    $("#out_hospital").val("")
                    $("#out_hospital").prop("disabled", false)
                    break;
                case "Special Leave Benefits for Women":
                    $("#special_leave").val("")
                    $("#special_leave").prop("disabled", false)
                    break;
                case "Study Leave":
                    $("input[name='study_leave']").prop("disabled", false);
                    break;
                default:
                    //When Others are checked on type of Leave
                    $(this).next().children().eq(1).prop("disabled", false);
                    break;
            }
        });

        $("input[name='vacation_leave']").change(function () {
            if ($(this).next().text().trim() == "Within the Philippines") {
                $("#within_philippines").prop("disabled", false);
                $("#abroad").prop("disabled", true);
                $("#abroad").val("");
            } else {
                $("#abroad").prop("disabled", false);
                $("#within_philippines").prop("disabled", true);
                $("#within_philippines").val("");
            }
        });

        $("input[name='sick_leave']").change(function () {
            if ($(this).next().text().trim() == "In Hospital (Specify Illness)") {
                $("#in_hospital").prop("disabled", false);
                $("#out_hospital").prop("disabled", true);
                $("#out_hospital").val("");
            } else {
                $("#out_hospital").prop("disabled", false);
                $("#in_hospital").prop("disabled", true);
                $("#in_hospital").val("");
            }
        });

        $("input[name='other_purpose']").change(function () {
            $("input[name='vacation_leave']").prop("checked", false);
            $("input[name='sick_leave']").prop("checked", false);
            $("input[name='study_leave']").prop("checked", false);
        });

        $("#btn_save_leave").click(function () {
            let leave_type = $("input[name='leave_type']:checked").next().text().trim();
            let leave_details = "";
            let leave_extra = "";
            switch (leave_type) {
                case "Vacation Leave":
                case "Special Leave":
                    leave_details = $("input[name='vacation_leave']:checked").next().text().trim();
                    leave_extra = $("input[name='vacation_leave']:checked").next().find("input").val();
                    break;
                case "Sick Leave":
                    leave_details = $("input[name='sick_leave']:checked").next().text().trim();
                    leave_extra = $("input[name='sick_leave']:checked").next().find("input").val();
                    break;
                case "Special Leave Benefits for Women":
                    leave_details = "In case of Special Leave Benefits for Women";
                    leave_extra = $("#special_leave").val().trim();
                    break;
                case "Study Leave":
                    leave_details = $("input[name='study_leave']:checked").next().text().trim();
                    break;
                case "Paternity Leave":
                case "Solo Parent Leave":
                case "10-Day VAWC Leave":
                case "Adoption Leave":
                case "Mandatory/Forced Leave":
                case "Maternity Leave":
                case "Special Privilege":
                case "Rehabilitation Privilege":
                case "Special Emergency (Calamity) Leave":
                    leave_details = $("input[name='other_purpose']:checked").next().text().trim();
                    break;
                default:
                    //When Others are checked on type of Leave
                    leave_type = $("#leave_others").val().trim();
                    leave_details = $("input[name='other_purpose']:checked").next().text().trim();
                    break;
            }

            const dates_applied = $("#dates_applied").val();
            const commutation = $("input[name='commutation']:checked").next().text();
            const params = {
                leave_type: leave_type,
                detail: leave_details,
                extra: leave_extra,
                date_applied: dates_applied,
                commutation: commutation
            }

            $(this).html(`<button class="btn btn-primary" type="button" disabled>
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Loading...
            </button>`);
            $(this).prop("disabled", true);

            fetch('{{ route('insertLeaveApplications') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    toastr.success(data.message, 'Information');
                    clearForm();
                    getLeaves();
                }).catch(err => {
                console.log(err);
            }).finally(() => {
                $("#btn_save_leave").html("SUBMIT");
                $("#btn_save_leave").prop("disabled", false);
            });
        });

        init_leave();
        getLeaves();
    });

    function init_leave() {
        LEAVE_TYPES.forEach(it => {
            let div = ` <div class="col-md-6">
                            <div class="form-check">
                                 <input class="form-check-input" type="radio" name="leave_type">
                                 <div class="d-flex align-items-center">
                                    <label class="form-check-label">` + it + `</label>`;
            if (it == "Others") {
                div += `<input class="ms-2 underline w-100" type="text" id="leave_others" />`;
            }
            div += `</div></div></div>`;
            $("#leave_list").append(div);
        })
    }

    function getLeaves() {
        $("#btn_next").prop("disabled", true);
        $("#btn_prev").prop("disabled", true);
        $("#leave_history_list").empty();
        $("#leave_history_list").append("<tr><td colspan='6'>Loading data, please wait...</td></tr>");
        fetch('{{ route('getLeaveApplications') }}?page=' + page, {
                method: "GET"
            }
        )
            .then(res => res.json())
            .then(data => {
                $("#btn_next").prop("disabled", false);
                $("#btn_prev").prop("disabled", false);
                $("#pagination_container").addClass("d-none")
                $("#leave_history_list").empty();
                $("#btn_next").addClass("d-none");
                if (data.length < 1) {
                    $("#leave_history_list").append("<tr><td colspan='6'>No record found</td></tr>");
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
                    const extra = (it.extra) ? it.extra : "";
                    const status = (it.status == "APPROVED") ? "<span class='badge bg-success'>APPROVED</span>" : "<span class='badge bg-secondary'>FOR APPROVAL</span>";
                    const tr = `
                    <tr id='leave_id_` + it.id + `'>
                        <td>` + it.leave_type + `</td>
                        <td>` + it.detail + `</td>
                        <td>` + extra + `</td>
                        <td>` + it.date_applied + `</td>
                        <td>` + status + `</td>
                        <td class="d-none">` + it.date_section_approved + `</td>
                        <td class="d-none">` + it.date_division_approved + `</td>
                        <td class="d-none">` + it.date_hr_approved + `</td>
                        <td>
                            <button type='button' class="btn btn-sm" style="background: transparent" onClick="viewStatus(` + it.id + `)">
                                <i class="bi bi-eye-fill text-success"></i>
                            </button>
                        </td>
                    <tr>`;
                    $("#leave_history_list").append(tr);
                }
            }).catch(err => {
            console.log(err);
        })
    }

    function viewStatus(id) {
        $("#leave_frame").attr("src", "https://dohcsmc.site/hris/print_leave?id=" + id);
        const section = {
            status: ($("#leave_id_" + id + " td:eq(5)").text() == "null") ? "<span class='badge bg-secondary'>FOR APPROVAL</span>" : "<span class='badge bg-success'>APPROVED</span>",
            date_approved: ($("#leave_id_" + id + " td:eq(5)").text() == "null") ? "" : moment($("#leave_id_" + id + " td:eq(5)").text()).format("MM/DD/YYYY hh:mm:ss A")
        };

        const division = {
            status: ($("#leave_id_" + id + " td:eq(6)").text() == "null") ? "<span class='badge bg-secondary'>FOR APPROVAL</span>" : "<span class='badge bg-success'>APPROVED</span>",
            date_approved: ($("#leave_id_" + id + " td:eq(6)").text() == "null") ? "" : moment($("#leave_id_" + id + " td:eq(6)").text()).format("MM/DD/YYYY hh:mm:ss A")
        };

        const hr = {
            status: ($("#leave_id_" + id + " td:eq(7)").text() == "null") ? "<span class='badge bg-secondary'>FOR APPROVAL</span>" : "<span class='badge bg-success'>APPROVED</span>",
            date_approved: ($("#leave_id_" + id + " td:eq(7)").text() == "null") ? "" : moment($("#leave_id_" + id + " td:eq(7)").text()).format("MM/DD/YYYY hh:mm:ss A")
        };

        $("#leave_status_logs tr:eq(0) td:eq(1)").html(section.status);
        $("#leave_status_logs tr:eq(0) td:eq(2)").text(section.date_approved);

        $("#leave_status_logs tr:eq(1) td:eq(1)").html(division.status);
        $("#leave_status_logs tr:eq(1) td:eq(2)").text(division.date_approved);

        $("#leave_status_logs tr:eq(2) td:eq(1)").html(hr.status);
        $("#leave_status_logs tr:eq(2) td:eq(2)").text(hr.date_approved);
        leave_modal.toggle();
    }

    function clearForm() {
        $("#leave_form input").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
    }
</script>
