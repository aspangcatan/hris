@extends('master')
@section('content')
    <div class="page-head">
        <div>
            <h1>Pay Computations</h1>
            <p class="page-sub">How each shift's rates are worked out. Daily Base = Basic ÷ divisor days, Hourly = Daily Base ÷ standard hours, Minute = Hourly ÷ 60. A day is charged at Hourly × its scheduled hours.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btn_add_computation">
            <i class="bi bi-plus-lg me-1"></i> Add computation
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5 class="fw-bold card-title">Computations</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th class="text-center" title="A fixed standard used to work out the daily rate. It is not a count of the days in a particular month.">Divisor (days)</th>
                        <th class="text-center">Standard hours a day</th>
                        <th class="text-center">Hours a month</th>
                        <th class="text-center">Shifts</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="pc_body">
                    <tr><td colspan="6" class="text-muted text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="fw-bold card-title">Shifts</h5>
            <p class="small text-muted">A shift left on Default uses the default computation. Changes apply to cutoffs generated from now on; finalized cutoffs keep what they used.</p>
            <div class="table-responsive" style="max-height: 55vh;">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Shift</th>
                        <th class="text-center">Scheduled hours</th>
                        <th style="width: 34%">Computation</th>
                    </tr>
                    </thead>
                    <tbody id="pc_shift_body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pc_modal" tabindex="-1" aria-labelledby="pcModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-heading">
                        <span class="modal-icon"><i class="bi bi-calculator"></i></span>
                        <div>
                            <h4 class="modal-title" id="pcModalLabel">Add Computation</h4>
                            <div class="modal-sub">Applies to cutoffs generated from now on.</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="pc_name">Name</label>
                        <input type="text" class="form-control" id="pc_name" maxlength="100" placeholder="e.g. 40 hours per week computation">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="pc_days" title="A fixed standard used to work out the daily rate. It is not a count of the days in a particular month.">Divisor (days)</label>
                            <input type="number" class="form-control" id="pc_days" min="1" max="31" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pc_hours">Standard hours a day</label>
                            <input type="number" class="form-control" id="pc_hours" min="0.5" max="24" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pc_remarks">Remarks</label>
                        <textarea class="form-control" id="pc_remarks" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pc_default">
                        <label class="form-check-label" for="pc_default">Default for shifts with no computation set</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-modal-save" id="btn_pc_save">Save</button>
                </div>
            </div>
        </div>
    </div>
@stop
@section('js')
    <script>
        const PC_LIST = "{{ route('pay_computations.list') }}";
        const PC_SAVE = "{{ route('pay_computations.save') }}";
        const PC_ASSIGN = "{{ route('pay_computations.assign') }}";
        const PC_BASE = "{{ url('/pay-computations') }}";
        const PC_HEADERS = {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}"};

        let pcComputations = [];
        let pcEditingId = null;
        const pcEscape = text => $("<div>").text(text ?? "").html();
        const pcNumber = value => Number(value ?? 0).toLocaleString("en-US", {maximumFractionDigits: 2});

        $(document).ready(function () {
            loadPc();
            $("#btn_add_computation").on("click", () => openPc(null));
            $("#btn_pc_save").on("click", savePc);
            $(document).on("click", ".btn-pc-edit", function () {
                openPc(pcComputations.find(c => c.id === $(this).data("id")));
            });
            $(document).on("click", ".btn-pc-delete", function () {
                deletePc($(this).data("id"));
            });
            $(document).on("change", ".pc-shift-select", function () {
                assignPc($(this).data("shift"), $(this).val());
            });
        });

        function loadPc() {
            fetch(PC_LIST, {headers: {"Accept": "application/json"}})
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        toastr.error(data.message || "Failed to load.", "Error");
                        return;
                    }
                    pcComputations = data.computations;
                    $("#pc_body").html(pcComputations.map(c => `
                        <tr>
                            <td>${pcEscape(c.name)} ${c.is_default ? '<span class="badge bg-primary ms-1">Default</span>' : ""}
                                ${c.remarks ? `<div class="small text-muted">${pcEscape(c.remarks)}</div>` : ""}</td>
                            <td class="text-center">${pcNumber(c.working_days_per_month)}</td>
                            <td class="text-center">${pcNumber(c.standard_hours_per_day)}</td>
                            <td class="text-center">${pcNumber(c.working_days_per_month * c.standard_hours_per_day)}</td>
                            <td class="text-center">${c.shifts}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-action btn-edit btn-pc-edit" data-id="${c.id}" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                ${c.is_default ? "" : `<button type="button" class="btn btn-action btn-delete btn-pc-delete" data-id="${c.id}" title="Delete"><i class="bi bi-trash"></i></button>`}
                            </td>
                        </tr>`).join(""));

                    const options = selected => `<option value="">Default</option>` + pcComputations.map(c =>
                        `<option value="${c.id}" ${c.id === selected ? "selected" : ""}>${pcEscape(c.name)}</option>`).join("");
                    $("#pc_shift_body").html(data.shifts.map(s => `
                        <tr>
                            <td>${pcEscape(s.code)}</td>
                            <td>${pcEscape(s.label)}</td>
                            <td class="text-center">${pcNumber(s.hours)}</td>
                            <td><select class="form-select form-select-sm pc-shift-select" data-shift="${s.id}">${options(s.pay_computation_id)}</select></td>
                        </tr>`).join(""));
                })
                .catch(() => toastr.error("Failed to load.", "Error"));
        }

        function openPc(computation) {
            pcEditingId = computation ? computation.id : null;
            $("#pcModalLabel").text(computation ? "Edit Computation" : "Add Computation");
            $("#pc_name").val(computation ? computation.name : "");
            $("#pc_days").val(computation ? Number(computation.working_days_per_month) : 22);
            $("#pc_hours").val(computation ? Number(computation.standard_hours_per_day) : 8);
            $("#pc_remarks").val(computation ? (computation.remarks || "") : "");
            $("#pc_default").prop("checked", !!(computation && computation.is_default));
            $("#pc_modal").modal("show");
        }

        function savePc() {
            const $button = $("#btn_pc_save").prop("disabled", true);
            fetch(PC_SAVE, {
                method: "POST",
                headers: PC_HEADERS,
                body: JSON.stringify({
                    id: pcEditingId,
                    name: ($("#pc_name").val() || "").trim(),
                    working_days_per_month: $("#pc_days").val(),
                    standard_hours_per_day: $("#pc_hours").val(),
                    remarks: $("#pc_remarks").val(),
                    is_default: $("#pc_default").is(":checked"),
                }),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message, "Information");
                        $("#pc_modal").modal("hide");
                        loadPc();
                    } else {
                        toastr.error(data.message || "Failed to save.", "Error");
                    }
                })
                .catch(() => toastr.error("Failed to save.", "Error"))
                .finally(() => $button.prop("disabled", false));
        }

        function deletePc(id) {
            if (!confirm("Delete this computation?")) {
                return;
            }
            fetch(`${PC_BASE}/${id}`, {method: "DELETE", headers: PC_HEADERS})
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message, "Information");
                        loadPc();
                    } else {
                        toastr.error(data.message || "Failed to delete.", "Error");
                    }
                })
                .catch(() => toastr.error("Failed to delete.", "Error"));
        }

        function assignPc(shiftId, computationId) {
            fetch(PC_ASSIGN, {
                method: "POST",
                headers: PC_HEADERS,
                body: JSON.stringify({shift_id: shiftId, pay_computation_id: computationId || null}),
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message, "Information");
                        loadPc();
                    } else {
                        toastr.error(data.message || "Failed to update the shift.", "Error");
                    }
                })
                .catch(() => toastr.error("Failed to update the shift.", "Error"));
        }
    </script>
@stop
