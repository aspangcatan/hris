@extends('master')
@section('content')
    <style>
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
    </style>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1">Earnings &amp; Deductions</h4>
            <div class="text-muted small">Manage global payroll components applied by default.</div>
        </div>
        <button class="btn btn-success btn-edit" id="btn_add_component">
            <i class="bi bi-plus-circle me-1"></i>
            Add Component
        </button>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold card-title mb-0">Earnings</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 18%;">Code</th>
                                <th>Name</th>
                                <th style="width: 12%;">Taxable</th>
                                <th style="width: 18%;">Default</th>
                                <th style="width: 14%;">Active</th>
                                <th style="width: 10%;"></th>
                            </tr>
                            </thead>
                            <tbody id="earnings_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold card-title mb-0">Deductions</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 18%;">Code</th>
                                <th>Name</th>
                                <th style="width: 12%;">Taxable</th>
                                <th style="width: 18%;">Default</th>
                                <th style="width: 14%;">Active</th>
                                <th style="width: 10%;"></th>
                            </tr>
                            </thead>
                            <tbody id="deductions_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="component_modal" tabindex="-1" aria-labelledby="componentModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="componentModalLabel">Add Component</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" id="component_code">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" id="component_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select class="form-control" id="component_type">
                                <option value="earning">Earnings</option>
                                <option value="deduction">Deductions</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Amount</label>
                            <input type="number" class="form-control" id="component_default" value="0.00">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="component_taxable">
                                <label class="form-check-label" for="component_taxable">Taxable</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="component_active" checked>
                                <label class="form-check-label" for="component_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_save_component">Save Component</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let earnings = [];
        let deductions = [];
        let editMode = false;
        let editCode = "";

        $(document).ready(function () {
            loadComponents();

            $("#btn_add_component").click(function () {
                openComponentModal();
            });

            $("#btn_save_component").click(function () {
                saveComponent();
            });

            $(document).on("click", ".btn-edit-component", function () {
                const code = $(this).data("code");
                const type = $(this).data("type");
                const component = findComponent(code, type);
                if (!component) {
                    toastr.error("Component not found.", "Error");
                    return;
                }
                openComponentModal(component);
            });

            $(document).on("click", ".btn-remove-component", function () {
                const code = $(this).data("code");
                const type = $(this).data("type");
                const component = findComponent(code, type);
                if (!component) {
                    return;
                }
                component.is_active = 0;
                submitComponents([component]);
            });
        });

        function loadComponents() {
            fetch('{{ route('getPayrollComponents') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    const items = data.data || [];
                    earnings = items.filter(item => item.type === 'earning' && item.is_global);
                    deductions = items.filter(item => item.type === 'deduction' && item.is_global);
                    renderComponents();
                })
                .catch(err => console.error(err));
        }

        function renderComponents() {
            const $earnings = $("#earnings_table_body");
            const $deductions = $("#deductions_table_body");
            $earnings.empty();
            $deductions.empty();

            if (!earnings.length) {
                $earnings.append("<tr><td colspan='6' class='text-muted text-center py-3'>No earnings configured.</td></tr>");
            } else {
                earnings.forEach(component => {
                    $earnings.append(renderRow(component));
                });
            }

            if (!deductions.length) {
                $deductions.append("<tr><td colspan='6' class='text-muted text-center py-3'>No deductions configured.</td></tr>");
            } else {
                deductions.forEach(component => {
                    $deductions.append(renderRow(component));
                });
            }
        }

        function renderRow(component) {
            const taxable = component.taxable ? "Yes" : "No";
            const active = component.is_active ? "Yes" : "No";
            const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
                ? parseFloat(component.default_amount).toFixed(2)
                : "0.00";

            return `
                <tr>
                    <td>${component.code}</td>
                    <td>${component.name}</td>
                    <td>${taxable}</td>
                    <td>${defaultAmount}</td>
                    <td>${active}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-action btn-edit btn-edit-component" data-code="${component.code}" data-type="${component.type}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-action btn-delete btn-remove-component" data-code="${component.code}" data-type="${component.type}" title="Deactivate">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </td>
                </tr>`;
        }

        function openComponentModal(component = null) {
            editMode = !!component;
            editCode = component ? component.code : "";
            $("#componentModalLabel").text(editMode ? "Edit Component" : "Add Component");
            $("#component_code").val(component ? component.code : "").prop("disabled", editMode);
            $("#component_name").val(component ? component.name : "");
            $("#component_type").val(component ? component.type : "earning");
            $("#component_default").val(component ? (component.default_amount ?? 0) : "0.00");
            $("#component_taxable").prop("checked", !!(component && component.taxable));
            $("#component_active").prop("checked", component ? !!component.is_active : true);
            $("#component_modal").modal("show");
        }

        function saveComponent() {
            const code = ($("#component_code").val() || "").trim().toUpperCase();
            const name = ($("#component_name").val() || "").trim();
            const type = $("#component_type").val();
            const taxable = $("#component_taxable").is(":checked");
            const active = $("#component_active").is(":checked");
            const defaultRaw = $("#component_default").val();
            const defaultAmount = defaultRaw === "" ? 0 : parseFloat(defaultRaw);

            if (!code || !name) {
                toastr.error("Code and Name are required.", "Validation");
                return;
            }

            const payload = {
                code: code,
                name: name,
                type: type,
                taxable: taxable,
                default_amount: isNaN(defaultAmount) ? 0 : defaultAmount,
                is_global: true,
                is_active: active
            };

            submitComponents([payload]);
        }

        function submitComponents(payload) {
            fetch('{{ route('savePayrollComponents') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({components: payload})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Component saved.", "Information");
                        $("#component_modal").modal("hide");
                        loadComponents();
                    } else {
                        toastr.error(data.message || "Failed to save component.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to save component.", "Error");
                });
        }

        function findComponent(code, type) {
            const list = type === "deduction" ? deductions : earnings;
            return list.find(item => item.code === code);
        }
    </script>
@stop
