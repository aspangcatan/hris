@extends('master')
@section('content')
    <div class="page-head">
        <div>
            <h1>Payroll Assignatories</h1>
            <p class="page-sub">The approval phases a payroll goes through after it is submitted, in order, and who approves each one.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btn_add_assignatory">
            <i class="bi bi-plus-lg me-1"></i> Add phase
        </button>
    </div>

    <div class="assignatory-board">

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

    <div class="modal fade" id="assignatory_modal" tabindex="-1" aria-labelledby="assignatoryModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-person-check"></i></div>
                        <div>
                            <h5 class="modal-title" id="assignatoryModalLabel">Add Phase</h5>
                            <p class="modal-sub">One approval step and the person who acts on it.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
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
                            <select id="assignatory_user_id" class="form-control" style="width: 100%;"></select>
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
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-modal-save" id="btn_save_assignatory">Save phase</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let assignatories = [];

        $(document).ready(function () {
            $('#assignatory_user_id').select2({
                dropdownParent: $('#assignatory_modal'),
                placeholder: 'Search by name...',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: '{{ route('searchPayrollAssignatoryUsers') }}',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {q: params.term};
                    },
                    processResults: function (data) {
                        return {
                            results: (data.data || []).map(function (user) {
                                return {id: user.id, text: user.lname + ', ' + user.fname};
                            })
                        };
                    },
                    cache: true
                }
            });

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

            $("#assignatory_user_id").empty();
            if (row && row.user_id) {
                const text = row.fname ? `${row.lname}, ${row.fname}` : `User #${row.user_id}`;
                const option = new Option(text, row.user_id, true, true);
                $("#assignatory_user_id").append(option).trigger('change');
            } else {
                $("#assignatory_user_id").val(null).trigger('change');
            }

            $("#assignatory_modal").modal("show");
        }

        function saveAssignatory() {
            const id = $("#assignatory_id").val();
            const orderNo = parseInt($("#assignatory_order").val() || "1", 10);
            const label = ($("#assignatory_label").val() || "").trim();
            const userId = $("#assignatory_user_id").val();
            const active = $("#assignatory_active").is(":checked");

            if (!label) {
                toastr.error("Label is required.", "Validation");
                return;
            }
            if (!userId) {
                toastr.error("Please select an assigned user from the search results.", "Validation");
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
