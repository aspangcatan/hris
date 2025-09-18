<script>
    let page = 1, leave_id = 0;

    // Event bindings
    $(function () {
        $("#btn_next").click(() => changePage(1));
        $("#btn_prev").click(() => changePage(-1));
        $("#approveLeaveBtn, #rejectLeaveBtn").click(handleApproval);

        $("#search_employee").on("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();   // prevent form submission (if inside a form)
                page = 1;             // reset back to first page when searching
                getLeaves();
            }
        });


        getLeaves(); // initial load
    });

    function changePage(step) {
        page = Math.max(1, page + step);
        getLeaves();
    }

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

        if (!data.length) {
            $("#leave_approval_list").html("<tr><td colspan='5'>No records</td></tr>");
            $("#pagination_container").hide(); // 👈 hide pagination when empty
            return;
        }

        $("#pagination_container").show(); // show only when there’s data

        data.forEach(it => $("#leave_approval_list").append(`
        <tr>
            <td>${it.lname}, ${it.fname} ${it.mname ?? ""}</td>
            <td>${it.description}</td>
            <td>${moment(it.created_at).format("MM/DD/YYYY hh:mm:ss")}</td>
            <td><button class="btn btn-sm" onclick="viewApplication(${it.id})"><i class="bi bi-eye-fill text-success"></i></button></td>
        </tr>
    `));

        $("#page_items_count").text(`Page ${pagination.current_page} of ${pagination.last_page}`);
        $("#btn_prev").toggleClass("d-none", pagination.current_page <= 1);
        $("#btn_next").toggleClass("d-none", pagination.current_page >= pagination.last_page);
    }


    function viewApplication(id) {
        leave_id = id;
        fetch(`{{ route('leave-applications.show', ':id') }}`.replace(':id', id))
            .then(r => r.json())
            .then(data => renderLeaveModal(data))
            .catch(() => alert("Error loading leave application."));
    }

    function renderLeaveModal(data) {
        $('#leave_name').text(data.name);
        $('#leave_type').text(data.type);
        $('#leave_commutation').text(data.commutation);
        $('#leave_details').val(data.details || '');

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
            .catch(err => toastr.error(err.message || "Error", "Oops"));
    }
</script>
