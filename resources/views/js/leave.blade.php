<script>
    let page = 1;
    let leave_last_page = 1;
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
            page = 1;
            getLeaves();
        });
        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page) return;
            if (targetPage < 1 || targetPage > leave_last_page) return;
            page = targetPage;
            getLeaves();
        });

        $("body").on("change", "input[name='leave_type']", function () {
            $("input:not(input[name='leave_type']):not('#dates_applied'):not(input[name='commutation'])").prop("disabled", true);
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
                    $("input[name='other_purpose']").prop("disabled", false);
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
                    $("input[name='study_leave']").prop("disabled", false);
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
                leave_details: leave_details,
                leave_extra: leave_extra,
                dates_applied: dates_applied,
                commutation: commutation,
                remarks: ""
            }

            console.log(params);

            $(this).html(`<button class="btn btn-primary" type="button" disabled>
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Loading...
            </button>`);
            $(this).prop("disabled", true);

            fetch('{{ route('insertLeaves') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    console.log(err);
                    console.log("hasdasdasd")
                    if (data.code == 500) {
                        toastr.error(data.message, 'Ooops');
                        return;
                    }

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
        $("#leave_history_list").empty();
        $("#leave_history_list").append("<tr><td colspan='6'>Loading data, please wait...</td></tr>");
        $("#pagination_links").empty();
        $("#page_indicator").text("");
        fetch('{{ route('getLeaves') }}?page=' + page, {
                method: "GET"
            }
        )
            .then(res => res.json())
            .then(data => {
                $("#pagination_container").addClass("d-none");
                $("#leave_history_list").empty();
                $("#pagination_links").empty();
                $("#page_indicator").text("");
                if (data.results.length < 1) {
                    $("#leave_history_list").append("<tr><td colspan='6'>No record found</td></tr>");
                    return;
                }

                const pagination = data.pagination || null;
                if (pagination && pagination.current_page) {
                    page = pagination.current_page;
                    leave_last_page = pagination.last_page;
                }

                $("#pagination_container").removeClass("d-none");
                let max_visible = data.results.length;
                if (pagination) {
                    $("#page_items_count").text(pagination.from + " - " + pagination.to + " of " + pagination.total);
                    $("#page_indicator").text("Page " + pagination.current_page + " of " + pagination.last_page);
                    renderLeavePaginationLinks(pagination);
                }
                for (let i = 0; i < max_visible; i++) {
                    const it = data.results[i];
                    const tr = `
                    <tr id='employee_` + it.user_id + `'>
                        <td>` + it.leave_type + `</td>
                        <td>` + it.leave_details + `</td>
                        <td>` + it.dates_applied + `</td>
                        <td><span class="badge bg-secondary">PENDING</span></td>
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

    function renderLeavePaginationLinks(pagination) {
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

    function viewStatus(id) {
        $("#status_leave_type").text("");
        $("#status_leave_details").text("");
        $("#status_leave_extra").text("");
        $("#status_date_applied").text("");
        $("#status_commutation").text("");
        fetch('{{ route('getLeaveStatus') }}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                $("#status_leave_type").text(data.results.leave_type);
                $("#status_leave_details").text(data.results.leave_details);
                $("#status_leave_extra").text(data.results.leave_extra);
                $("#status_date_applied").text(data.results.dates_applied);
                $("#status_commutation").text(data.results.commutation);
            });

        $("#leave_status_list").append(`<tr><td colspan="4"><div class="d-flex justify-content-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div></td></tr>`);
        setTimeout(function () {
            const data = [{
                origin: "Asnaui Pangcatan",
                remarks: "",
                destination: "",
                date_forwarded: ""
            }];

            $("#leave_status_list").empty();
            data.forEach(it => {
                let tr = "<tr>";
                tr += "<td>" + it.origin + "</td>";
                tr += "<td>" + it.remarks + "</td>";
                tr += "<td>" + it.destination + "</td>";
                tr += "<td>" + it.date_forwarded + "</td>";
                tr += "</tr>";

                $("#leave_status_list").append(tr);
            });
        }, 3000);
        leave_modal.toggle();
    }

    function clearForm() {
        $("#leave_form input").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
    }
</script>
