@extends('master')
@section('content')
    <style>
        .assignatory-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .assignatory-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .assignatory-head h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .assignatory-head span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .assignatory-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .assignatory-table-shell table {
            margin-bottom: 0;
        }

        .assignatory-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .assignatory-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        .payroll-modal-theme .modal-content {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.2);
        }

        .payroll-modal-theme .modal-header {
            background: linear-gradient(135deg, #0f766e, #0ea5a4);
            color: #fff;
            border: 0;
        }

        .payroll-modal-theme .modal-title {
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .payroll-modal-theme .btn-close {
            filter: invert(1);
            opacity: 0.9;
        }

        .payroll-modal-theme .modal-body {
            background: #f8fafc;
        }

        /* dropdownParent: 'body' floats the Selectize dropdown above the
           modal instead of being clipped by modal-body's overflow-y, but
           that also lifts it out of the modal's stacking context, so it
           needs a z-index above Bootstrap's modal (1055) and backdrop
           (1050) or it renders behind them - only the portion spilling
           past the modal's edge would otherwise be visible. */
        .selectize-dropdown {
            z-index: 2000 !important;
        }

        /* Match Selectize's box to the plain .form-control inputs (Order,
           Label) in this modal - Selectize's own default padding/height
           don't line up with Bootstrap's, so without this it looks shorter
           and less padded than its siblings. */
        #assignatory_modal .selectize-control.single .selectize-input {
            min-height: calc(1.5em + 0.75rem + 2px);
            padding: 0.375rem 0.75rem;
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        #assignatory_modal .selectize-control.single .selectize-input input {
            padding: 0 !important;
            font-size: 1rem;
            line-height: 1.5;
        }

        #assignatory_modal .selectize-control.single .selectize-input.items {
            padding-right: 2rem;
        }
    </style>

    <div class="assignatory-board">
        <div class="assignatory-head">
            <div>
                <h4>Payroll Assignatories</h4>
                <span>Configure how many approval phases a payroll goes through after submission, and who approves each phase.</span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_assignatory">
                <i class="bi bi-plus-circle me-1"></i>
                Add Phase
            </button>
        </div>

        <div class="table-responsive assignatory-table-shell">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th style="width: 10%;">Order</th>
                    <th style="width: 25%;">Label</th>
                    <th style="width: 25%;">Assigned To</th>
                    <th style="width: 10%;">Active</th>
                    <th style="width: 10%;"></th>
                </tr>
                </thead>
                <tbody id="assignatories_body"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="assignatory_modal" tabindex="-1" aria-labelledby="assignatoryModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="assignatoryModalLabel">Add Phase</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <input type="hidden" id="assignatory_id">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Order</label>
                            <input type="number" class="form-control" id="assignatory_order" value="1" min="1">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-control" id="assignatory_label" placeholder="e.g. Section Head">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Assigned User</label>
                            <select id="assignatory_user_id" class="form-control" placeholder="Search by name..."></select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="assignatory_active" checked>
                                <label class="form-check-label" for="assignatory_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_save_assignatory">Save Phase</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let assignatories = [];
        let userSelectize = null;

        $(document).ready(function () {
            userSelectize = $("#assignatory_user_id").selectize({
                valueField: 'id',
                labelField: 'text',
                searchField: 'text',
                create: false,
                maxItems: 1,
                dropdownParent: 'body',
                loadThrottle: 300,
                load: function (query, callback) {
                    if (!query.length) {
                        return callback();
                    }
                    fetch('{{ route('searchPayrollAssignatoryUsers') }}?q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success) {
                                return callback();
                            }
                            callback(data.data.map(user => ({id: user.id, text: `${user.lname}, ${user.fname}`})));
                        })
                        .catch(() => callback());
                }
            })[0].selectize;

            loadAssignatories();

            $("#btn_add_assignatory").click(function () {
                openAssignatoryModal();
            });

            $("#btn_save_assignatory").click(function () {
                saveAssignatory();
            });

            $(document).on("click", ".btn-edit-assignatory", function () {
                const id = $(this).data("id");
                const row = assignatories.find(item => item.id === id);
                if (!row) {
                    toastr.error("Assignatory not found.", "Error");
                    return;
                }
                openAssignatoryModal(row);
            });

            $(document).on("click", ".btn-disable-assignatory", function () {
                const id = $(this).data("id");
                const row = assignatories.find(item => item.id === id);
                if (!row) {
                    return;
                }
                submitAssignatories([{
                    id: row.id,
                    label: row.label,
                    user_id: row.user_id,
                    order_no: row.order_no,
                    is_active: false
                }]);
            });

        });

        function loadAssignatories() {
            fetch('{{ route('getPayrollAssignatories') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    assignatories = data.data || [];
                    renderAssignatories();
                })
                .catch(err => console.error(err));
        }

        function renderAssignatories() {
            const $body = $("#assignatories_body");
            $body.empty();

            if (!assignatories.length) {
                $body.append("<tr><td colspan='5' class='text-muted text-center py-3'>No assignatories configured.</td></tr>");
                return;
            }

            assignatories.forEach(row => {
                const assignedName = row.fname ? `${row.lname}, ${row.fname}` : `User #${row.user_id}`;
                const active = row.is_active ? "Yes" : "No";

                const tr = `
                    <tr>
                        <td>${row.order_no}</td>
                        <td>${row.label}</td>
                        <td>${assignedName}</td>
                        <td>${active}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-action btn-edit btn-edit-assignatory" data-id="${row.id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-action btn-delete btn-disable-assignatory" data-id="${row.id}" title="Deactivate">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>`;
                $body.append(tr);
            });
        }

        function openAssignatoryModal(row = null) {
            $("#assignatoryModalLabel").text(row ? "Edit Phase" : "Add Phase");
            $("#assignatory_id").val(row ? row.id : "");
            $("#assignatory_order").val(row ? row.order_no : (assignatories.length + 1));
            $("#assignatory_label").val(row ? row.label : "");
            $("#assignatory_active").prop("checked", row ? !!row.is_active : true);

            userSelectize.clear(true);
            userSelectize.clearOptions();
            if (row && row.user_id) {
                const text = row.fname ? `${row.lname}, ${row.fname}` : `User #${row.user_id}`;
                userSelectize.addOption({id: row.user_id, text: text});
                userSelectize.setValue(row.user_id, true);
            }

            $("#assignatory_modal").modal("show");
        }

        function saveAssignatory() {
            const id = $("#assignatory_id").val();
            const orderNo = parseInt($("#assignatory_order").val() || "1", 10);
            const label = ($("#assignatory_label").val() || "").trim();
            const userId = userSelectize.getValue();
            const active = $("#assignatory_active").is(":checked");

            if (!label) {
                toastr.error("Label is required.", "Validation");
                return;
            }
            if (!userId) {
                toastr.error("Please select an assigned user.", "Validation");
                return;
            }

            submitAssignatories([{
                id: id || null,
                label: label,
                user_id: parseInt(userId, 10),
                order_no: orderNo,
                is_active: active
            }], true);
        }

        function submitAssignatories(rows, closeModal = false) {
            fetch('{{ route('savePayrollAssignatories') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({assignatories: rows})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Assignatory saved.", "Information");
                        if (closeModal) {
                            $("#assignatory_modal").modal("hide");
                        }
                        loadAssignatories();
                    } else {
                        toastr.error(data.message || "Failed to save assignatory.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to save assignatory.", "Error");
                });
        }
    </script>
@stop
