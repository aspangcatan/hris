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

        $("#approveLeaveBtn, #rejectLeaveBtn").click(function () {
            const remarks = $("#leave_remarks").val().trim();
            const status = $(this).val();

            fetch('{{ route('updateApprovalStatus') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json", // Important to ensure JSON response is parsed correctly
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                },
                body: JSON.stringify({id: leave_id, remarks: remarks, status: status})
            })
                .then(async res => {
                    if (!res.ok) {
                        // Extract error message from response body
                        const error = await res.json();
                        throw new Error(error.message || "Something went wrong");
                    }
                    return res.json();
                })
                .then(data => {
                    toastr.success(data.message, "Information");
                    leave_modal.toggle();
                    getLeaves();
                })
                .catch(err => {
                    toastr.error(err.message, "Ooops");
                    console.error("Approval error:", err);
                });
        });


        getLeaves();
    });

    function getLeaves() {
        $("#btn_next").prop("disabled", true);
        $("#btn_prev").prop("disabled", true);
        $("#leave_approval_list").empty();
        $("#leave_approval_list").append("<tr><td colspan='5'>Loading data, please wait...</td></tr>");

        fetch('{{ route('getLeaveForApproval') }}?page=' + page, {method: "GET"})
            .then(res => {
                if (!res.ok) throw new Error("Failed to fetch leaves for approval.");
                return res.json();
            })
            .then(resp => {
                const data = resp.data;
                const pagination = resp.pagination;

                $("#btn_next").prop("disabled", false);
                $("#btn_prev").prop("disabled", false);
                $("#leave_approval_list").empty();

                if (data.length < 1) {
                    $("#leave_approval_list").append("<tr><td colspan='5'>No record found</td></tr>");
                    $("#pagination_container").addClass("d-none");
                    return;
                }

                $("#pagination_container").removeClass("d-none");

                // Update pagination text
                $("#page_items_count").text("Page " + pagination.current_page + " of " + pagination.last_page);

                // Hide/Show prev button
                if (pagination.current_page <= 1) {
                    $("#btn_prev").addClass("d-none");
                } else {
                    $("#btn_prev").removeClass("d-none");
                }

                // Hide/Show next button
                if (pagination.current_page >= pagination.last_page) {
                    $("#btn_next").addClass("d-none");
                } else {
                    $("#btn_next").removeClass("d-none");
                }

                // Render rows
                data.forEach(it => {
                    const tr = `
                    <tr id="leave_id_${it.id}">
                        <td>${it.lname}, ${it.fname} ${it.mname ?? ""}</td>
                        <td>${it.description}</td>
                        <td>${moment(it.created_at).format("MM/DD/YYYY hh:mm:ss")}</td>
                        <td>
                            <button type="button" class="btn btn-sm" style="background: transparent" onClick="viewApplication(${it.id})">
                                <i class="bi bi-eye-fill text-success"></i>
                            </button>
                        </td>
                    </tr>
                `;
                    $("#leave_approval_list").append(tr);
                });
            })
            .catch(err => {
                console.error(err);
                $("#leave_approval_list").html("<tr><td colspan='5' class='text-danger'>Error loading data</td></tr>");
            });
    }

    function viewApplication(id) {
        leave_id = id;
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
                $('#leave_commutation').text(data.commutation);
                $('#leave_details').val(data.details || '');

                // Build dates table rows
                let dateRows = '';
                data.dates.forEach(d => {
                    dateRows += `
                <tr>
                    <td>${d.date}</td>
                    <td>${d.duration} hours</td>
                </tr>
            `;
                });
                $('#dates_applied_body').html(dateRows);

                updateStepStatus('#step1', data.approvals.sh);
                updateStepStatus('#step2', data.approvals.dh);
                updateStepStatus('#step3', data.approvals.hr);


                // Show modal
                $('#viewLeaveModal').modal('show');
            })
            .catch(err => {
                console.error(err);
                alert("Error loading leave application.");
            });
    }

    function updateStepStatus(selector, approval) {
        $(selector).removeClass("approved rejected pending");
        $(selector + " .circle").removeClass("approved rejected pending");

        if (approval?.status === 'approved') {
            $(selector).addClass("approved");
            $(selector + " .circle").addClass("approved");
        } else if (approval?.status === 'rejected') {
            $(selector).addClass("rejected");
            $(selector + " .circle").addClass("rejected");
        } else {
            $(selector).addClass("pending");
            $(selector + " .circle").addClass("pending");
        }
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
