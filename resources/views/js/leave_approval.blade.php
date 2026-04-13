<script>
    let page = 1, leave_id = 0, leave_approval_last_page = 1;
    const leaveApprovalViewButtonHtml = `<i class="bi bi-eye-fill text-success"></i>`;

    $(function () {
        $("#approveLeaveBtn, #rejectLeaveBtn").click(handleApproval);

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

        fetch(`{{ route('getLeaveForApproval') }}?page=${page}&search_employee=${search_employee}`)
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
</script>
