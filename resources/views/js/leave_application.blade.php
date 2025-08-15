<script>
    let page = 0;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
    const LEAVE_TYPES = [];
    $(document).ready(function () {
        $('.datepicker').datepicker({
            multidate: true
        });

        $("#btn_file_leave").click(async function () {
            file_leave_modal.toggle();
        });

        $("#btn_reload").click(function () {
            init_leave();
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
            $("input:not(input[name='leave_type']):not('#dates_applied'):not(input[type='date']):not(input[type='number']):not(input[name='commutation']):not(input[name='other_purpose'])").prop("disabled", true);
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
            let is_valid = true;
            const leave_id = $("input[name='leave_type']:checked").val();
            let leave_type = $("input[name='leave_type']:checked").next().text().trim();
            let leave_details = "N/A";
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

            const date_applied_array = [];
            for (let i = 0; i < $("#dates_table_body tr").length; i++) {
                const tr = $("#dates_table_body tr:eq(" + i + ")");
                const date = tr.find("td:eq(0) input").val();
                const hours = tr.find("td:eq(1) input").val();

                date_applied_array.push({
                    date_applied: date,
                    hours: hours
                });
            }

            const commutation = $("input[name='commutation']:checked").next().text();

            if (leave_type == "") {
                is_valid = false;
                toastr.error("Please select leave type", "Required");
            }

            if (leave_details == "") {
                is_valid = false;
                toastr.error("Please provide leave details", "Required");
            }


            //TODO: MAKE ANOTHER TABLE FOR DATE APPLIED AND ADD COLUMN (WHOLE/HALF DAY)

            if (commutation == "") {
                is_valid = false;
                toastr.error("Please specify commutation", "Required")
            }

            if (!is_valid) return;

            const params = {
                leave_id: parseInt(leave_id),
                detail: leave_details,
                extra: leave_extra,
                commutation: commutation,
                dates: date_applied_array
            }

            $(this).html(`<button class="btn btn-primary" type="button" disabled>
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Loading...
            </button>`);

            $(this).prop("disabled", true);
            fetch('{{ route('insertLeaveApplication') }}', {
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
                    // clearForm();
                    // getLeaves();
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

    function addRow() {
        const tableBody = document.getElementById('dates_table_body');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="date" class="form-control" name="leave_dates[]"></td>
            <td><input type="number" class="form-control" name="leave_hours[]" min="1" max="24"></td>
            <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button></td>
        `;
        tableBody.appendChild(newRow);
    }

    function removeRow(button) {
        if ($("#dates_table_body tr").length <= 1) {
            toastr.error("Must specify date for leave", "Information");
            return;
        }

        const row = button.parentElement.parentElement;
        row.remove();
    }

    async function init_leave() {
        await loadCreditBalance();
        $("#leave_list").empty();
        LEAVE_TYPES.forEach(it => {
            let div = ` <div class="col-md-6">
                            <div class="form-check">
                                 <input class="form-check-input" type="radio" name="leave_type" value=` + it.id + `>
                                 <div class="d-flex align-items-center">
                                    <label class="form-check-label">` + it.description + `</label>`;
            // if (it == "Others") {
            //     div += `<input class="ms-2 underline w-100" type="text" id="leave_others" />`;
            // }
            div += `</div></div></div>`;
            $("#leave_list").append(div);
        })
    }

    async function loadCreditBalance() {
        const response = await fetch('{{ route('getCreditBalances') }}', {
            method: "GET"
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#credit_balances").empty();
                $("#legend_list").empty();
                data.forEach(it => {
                    const balanceColorClass = it.balance > 0 ? 'text-success' : (it.balance < 0 ? 'text-danger' : '');
                    const credit_balances = `
                        <div class="col-md-6">
                            <span class="fw-bold">${it.code}:</span>
                            <span class="ms-1 ${balanceColorClass}">${it.balance}</span>
                        </div>`;
                    $("#credit_balances").append(credit_balances);

                    const legends = `
                        <div class="col-md-6">
                            <span class="fw-bold">` + it.code + `:</span>
                            <span class="ms-1">` + it.description + `</span>
                        </div>`;
                    $("#legend_list").append(legends);

                    LEAVE_TYPES.push({
                        id: it.id,
                        description: it.description
                    });
                });
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (error) {
            console.error('Error parsing JSON response', error);
            toastr.error('Something went wrong, please try again');
        }
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
                    const dates_applied_array = Object.values(it.dates_applied).map(date => moment(date.date_applied).format("MM/DD/YYYY"));
                    const extra = (it.extra) ? it.extra : "";
                    const status = (it.status == "APPROVED") ? "<span class='badge bg-success'>APPROVED</span>" : "<span class='badge bg-secondary'>FOR APPROVAL</span>";
                    const tr = `
                    <tr id='leave_id_` + it.id + `'>
                        <td>` + it.description + `</td>
                        <td>` + it.detail + `</td>
                        <td>` + extra + `</td>
                        <td>` + dates_applied_array.join(', ') + `</td>
                        <td>` + status + `</td>
                        <td>
                            <button type='button' class="btn btn-sm" style="background: transparent" onClick="viewApplication(` + it.id + `)">
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

    function viewApplication(id) {
        fetch(`{{ route('leave-applications.show', ':id') }}`.replace(':id', id), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error("Failed to load leave application.");
                return response.json();
            })
            .then(data => {
                // Fill static info
                $('#leave_name').text(data.name);
                $('#leave_type').text(data.type);
                $('#leave_details').val(data.details || '');

                // Build dates table rows
                let dateRows = '';
                data.dates.forEach(d => {
                    dateRows += `
                <tr>
                    <td>${d.date}</td>
                    <td>${d.duration} hours</td>
                    <td>${d.remarks || ''}</td>
                    <td class="text-center align-middle">
                        ${d.status === 'approved'
                        ? '<span class="text-success fs-5">&#10004;</span>'
                        : d.status === 'rejected'
                            ? '<span class="text-danger fs-5">&#10060;</span>'
                            : ''}
                    </td>
                </tr>
            `;
                });
                $('#dates_applied_body').html(dateRows);

                // Show modal
                $('#viewLeaveModal').modal('show');
            })
            .catch(err => {
                console.error(err);
                alert("Error loading leave application.");
            });
    }


    function viewStatus(id) {
        leave_id = id;
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
        $("#leave_form input").not("[type='radio']").val("");
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

    function updateStepStatus(status) {
        let steps = document.querySelectorAll('.steps .step');
        steps.forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index < status) {
                step.classList.add('completed'); // Green
            } else if (index === status) {
                step.classList.add('active'); // Blue
            }
        });

        // Update line color
        let line = document.querySelector('.steps .line');
        if (status === 0) {
            line.style.background = '#dee2e6';
        } else if (status > 0 && status < steps.length) {
            line.style.background = '#0d6efd';
        } else if (status >= steps.length) {
            line.style.background = '#198754';
        }
    }

</script>
