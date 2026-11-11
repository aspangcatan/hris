@extends('master')
@section('content')
    <style>
        .lce-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .lce-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .lce-head h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .lce-head span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .lce-filter {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .lce-filter select {
            min-width: 320px;
        }

        .lce-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .lce-table-shell table {
            margin-bottom: 0;
        }

        .lce-table-shell thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
    </style>


    <div class="lce-board">
        <div class="lce-head">
            <div>
                <h4>Leave Credits Earned</h4>
                <span>
                    Credits are posted once a month from the month's finalized cutoffs. Each employee earns VL and SL
                    from CSC Table III by their filed and approved LWOP days (late/undertime is charged to salary, not
                    to leave). Review the batch, then click Post. An employee is credited once per month.
                </span>
            </div>
            <div class="lce-filter">
                <select class="form-select" id="lce_month">
                    <option value="">Loading months...</option>
                </select>
            </div>
        </div>

        <div class="small text-muted mb-2 d-none" id="lce_cutoffs"></div>
        <div class="alert alert-warning d-none" id="lce_drafts" role="alert"></div>
        <div class="alert alert-warning d-none" id="lce_notice" role="alert"></div>
        <div class="alert alert-danger d-none" id="lce_absences" role="alert"></div>

        <div class="table-responsive lce-table-shell">
            <table class="table align-middle text-center">
                <thead>
                <tr>
                    <th style="width: 3%"><input type="checkbox" class="form-check-input" id="lce_select_all" title="Select all ready"></th>
                    <th class="text-start">Employee</th>
                    <th title="The employee's finalized cutoffs in this month">Cutoffs</th>
                    <th title="Filed and approved LWOP days in the month (all finalized cutoffs added together)">LWOP</th>
                    <th title="CSC Table III value for the LWOP days">VL</th>
                    <th title="CSC Table III value for the LWOP days">SL</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody id="lce_body">
                <tr>
                    <td colspan="7" class="text-muted">Pick a month.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <span class="small text-muted" id="lce_selected_note"></span>
            <button type="button" class="btn btn-success btn-edit" id="btn_lce_post" disabled>
                <i class="bi bi-check2-circle me-1"></i> Post Credits
            </button>
        </div>
    </div>
@stop
@section('js')
    <script>
        let lceBatch = null;

        const lceEscape = text => String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        const lceThree = value => Number(value ?? 0).toFixed(3);

        $(document).ready(function () {
            // ?month=YYYY-MM (link from the Cutoff Summary page) preselects a month.
            const wanted = new URLSearchParams(window.location.search).get("month");
            loadLceMonths(wanted);
            $("#lce_month").on("change", loadLceBatch);
            $("#btn_lce_post").on("click", postLce);
            $("#lce_select_all").on("change", function () {
                $("#lce_body .lce-pick:not(:disabled)").prop("checked", $(this).is(":checked"));
                updateLceSelection();
            });
            $(document).on("change", "#lce_body .lce-pick", updateLceSelection);
        });

        function loadLceMonths(keepKey) {
            fetch(`{{ route('leave_credits_earned.months') }}`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $select = $("#lce_month").empty();
                    const rows = data.data || [];
                    if (!rows.length) {
                        $select.append('<option value="">No finalized cutoffs yet</option>');
                        $("#lce_body").html('<tr><td colspan="7" class="text-muted">Finalize a cutoff on the Cutoff Summary page first.</td></tr>');
                        return;
                    }
                    $select.append('<option value="">Select a month</option>');
                    rows.forEach(m => {
                        let state;
                        if (m.ready > 0) {
                            state = `Ready to post: ${m.ready}`;
                        } else if (m.credited > 0 && m.credited === m.employees) {
                            state = 'Posted';
                        } else {
                            state = `${m.credited}/${m.employees} credited`;
                        }
                        const extra = m.blocked > 0 ? `, ${m.blocked} blocked` : '';
                        $select.append(`<option value="${m.key}" data-year="${m.year}" data-month="${m.month}">${lceEscape(m.label)} (${lceEscape(state)}${extra})</option>`);
                    });
                    if (keepKey && $select.find(`option[value="${keepKey}"]`).length) {
                        $select.val(keepKey);
                        loadLceBatch();
                    }
                })
                .catch(() => toastr.error("Failed to load months.", "Error"));
        }

        function selectedLceMonth() {
            const $option = $("#lce_month").find(":selected");
            return $option.val() ? {year: $option.data("year"), month: $option.data("month"), key: $option.val()} : null;
        }

        function statusBadge(item) {
            if (item.status === "credited") {
                return '<span class="badge bg-secondary">Already credited</span>';
            }
            if (item.status === "unfiled") {
                return `<span class="badge bg-danger">Unfiled absence</span>`
                    + `<div class="small text-danger mt-1">${lceEscape(item.reason)}</div>`;
            }
            if (item.status === "blocked") {
                return `<span class="badge bg-danger" title="${lceEscape(item.reason)}">Blocked</span>`
                    + `<div class="small text-danger mt-1">${lceEscape(item.reason)}</div>`;
            }
            return item.full_month
                ? '<span class="badge bg-success">Ready</span>'
                : '<span class="badge bg-warning text-dark" title="The finalized cutoffs do not cover the whole month">Ready, partial month</span>';
        }

        function loadLceBatch() {
            const picked = selectedLceMonth();
            lceBatch = null;
            $("#btn_lce_post").prop("disabled", true);
            $("#lce_select_all").prop("checked", false);
            $("#lce_notice, #lce_drafts, #lce_cutoffs, #lce_absences").addClass("d-none");
            $("#lce_selected_note").text("");
            if (!picked) {
                $("#lce_body").html('<tr><td colspan="7" class="text-muted">Pick a month.</td></tr>');
                return;
            }

            $("#lce_body").html('<tr><td colspan="7" class="text-muted">Loading...</td></tr>');
            fetch(`{{ route('leave_credits_earned.preview') }}?${new URLSearchParams({year: picked.year, month: picked.month})}`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#lce_body").empty();
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load the batch.", "Error");
                        $tbody.html('<tr><td colspan="7" class="text-muted">Nothing loaded.</td></tr>');
                        return;
                    }
                    lceBatch = {year: picked.year, month: picked.month, key: picked.key, rows: data.data || []};

                    const cutoffs = (data.cutoffs || []).map(c => `${lceEscape(c.title || "")} (${lceEscape(c.period_start)} to ${lceEscape(c.period_end)})`);
                    $("#lce_cutoffs").toggleClass("d-none", !cutoffs.length).html(`<strong>Finalized cutoffs:</strong> ${cutoffs.join("; ")}`);

                    const drafts = data.draft_cutoffs || [];
                    $("#lce_drafts").toggleClass("d-none", !drafts.length).html(drafts.length
                        ? `<strong>Not included (still draft):</strong> ${drafts.map(c => lceEscape(c.title || `${c.period_start} to ${c.period_end}`)).join("; ")}. Finalize them first if their employees should be credited for this month.`
                        : "");

                    const notice = data.message || "";
                    $("#lce_notice").toggleClass("d-none", !notice).text(notice);

                    const unfiled = data.unfiled_absences || [];
                    $("#lce_absences").toggleClass("d-none", !unfiled.length).html(unfiled.length
                        ? `<strong>Posting is blocked while any of these ${unfiled.length} employee(s) is ticked. Resolve the absences, or untick them to post the rest:</strong>`
                          + `<ul class="mb-0 mt-1">${unfiled.map(u => `<li><strong>${lceEscape(u.name)}</strong>: ${lceEscape(u.detail)}</li>`).join("")}</ul>`
                        : "");

                    if (!lceBatch.rows.length) {
                        $tbody.html('<tr><td colspan="7" class="text-muted">No employees to credit.</td></tr>');
                        return;
                    }
                    lceBatch.rows.forEach(item => {
                        const ready = item.status === "ready";
                        // Partial-month rows start unticked: posting locks the month for that employee.
                        // Unfiled-absence rows start ticked: HR has to untick them on purpose.
                        const unfiledRow = item.status === "unfiled";
                        const checked = (ready && item.full_month) || unfiledRow ? "checked" : "";
                        const lwop = item.without_pay_days !== null && item.without_pay_days !== undefined
                            ? Number(item.without_pay_days)
                            : '<span class="small text-warning" title="Generated before the Table III LWOP lookup">old formula</span>';
                        $tbody.append(`
                            <tr class="${ready || unfiledRow ? '' : 'text-muted'}">
                                <td><input type="checkbox" class="form-check-input lce-pick" value="${item.employee_id}" ${ready || unfiledRow ? checked : "disabled"}></td>
                                <td class="text-start">${lceEscape(item.name)}</td>
                                <td class="small">${(item.cutoffs || []).map(lceEscape).join(", ")}</td>
                                <td>${lwop}</td>
                                <td>${lceThree(item.vl_points)}</td>
                                <td>${lceThree(item.sl_points)}</td>
                                <td>${statusBadge(item)}</td>
                            </tr>`);
                    });
                    updateLceSelection();
                })
                .catch(() => toastr.error("Failed to load the batch.", "Error"));
        }

        function pickedLceEmployees() {
            return $("#lce_body .lce-pick:checked").map(function () {
                return parseInt($(this).val(), 10);
            }).get();
        }

        function updateLceSelection() {
            const count = pickedLceEmployees().length;
            $("#btn_lce_post").prop("disabled", count === 0);
            $("#lce_selected_note").text(count ? `${count} employee(s) selected to post.` : "");
        }

        function postLce() {
            const employees = pickedLceEmployees();
            if (!lceBatch || !employees.length) {
                return;
            }
            const label = $("#lce_month").find(":selected").text().split(" (")[0];
            if (!confirm(`Post leave credits for ${label} to ${employees.length} employee(s)? Each employee can be credited only once for this month.`)) {
                return;
            }

            const $button = $("#btn_lce_post").prop("disabled", true);
            fetch(`{{ route('leave_credits_earned.post') }}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({year: lceBatch.year, month: lceBatch.month, employees: employees})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Leave credits posted.", "Information");
                        loadLceMonths(lceBatch.key); // refresh counts and reload the batch
                    } else {
                        toastr.warning(data.message || "No leave credits were posted.", "Nothing posted");
                        $button.prop("disabled", false);
                    }
                })
                .catch(() => {
                    toastr.error("Failed to post leave credits.", "Error");
                    $button.prop("disabled", false);
                });
        }
    </script>
@stop
