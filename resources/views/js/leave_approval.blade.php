<script>
    let page = 1, leave_id = 0, leave_approval_last_page = 1, activeFilter = 'pending';
    const leaveApprovalViewButtonHtml = `<i class="bi bi-eye-fill text-success"></i>`;

    $(function () {
        $("#approveLeaveBtn, #rejectLeaveBtn").click(handleApproval);
        $("#undoApprovalBtn").click(handleUndoApproval);

        $("[data-filter]").on("click", function () {
            $("[data-filter]").removeClass("active");
            $(this).addClass("active");
            activeFilter = $(this).data("filter");
            page = 1;
            getLeaves();
        });

        $("#search_employee").on("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                page = 1;
                getLeaves();
            }
        });

        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page || targetPage < 1 || targetPage > leave_approval_last_page) return;
            page = targetPage;
            getLeaves();
        });

        getLeaves();
    });

    function getLeaves() {
        const search_employee = $("#search_employee").val().trim();
        $("#leave_approval_list").html("<tr><td colspan='5'>Loading...</td></tr>");

        fetch(`{{ route('getLeaveForApproval') }}?page=${page}&search_employee=${search_employee}&filter=${activeFilter}`)
            .then(r => r.json())
            .then(resp => renderLeaves(resp.data, resp.pagination))
            .catch(() => $("#leave_approval_list").html("<tr><td colspan='5'>Error</td></tr>"));
    }

    function renderLeaves(data, pagination) {
        $("#leave_approval_list").empty();
        $("#pagination_links").empty();
        $("#page_indicator").text("");

        if (!data.length) {
            $("#leave_approval_list").html("<tr><td colspan='5'>No records</td></tr>");
            $("#pagination_container").hide();
            return;
        }

        $("#pagination_container").show();

        data.forEach(it => $("#leave_approval_list").append(`
        <tr>
            <td>${it.lname}, ${it.fname} ${it.mname ?? ""}</td>
            <td>${it.description}</td>
            <td>${moment(it.created_at).format("MM/DD/YYYY hh:mm:ss")}</td>
            <td><button class="btn btn-sm leave-view-btn" onclick="viewApplication(${it.id}, this)">${leaveApprovalViewButtonHtml}</button></td>
        </tr>
    `));

        const from = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const to = from + data.length - 1;
        leave_approval_last_page = pagination.last_page;
        $("#page_items_count").text(`${from} - ${to} of ${pagination.total}`);
        $("#page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
        renderLeaveApprovalPaginationLinks(pagination);
    }

    function renderLeaveApprovalPaginationLinks(pagination) {
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

    function setLeaveApprovalButtonLoading(button, isLoading) {
        if (!button) return;
        const $button = $(button);
        $button.prop("disabled", isLoading);
        $button.html(isLoading
            ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
            : leaveApprovalViewButtonHtml);
    }

    function viewApplication(id, triggerButton = null) {
        leave_id = id;
        setLeaveApprovalButtonLoading(triggerButton, true);
        fetch(`{{ route('leave-applications.show', ':id') }}`.replace(':id', id))
            .then(r => r.json())
            .then(data => renderLeaveModal(data))
            .catch(() => alert("Error loading leave application."))
            .finally(() => setLeaveApprovalButtonLoading(triggerButton, false));
    }

    function renderLeaveModal(data) {
        $('#leave_name').text(data.name);
        $('#leave_type').text(data.type);
        $('#leave_commutation').text(data.commutation);
        $('#leave_details').val((data.details || '') + ':' + (data.extra || ''));

        if (data.attachment_url) {
            $('#view_attachment_link').attr('href', data.attachment_url);
            $('#view_attachment_row').show();
        } else {
            $('#view_attachment_row').hide();
        }

        $('#dates_applied_body').html(data.dates.map(d =>
            `<tr><td>${moment(d.date).format("MM/DD/YYYY")}</td><td>${d.duration} hours</td></tr>`
        ).join(""));

        updateStepStatus('#step1', data.approvals.sh);
        updateStepStatus('#step2', data.approvals.dh);
        updateStepStatus('#step3', data.approvals.hr);

        let remarks = ["sh", "dh", "hr"].map(role => {
            let a = data.approvals[role];
            return a?.remarks ? `
            <div class="mb-2">
                <strong>${role.toUpperCase()}:</strong>
                <p class="mb-0">${a.remarks}</p>
                <small class="text-muted">Status: ${a.status}</small>
            </div>
        ` : "";
        }).join("") || `<p class="text-muted">No remarks yet.</p>`;

        $('#remarks_history').html(remarks);
        const canAct = activeFilter === 'pending';
        $('#approveLeaveBtn, #rejectLeaveBtn').toggle(canAct);
        $('#undoApprovalBtn').toggle(!!data.can_undo);

        $('#viewLeaveModal').modal('show');
    }

    function updateStepStatus(sel, a) {
        $(sel).removeClass("approved rejected pending")
            .addClass(a?.status || "pending");
        $(sel + " .circle").attr("class", "circle " + (a?.status || "pending"));
    }

    function handleApproval() {
        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        fetch('{{ route('updateApprovalStatus') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({id: leave_id, remarks: $("#leave_remarks").val().trim(), status: this.value})
        })
            .then(r => r.json())
            .then(data => {
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }

    function handleUndoApproval() {
        if (!confirm("Are you sure you want to undo your approval? The application will be returned to pending status.")) return;
        const button = this;
        const originalHtml = button.innerHTML;
        $(button).prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Undoing...');
        fetch('{{ route('undoApproval') }}', {
            method: "POST",
            headers: {"Content-Type": "application/json", "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")},
            body: JSON.stringify({id: leave_id})
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                toastr.success(data.message, "Info");
                $('#viewLeaveModal').modal('hide');
                getLeaves();
            })
            .catch(err => toastr.error(err.message || "Error", "Oops"))
            .finally(() => $(button).prop("disabled", false).html(originalHtml));
    }

    /** ================================================
     *  MONETIZATION APPROVAL
     * ================================================ */
    let monetization_approval_page        = 1;
    let monetization_approval_last_page   = 1;
    let active_monetization_filter        = 'pending';
    let current_monetization_id           = 0;
    const monetize_approve_modal          = new bootstrap.Modal(document.getElementById('monetize_approve_modal'));

    // Filter buttons
    $("[data-monetization-filter]").on("click", function () {
        $("[data-monetization-filter]").removeClass("active");
        $(this).addClass("active");
        active_monetization_filter = $(this).data("monetization-filter");
        monetization_approval_page = 1;
        getMonetizationApprovals();
    });

    // Search
    $("#search_employee_monetization").on("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            monetization_approval_page = 1;
            getMonetizationApprovals();
        }
    });

    // Approve / Reject buttons
    $("#btn_approve_monetization").click(function () {
        handleMonetizationApproval('approved');
    });
    $("#btn_reject_monetization").click(function () {
        handleMonetizationApproval('rejected');
    });

    // Load when tab activates
    $('#tab-monetization-approval').on('shown.bs.tab', function () {
        monetization_approval_page = 1;
        getMonetizationApprovals();
    });

    function getMonetizationApprovals() {
        const search = $("#search_employee_monetization").val().trim();
        $("#monetization_approval_list").html("<tr><td colspan='5'>Loading...</td></tr>");

        fetch(`{{ route('getMonetizationApprovals') }}?page=${monetization_approval_page}&search_employee=${search}&filter=${active_monetization_filter}`)
            .then(r => r.json())
            .then(resp => renderMonetizationApprovals(resp.data, resp.pagination))
            .catch(() => $("#monetization_approval_list").html("<tr><td colspan='5'>Error loading data.</td></tr>"));
    }

    function renderMonetizationApprovals(data, pagination) {
        $("#monetization_approval_list").empty();

        if (!data || !data.length) {
            $("#monetization_approval_list").html("<tr><td colspan='5'>No requests found.</td></tr>");
            $("#monetization_approval_pagination_container").hide();
            return;
        }

        $("#monetization_approval_pagination_container").show();

        data.forEach(it => {
            $("#monetization_approval_list").append(`
            <tr>
                <td>${it.employee}</td>
                <td>${it.leave_type}</td>
                <td>${it.days_requested}</td>
                <td>${it.filed_date}</td>
                <td>
                    <button class="btn btn-sm" onclick="viewMonetizationRequest(${it.id}, ${JSON.stringify(it).replace(/"/g, '&quot;')})">
                        <i class="bi bi-eye-fill text-success"></i>
                    </button>
                </td>
            </tr>`);
        });

        monetization_approval_last_page = pagination.last_page;
        const from = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const to   = from + data.length - 1;
        $("#monetization_approval_page_items_count").text(`${from} - ${to} of ${pagination.total}`);
        $("#monetization_approval_page_indicator").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
        renderMonetizationApprovalPaginationLinks(pagination);
    }

    function renderMonetizationApprovalPaginationLinks(pagination) {
        const $links  = $("#monetization_approval_pagination_links");
        if (!$links.length || !pagination) return;

        const current = pagination.current_page;
        const last    = pagination.last_page;
        const start   = Math.max(1, current - 2);
        const end     = Math.min(last, current + 2);

        $links.empty();
        $links.append(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${current - 1})">Prev</a></li>`);
        for (let i = start; i <= end; i++) {
            $links.append(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${i})">${i}</a></li>`);
        }
        $links.append(`<li class="page-item ${current >= last ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeMonetizationApprovalPage(${current + 1})">Next</a></li>`);
    }

    function changeMonetizationApprovalPage(p) {
        if (p < 1 || p > monetization_approval_last_page) return;
        monetization_approval_page = p;
        getMonetizationApprovals();
    }

    function viewMonetizationRequest(id, data) {
        current_monetization_id = id;
        $("#ma_employee").text(data.employee);
        $("#ma_leave_type").text(data.leave_type);
        $("#ma_days").text(data.days_requested + ' days');
        $("#ma_filed_date").text(data.filed_date);
        $("#ma_remarks").val('');
        monetize_approve_modal.show();
    }

    function handleMonetizationApproval(status) {
        const remarks = $("#ma_remarks").val().trim();
        const btn     = status === 'approved' ? $("#btn_approve_monetization") : $("#btn_reject_monetization");
        btn.prop('disabled', true);

        fetch("{{ route('updateMonetizationApprovalStatus') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            body: JSON.stringify({ id: current_monetization_id, status, remarks }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                toastr.success(data.message || 'Status updated.', 'Success');
                monetize_approve_modal.hide();
                getMonetizationApprovals();
            })
            .catch(err => toastr.error(err.message || 'Error updating status.', 'Error'))
            .finally(() => btn.prop('disabled', false));
    }
</script>
