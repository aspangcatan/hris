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
                    Pick a generated cutoff, compute, then save. Each employee earns VL and SL from CSC Table III by
                    their filed and approved LWOP days; VL is less late/undertime. An employee is credited once per month.
                </span>
            </div>
            <div class="lce-filter">
                <select class="form-select" id="lce_cutoff">
                    <option value="">Loading cutoffs...</option>
                </select>
                <button type="button" class="btn btn-success btn-edit" id="btn_lce_compute">
                    <i class="bi bi-calculator me-1"></i> Compute
                </button>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="lce_notice" role="alert"></div>

        <div class="table-responsive lce-table-shell">
            <table class="table align-middle text-center">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th title="Filed and approved LWOP days this month">LWOP</th>
                    <th title="CSC Table III value for the LWOP days, minus late/undertime and personal pass slips">VL</th>
                    <th title="CSC Table III value for the LWOP days">SL</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody id="lce_body">
                <tr>
                    <td colspan="5" class="text-muted">Pick a cutoff and click Compute.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-success btn-edit" id="btn_lce_save" disabled>
                <i class="bi bi-check2-circle me-1"></i> Save Credits
            </button>
        </div>
    </div>
@stop
@section('js')
    <script>
        let lcePreview = [];
        let lceCutoffId = null;

        const lceEscape = text => String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        const lceThree = value => Number(value ?? 0).toFixed(3);

        $(document).ready(function () {
            loadLceCutoffs();
            $("#btn_lce_compute").on("click", computeLce);
            $("#btn_lce_save").on("click", saveLce);
            $("#lce_cutoff").on("change", function () {
                // A different cutoff needs a fresh computation before saving.
                lcePreview = [];
                $("#btn_lce_save").prop("disabled", true);
                $("#lce_notice").addClass("d-none");
                $("#lce_body").html('<tr><td colspan="5" class="text-muted">Click Compute.</td></tr>');
            });
        });

        function loadLceCutoffs(keepId) {
            fetch(`{{ route('leave_credits_earned.cutoffs') }}`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $select = $("#lce_cutoff").empty();
                    const rows = data.data || [];
                    if (!rows.length) {
                        $select.append('<option value="">No generated cutoffs yet</option>');
                        return;
                    }
                    $select.append('<option value="">Select a cutoff</option>');
                    rows.forEach(c => {
                        const done = c.credited >= c.employees ? ' (all credited)' : (c.credited > 0 ? ` (${c.credited}/${c.employees} credited)` : '');
                        $select.append(`<option value="${c.id}">${lceEscape(c.month_label)}: ${lceEscape(c.title || '')} (${lceEscape(c.period_start)} to ${lceEscape(c.period_end)})${done}</option>`);
                    });
                    if (keepId) {
                        $select.val(String(keepId));
                    }
                })
                .catch(() => toastr.error("Failed to load cutoffs.", "Error"));
        }

        function computeLce() {
            const cutoffId = $("#lce_cutoff").val();
            if (!cutoffId) {
                toastr.error("Select a cutoff first.", "Validation");
                return;
            }

            const $button = $("#btn_lce_compute").prop("disabled", true);
            fetch(`{{ route('previewCutoffLeaveCredits') }}?${new URLSearchParams({cutoff_id: cutoffId})}`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#lce_body").empty();
                    lcePreview = [];
                    lceCutoffId = cutoffId;

                    if (!data.success) {
                        toastr.error(data.message || data.error || "Failed to compute leave credits.", "Error");
                        $tbody.html('<tr><td colspan="5" class="text-muted">Nothing computed.</td></tr>');
                        $("#btn_lce_save").prop("disabled", true);
                        return;
                    }

                    // Nothing to post (no employees, all already credited, or no attendance).
                    const notice = data.message || "";
                    $("#lce_notice").toggleClass("d-none", !notice).text(notice);

                    const rows = data.data || [];
                    if (!rows.length) {
                        $tbody.html('<tr><td colspan="5" class="text-muted">No employees to credit.</td></tr>');
                    }
                    lcePreview = rows;
                    rows.forEach(item => {
                        const status = item.already_credited
                            ? '<span class="badge bg-secondary">Already credited</span>'
                            : '<span class="badge bg-success">To be credited</span>';
                        const lwop = item.without_pay_days !== null && item.without_pay_days !== undefined
                            ? Number(item.without_pay_days)
                            : '<span class="small text-warning" title="Generated before the Table III LWOP lookup - regenerate this cutoff to use it">old formula</span>';
                        $tbody.append(`
                            <tr class="${item.already_credited ? 'text-muted' : ''}">
                                <td class="text-start">${lceEscape(item.name)}</td>
                                <td>${lwop}</td>
                                <td title="${lceThree(item.earned_points)} - ${lceThree(item.deducted_points)} late/undertime">${lceThree(item.net_points)}</td>
                                <td>${lceThree(item.earned_points)}</td>
                                <td>${status}</td>
                            </tr>`);
                    });
                    $("#btn_lce_save").prop("disabled", !!notice || !rows.length);
                })
                .catch(() => toastr.error("Failed to compute leave credits.", "Error"))
                .finally(() => $button.prop("disabled", false));
        }

        function saveLce() {
            if (!lceCutoffId || !lcePreview.length) {
                return;
            }
            if (!confirm("Post these leave credits? Each employee can be credited only once for this month.")) {
                return;
            }

            const $button = $("#btn_lce_save").prop("disabled", true);
            fetch(`{{ route('applyCutoffLeaveCredits') }}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    cutoff_id: lceCutoffId,
                    employees: lcePreview.map(item => item.employee_id)
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Leave credits posted.", "Information");
                        computeLce();               // rows now show "Already credited"
                        loadLceCutoffs(lceCutoffId); // refresh the "(x/y credited)" labels, keep the selection
                    } else {
                        toastr.warning(data.message || data.error || "No leave credits were posted.", "Nothing posted");
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
