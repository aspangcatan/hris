@extends('master')
@section('content')
    <div class="page-head">
        <div>
            <h1>Opening Leave Balances</h1>
            <p class="page-sub">Certify each employee's starting balance. Later cutoffs and the leave ledger build on it, so check it against the leave card first. Once certified it can't be changed here.</p>
        </div>
    </div>

    <div class="alert d-none" id="lob_reconcile" role="alert"></div>

    <div class="lob-board card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div>
                    <label class="form-label mb-1" for="lob_as_of">Balance as of</label>
                    <input type="date" class="form-control" id="lob_as_of" max="{{ now()->toDateString() }}"
                           value="{{ now()->startOfMonth()->subMonth()->subDay()->toDateString() }}">
                </div>
                <button type="button" class="btn btn-outline-primary" id="btn_lob_load">Load</button>
                <div class="ms-auto">
                    <input type="search" class="form-control" id="lob_search" placeholder="Search employee">
                </div>
            </div>

            <div class="table-responsive" style="max-height: 60vh;">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th style="width: 3%"><input type="checkbox" class="form-check-input" id="lob_select_all" title="Select all ready"></th>
                        <th>Employee</th>
                        <th title="Balance today + leave already filed for dates after the as-of date">Opening balances</th>
                        <th class="text-center">Status</th>
                    </tr>
                    </thead>
                    <tbody id="lob_body">
                    <tr><td colspan="4" class="text-muted text-center">Choose a date and click Load.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <span class="small text-muted" id="lob_note"></span>
                <button type="button" class="btn btn-primary" id="btn_lob_certify" disabled>
                    <i class="bi bi-patch-check me-1"></i> Certify
                </button>
            </div>
        </div>
    </div>
@stop
@section('js')
    <script>
        const LOB_PREVIEW = "{{ route('leave_opening_balances.preview') }}";
        const LOB_CERTIFY = "{{ route('leave_opening_balances.certify') }}";
        const LOB_RECONCILE = "{{ route('leave_opening_balances.reconcile') }}";

        const lobEscape = text => $("<div>").text(text ?? "").html();
        const lobThree = value => Number(value ?? 0).toFixed(3);

        $(document).ready(function () {
            $("#btn_lob_load").on("click", loadLob);
            $("#btn_lob_certify").on("click", certifyLob);
            $("#lob_search").on("input", filterLob);
            $("#lob_select_all").on("change", function () {
                $("#lob_body tr:visible .lob-pick:not(:disabled)").prop("checked", $(this).is(":checked"));
                updateLob();
            });
            $(document).on("change", "#lob_body .lob-pick", updateLob);
            loadReconcile();
        });

        function lobBalances(item) {
            return item.balances.map(b => {
                const ahead = b.filed_ahead > 0 ? ` <span class="text-muted">(${lobThree(b.current)} + ${lobThree(b.filed_ahead)} filed ahead)</span>` : "";
                return `<div class="small"><strong>${lobEscape(b.code)}</strong>: ${lobThree(b.opening)}${ahead}</div>`;
            }).join("");
        }

        function lobStatus(item) {
            if (item.status === "certified") {
                return `<span class="badge bg-primary">Certified</span><div class="small text-muted mt-1">as of ${lobEscape(item.certified_on)}</div>`;
            }
            if (item.status === "blocked") {
                return `<span class="badge bg-danger">Blocked</span><div class="small text-danger mt-1">${lobEscape(item.reason)}</div>`;
            }
            return `<span class="badge bg-success">Ready</span>`;
        }

        function loadLob() {
            const asOf = $("#lob_as_of").val();
            if (!asOf) {
                toastr.warning("Choose the as-of date.", "Validation");
                return;
            }
            $("#lob_select_all").prop("checked", false);
            $("#lob_body").html('<tr><td colspan="4" class="text-muted text-center">Loading...</td></tr>');
            fetch(`${LOB_PREVIEW}?${new URLSearchParams({as_of: asOf})}`, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $body = $("#lob_body").empty();
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load.", "Error");
                        return;
                    }
                    if (!data.data.length) {
                        $body.html('<tr><td colspan="4" class="text-muted text-center">No employees have leave balances.</td></tr>');
                        return;
                    }
                    data.data.forEach(item => {
                        const ready = item.status === "ready";
                        $body.append(`
                            <tr data-name="${lobEscape(item.name).toLowerCase()}">
                                <td><input type="checkbox" class="form-check-input lob-pick" value="${item.employee_id}" ${ready ? "" : "disabled"}></td>
                                <td>${lobEscape(item.name)}</td>
                                <td>${lobBalances(item)}</td>
                                <td class="text-center">${lobStatus(item)}</td>
                            </tr>`);
                    });
                    filterLob();
                    updateLob();
                })
                .catch(() => toastr.error("Failed to load.", "Error"));
        }

        function filterLob() {
            const value = ($("#lob_search").val() || "").toLowerCase();
            $("#lob_body tr").each(function () {
                $(this).toggle(($(this).data("name") || "").toString().includes(value));
            });
        }

        function pickedLob() {
            return $("#lob_body .lob-pick:checked").map(function () {
                return parseInt($(this).val(), 10);
            }).get();
        }

        function updateLob() {
            const count = pickedLob().length;
            $("#btn_lob_certify").prop("disabled", count === 0);
            $("#lob_note").text(count ? `${count} employee(s) selected.` : "");
        }

        function certifyLob() {
            const employees = pickedLob();
            const asOf = $("#lob_as_of").val();
            if (!employees.length || !confirm(`Certify opening balances as of ${asOf} for ${employees.length} employee(s)? This can't be changed here afterwards.`)) {
                return;
            }
            const $button = $("#btn_lob_certify").prop("disabled", true);
            fetch(LOB_CERTIFY, {
                method: "POST",
                headers: {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}"},
                body: JSON.stringify({as_of: asOf, employees: employees}),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message, "Information");
                        loadLob();
                        loadReconcile();
                    } else {
                        toastr.warning(data.message || "Nothing was certified.", "Nothing certified");
                        $button.prop("disabled", false);
                    }
                })
                .catch(() => {
                    toastr.error("Failed to certify.", "Error");
                    $button.prop("disabled", false);
                });
        }

        function loadReconcile() {
            fetch(LOB_RECONCILE, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    const $panel = $("#lob_reconcile").removeClass("alert-success alert-danger").addClass("d-none");
                    if (!data.success || !data.checked) {
                        return;
                    }
                    if (!data.differences.length) {
                        $panel.removeClass("d-none").addClass("alert-success").text(`Ledger check: all ${data.checked} certified balances match the current balances.`);
                        return;
                    }
                    const items = data.differences.map(d => `<li><strong>${lobEscape(d.employee)}</strong> ${lobEscape(d.code)}: ledger ${lobThree(d.ledger)}, balance ${lobThree(d.balance)}</li>`).join("");
                    $panel.removeClass("d-none").addClass("alert-danger").html(`<strong>Ledger check: ${data.differences.length} of ${data.checked} balances do not match.</strong><ul class="mb-0 mt-1">${items}</ul>`);
                })
                .catch(err => console.error(err));
        }
    </script>
@stop
