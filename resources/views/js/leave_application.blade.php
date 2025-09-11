<script>
    let page = 0;
    const file_leave_modal = new bootstrap.Modal(document.getElementById('file_leave_modal'));
    const leave_modal = new bootstrap.Modal(document.getElementById('leave_modal'));
    const LEAVE_TYPES = [];

    $(document).ready(function () {
        $('.datepicker').datepicker({multidate: true});

        // Button actions
        $("#btn_file_leave").click(() => file_leave_modal.toggle());
        $("#btn_reload").click(init_leave);
        $("#btn_search").click(() => {
            page = 0;
            getLeaves();
        });
        $("#btn_next").click(() => {
            page++;
            getLeaves();
            $("#btn_prev").removeClass("d-none");
        });
        $("#btn_prev").click(() => {
            if (page === 0) return;
            if (page === 1) $("#btn_prev").addClass("d-none");
            page--;
            getLeaves();
        });

        $("#btn_save_leave").click(handleSaveLeave);

        // Init
        init_leave();
        getLeaves();
    });

    /** ------------------------
     *  Helpers
     * ------------------------ */
    async function fetchData(url, options = {}) {
        try {
            const response = await fetch(url, options);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || "Server error");
            return data;
        } catch (err) {
            console.error(err);
            toastr.error(err.message || "Something went wrong", "Error");
            throw err; // rethrow so caller knows it failed
        }
    }

    function toggleButtonLoading(btn, isLoading, text = "SUBMIT") {
        if (isLoading) {
            btn.html(`
                <button class="btn btn-primary" type="button" disabled>
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Loading...
                </button>
            `).prop("disabled", true);
        } else {
            btn.html(text).prop("disabled", false);
        }
    }

    /** ------------------------
     *  Save Leave Application
     * ------------------------ */
    async function handleSaveLeave() {
        let is_valid = true;
        const leave_id = $("input[name='leave_type']:checked").val();
        const leave_type = $("input[name='leave_type']:checked").next().text().trim();
        const leave_details = $("#details_leave input[type='radio']:checked").siblings("label").text().trim();
        const leave_extra = $("#details_leave input[type='radio']:checked").siblings("input[type='text']").val() || "";
        const commutation = $("input[name='commutation']:checked").next().text();
        const date_applied_array = [];

        $("#dates_table_body tr").each(function () {
            const date = $(this).find("td:eq(0) input").val();
            const hours = $(this).find("td:eq(1) input").val();
            if (date && hours) {
                date_applied_array.push({date_applied: date, hours: hours});
            }
        });

        // Validation
        if (!leave_type) {
            is_valid = false;
            toastr.error("Please select leave type", "Required");
        }
        if (!leave_details) {
            is_valid = false;
            toastr.error("Please provide leave details", "Required");
        }
        if (!commutation) {
            is_valid = false;
            toastr.error("Please specify commutation", "Required");
        }
        if (date_applied_array.length < 1) {
            is_valid = false;
            toastr.error("Please specify date applied", "Required");
        }

        if (!is_valid) return;

        const params = {
            leave_id: parseInt(leave_id),
            detail: leave_details,
            extra: leave_extra,
            commutation,
            dates: date_applied_array
        };

        const btn = $("#btn_save_leave");
        toggleButtonLoading(btn, true);

        try {
            const body = await fetchData('{{ route('insertLeaveApplication') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            });

            toastr.success(body.message || "Leave submitted successfully", "Information");
            clearForm();
            getLeaves();
            init_leave();
        } catch {
            // error already handled in fetchData
        } finally {
            toggleButtonLoading(btn, false);
        }
    }

    /** ------------------------
     *  Dynamic Rows
     * ------------------------ */
    function addRow() {
        $("#dates_table_body").append(`
            <tr>
                <td><input type="date" class="form-control" name="leave_dates[]"></td>
                <td><input type="number" class="form-control" name="leave_hours[]" min="1" max="24"></td>
                <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Remove</button></td>
            </tr>
        `);
    }

    function removeRow(button) {
        if ($("#dates_table_body tr").length <= 1) {
            toastr.error("Must specify at least one date for leave", "Information");
            return;
        }
        $(button).closest("tr").remove();
    }

    /** ------------------------
     *  Init Leave Types + Balances
     * ------------------------ */
    async function init_leave() {
        const data = await loadCreditBalance();
        $("#leave_list").empty();
        data.forEach(it => {
            $("#leave_list").append(`
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="leave_type" value="${it.id}">
                        <div class="d-flex align-items-center">
                            <label class="form-check-label">${it.description}</label>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    async function loadCreditBalance() {
        try {
            const data = await fetchData('{{ route('getCreditBalances') }}');
            $("#credit_balances, #legend_list").empty();
            LEAVE_TYPES.length = 0;

            data.forEach(it => {
                const balanceClass = it.balance > 0 ? "text-success" : (it.balance < 0 ? "text-danger" : "");
                $("#credit_balances").append(`
                    <div class="col-md-6">
                        <span class="fw-bold">${it.code}:</span>
                        <span class="ms-1 ${balanceClass}">${it.balance}</span>
                    </div>
                `);
                $("#legend_list").append(`
                    <div class="col-md-6">
                        <span class="fw-bold">${it.code}:</span>
                        <span class="ms-1">${it.description}</span>
                    </div>
                `);
                LEAVE_TYPES.push({id: it.id, description: it.description});
            });
            return data;
        } catch {
            return [];
        }
    }

    /** ------------------------
     *  Get Leave Applications
     * ------------------------ */
    async function getLeaves() {
        $("#leave_history_list").html("<tr><td colspan='6'>Loading data, please wait...</td></tr>");
        $("#btn_next, #btn_prev").prop("disabled", true);

        try {
            const data = await fetchData(`{{ route('getLeaveApplications') }}?page=${page}`);
            $("#leave_history_list").empty();
            $("#btn_next, #btn_prev").prop("disabled", false).addClass("d-none");

            if (!data.length) {
                $("#leave_history_list").html("<tr><td colspan='6'>No record found</td></tr>");
                return;
            }

            $("#pagination_container").removeClass("d-none");
            let max_visible = data.length === 11 ? 10 : data.length;
            if (data.length === 11) $("#btn_next").removeClass("d-none");

            $("#page_items_count").text(`${(page * 10) + 1} - ${(page * 10) + max_visible}`);

            data.slice(0, max_visible).forEach(it => {
                const dates = Object.values(it.dates_applied).map(d => moment(d.date_applied).format("MM/DD/YYYY"));
                const status = it.status === "Approved" ? `<span class="badge bg-success">APPROVED</span>`
                    : it.status === "Rejected" ? `<span class="badge bg-danger">REJECTED</span>`
                        : `<span class="badge bg-secondary">PENDING</span>`;

                $("#leave_history_list").append(`
                <tr id="leave_id_${it.id}">
                    <td>${it.description}</td>
                    <td>${it.detail}</td>
                    <td>${it.extra || ""}</td>
                    <td>${dates.join(", ")}</td>
                    <td>${status}</td>
                    <td>
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: transparent">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewApplication(${it.id})">
                                        <i class="bi bi-eye-fill text-success me-2"></i> View
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="printApplication(${it.id})">
                                        <i class="bi bi-printer text-primary me-2"></i> Print
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="cancelApplication(${it.id})">
                                        <i class="bi bi-x-circle-fill me-2"></i> Cancel
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `);

            });
        } catch {
            $("#leave_history_list").html("<tr><td colspan='6'>Error loading data</td></tr>");
        }
    }

    /** ------------------------
     *  View Application
     * ------------------------ */
    async function printApplication(id){
        window.open(`{{ route('printLeaveForm') }}?id=${id}`, '_blank');
    }

    async function cancelApplication(id){
        while (true) {
            if (!confirm("Are you sure you want to cancel your application?")) {
                break;
            }
            if (!confirm("Cancelling your application means undoing your application, you still proceed?")) {
                break;
            }
        }
        alert("Dugaya ka gets oi HAHAHA");
    }



    async function viewApplication(id) {
        try {
            const data = await fetchData(`{{ route('leave-applications.show', ':id') }}`.replace(':id', id), {
                method: 'GET',
                headers: {'Accept': 'application/json'}
            });

            $('#leave_name').text(data.name);
            $('#leave_type').text(data.type);
            $('#leave_commutation').text(data.commutation);
            $('#leave_details').val(data.details || '');

            // Dates
            $('#dates_applied_body').html(
                data.dates.map(d => `<tr><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td></tr>`).join("")
            );

            // Step status
            updateStepStatus('#step1', data.approvals.sh);
            updateStepStatus('#step2', data.approvals.dh);
            updateStepStatus('#step3', data.approvals.hr);

            // Remarks
            let remarksHtml = "";
            ["sh", "dh", "hr"].forEach(role => {
                if (data.approvals[role]?.remarks) {
                    const label = role === "sh" ? "Supervisor"
                        : role === "dh" ? "Division Head" : "Human Resource";
                    remarksHtml += `
                        <div class="mb-2">
                            <strong>${label}:</strong>
                            <p class="mb-0">${data.approvals[role].remarks}</p>
                        </div><hr>
                    `;
                }
            });
            $('#remarks_history').html(remarksHtml || `<p class="text-muted mb-0">No remarks yet.</p>`);

            $('#viewLeaveModal').modal('show');
        } catch {
            alert("Error loading leave application.");
        }
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
        $("#leave_form input").not("[type='radio']").val("");
        $("#leave_form input[type='radio']").prop("checked", false);
    }
</script>
