@extends('master')
@section('content')
    <div class="page-head">
        <div>
            <h1>Earnings &amp; Deductions</h1>
            <p class="page-sub">Manage all payroll components. Mark a component Global to apply it to every employee by default; leave it non-global to assign it per-employee instead.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btn_add_component">
            <i class="bi bi-plus-lg me-1"></i> Add component
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_earnings" role="tab">
                        Earnings <span class="badge bg-secondary ms-1" id="earnings_count">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_deductions" role="tab">
                        Deductions <span class="badge bg-secondary ms-1" id="deductions_count">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_employer_shares" role="tab">
                        Employer Shares <span class="badge bg-secondary ms-1" id="employer_shares_count">0</span>
                    </button>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_earnings" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 14%;">Code</th>
                                <th>Name</th>
                                <th style="width: 8%;">Taxable</th>
                                <th style="width: 8%;">Global</th>
                                <th style="width: 13%;">Default</th>
                                <th style="width: 16%;" title="Who gets it, and how it is reduced for days on leave, absent or LWOP">Rules</th>
                                <th style="width: 7%;">Active</th>
                                <th style="width: 10%;">Adjustable per Cutoff</th>
                                <th style="width: 8%;"></th>
                            </tr>
                            </thead>
                            <tbody id="earnings_table_body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab_deductions" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 14%;">Code</th>
                                <th>Name</th>
                                <th style="width: 8%;" title="Taken off the income that withholding tax is computed on">Before tax</th>
                                <th style="width: 8%;">Global</th>
                                <th style="width: 13%;">Default</th>
                                <th style="width: 16%;">Rules</th>
                                <th style="width: 7%;">Active</th>
                                <th style="width: 10%;">Adjustable per Cutoff</th>
                                <th style="width: 8%;"></th>
                            </tr>
                            </thead>
                            <tbody id="deductions_table_body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab_employer_shares" role="tabpanel">
                    <p class="text-muted small">The government's own share (e.g. GSIS Government Share, EC). Recorded with each payroll for remittance; never deducted from the employee's pay.</p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 14%;">Code</th>
                                <th>Name</th>
                                <th style="width: 8%;">&nbsp;</th>
                                <th style="width: 8%;">Global</th>
                                <th style="width: 13%;">Default</th>
                                <th style="width: 16%;">Rules</th>
                                <th style="width: 7%;">Active</th>
                                <th style="width: 10%;">Adjustable per Cutoff</th>
                                <th style="width: 8%;"></th>
                            </tr>
                            </thead>
                            <tbody id="employer_shares_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="component_modal" tabindex="-1" aria-labelledby="componentModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-sliders"></i></div>
                        <div>
                            <h5 class="modal-title" id="componentModalLabel">Add Component</h5>
                            <p class="modal-sub">An earning or deduction, with its default amount or formula.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <option value="employer_share">Employer Share (not deducted from pay)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Computation Type</label>
                            <select class="form-control" id="component_calc_type">
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="component_default_wrap">
                            <label class="form-label">Default Amount</label>
                            <input type="number" class="form-control" id="component_default" value="0.00">
                        </div>
                        <div class="col-md-4 d-none" id="component_base_wrap">
                            <label class="form-label">Base</label>
                            <select class="form-control" id="component_base">
                                <option value="__BASIC_SALARY__">Basic Salary</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="component_rate_wrap">
                            <label class="form-label">Percentage Rate (%)</label>
                            <input type="number" class="form-control" id="component_rate" value="0.00" step="0.001">
                        </div>
                        <div class="col-md-2 d-flex align-items-end" id="component_taxable_wrap">
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
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="component_cutoff_adjustable">
                                <label class="form-check-label" for="component_cutoff_adjustable">Adjustable per Cutoff</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="component_global">
                                <label class="form-check-label" for="component_global">Global (applies to every employee)</label>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="component_permanent_only">
                                <label class="form-check-label" for="component_permanent_only">Permanent employees only</label>
                            </div>
                        </div>
                        <div class="col-md-4" id="component_reduction_wrap">
                            <label class="form-label">Reduce for days on leave, absent or LWOP</label>
                            <select class="form-control" id="component_reduction">
                                <option value="none">No reduction</option>
                                <option value="prorate">Prorate (amount &divide; working days, per day)</option>
                                <option value="per_day">Fixed amount per day</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="component_reduction_rate_wrap">
                            <label class="form-label">Amount per day</label>
                            <input type="number" class="form-control" id="component_reduction_rate" value="0.00" step="0.01" min="0">
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">When checked, this component gets a quick manual-entry field in the Payroll module's Review &amp; Compute step for every cutoff.</p>
                    <p class="text-muted small mt-1 mb-0">Permanent employees only: skipped for every other employee type (Temporary, Job Order, COS...). A leave reduction is computed when the cutoff snapshot is generated, from paid leave, absence and LWOP days, and shows as its own line.</p>
                    <p class="text-muted small mt-1 mb-0">Global components are auto-applied to every employee at the default amount/formula above. Leave unchecked for components that vary per employee (loan balances, contribution tiers, etc.) — those are assigned individually from the employee's Payroll tab.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-modal-save" id="btn_save_component">Save component</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let earnings = [];
        let deductions = [];
        let employerShares = [];
        let editMode = false;
        let editCode = "";

        $(document).ready(function () {
            loadComponents();

            $("#component_base").select2({
                dropdownParent: $("#component_modal"),
                width: "100%"
            });

            $("#component_calc_type").on("change", function () {
                toggleComputationFields();
            });

            $("#component_reduction").on("change", toggleReductionFields);

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
                    earnings = items.filter(item => item.type === 'earning');
                    deductions = items.filter(item => item.type === 'deduction');
                    employerShares = items.filter(item => item.type === 'employer_share');
                    renderComponents();
                })
                .catch(err => console.error(err));
        }

        function renderComponents() {
            const $earnings = $("#earnings_table_body");
            const $deductions = $("#deductions_table_body");
            const $employerShares = $("#employer_shares_table_body");
            $earnings.empty();
            $deductions.empty();
            $employerShares.empty();
            $("#earnings_count").text(earnings.length);
            $("#deductions_count").text(deductions.length);
            $("#employer_shares_count").text(employerShares.length);

            if (!employerShares.length) {
                $employerShares.append("<tr><td colspan='9' class='text-muted text-center py-3'>No employer shares configured.</td></tr>");
            } else {
                employerShares.forEach(component => {
                    $employerShares.append(renderRow(component));
                });
            }

            if (!earnings.length) {
                $earnings.append("<tr><td colspan='9' class='text-muted text-center py-3'>No earnings configured.</td></tr>");
            } else {
                earnings.forEach(component => {
                    $earnings.append(renderRow(component));
                });
            }

            if (!deductions.length) {
                $deductions.append("<tr><td colspan='9' class='text-muted text-center py-3'>No deductions configured.</td></tr>");
            } else {
                deductions.forEach(component => {
                    $deductions.append(renderRow(component));
                });
            }
        }

        function renderRow(component) {
            const taxable = component.type === "employer_share" ? "&mdash;" : (component.taxable ? "Yes" : "No");
            const global = component.is_global ? "Yes" : "No";
            const active = component.is_active ? "Yes" : "No";
            const adjustable = component.is_cutoff_adjustable ? "Yes" : "No";
            const defaultDisplay = component.calculation_type === "percentage"
                ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                : (component.default_amount !== undefined && component.default_amount !== null ? parseFloat(component.default_amount).toFixed(2) : "0.00");

            return `
                <tr>
                    <td>${component.code}</td>
                    <td>${component.name}</td>
                    <td>${taxable}</td>
                    <td>${global}</td>
                    <td>${defaultDisplay}</td>
                    <td>${rulesDisplay(component)}</td>
                    <td>${active}</td>
                    <td>${adjustable}</td>
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

        function rulesDisplay(component) {
            const rules = [];
            if (parseInt(component.permanent_only, 10)) {
                rules.push('<span class="badge bg-info text-dark">Permanent only</span>');
            }
            if (component.absence_reduction === "prorate") {
                rules.push('<span class="badge bg-light text-dark border">Prorated on days off</span>');
            } else if (component.absence_reduction === "per_day") {
                rules.push(`<span class="badge bg-light text-dark border">Less ${parseFloat(component.absence_reduction_rate || 0).toFixed(2)} / day off</span>`);
            }
            return rules.length ? rules.join(" ") : '<span class="text-muted">&mdash;</span>';
        }

        function toggleReductionFields() {
            const isEarning = $("#component_type").val() === "earning";
            $("#component_reduction_wrap").toggleClass("d-none", !isEarning);
            $("#component_reduction_rate_wrap").toggleClass("d-none", !isEarning || $("#component_reduction").val() !== "per_day");
        }

        function toggleComputationFields() {
            const isPercentage = $("#component_calc_type").val() === "percentage";
            $("#component_default_wrap").toggleClass("d-none", isPercentage);
            $("#component_base_wrap").toggleClass("d-none", !isPercentage);
            $("#component_rate_wrap").toggleClass("d-none", !isPercentage);
        }

        // On a deduction this flag means it comes off taxable income before withholding tax.
        // An employer share is never part of the employee's pay, so it has no tax flag.
        function updateTaxableLabel() {
            const type = $("#component_type").val();
            const isDeduction = type === "deduction";
            $("#component_taxable_wrap").toggleClass("d-none", type === "employer_share");
            $("label[for='component_taxable']")
                .text(isDeduction ? "Before tax" : "Taxable")
                .attr("title", isDeduction ? "Taken off the income that withholding tax is computed on" : "");
            toggleReductionFields();
        }
        $(document).on("change", "#component_type", updateTaxableLabel);

        function openComponentModal(component = null) {
            editMode = !!component;
            editCode = component ? component.code : "";
            $("#componentModalLabel").text(editMode ? "Edit Component" : "Add Component");
            $("#component_code").val(component ? component.code : "").prop("disabled", editMode);
            $("#component_name").val(component ? component.name : "");
            $("#component_type").val(component ? component.type : "earning");
            $("#component_permanent_only").prop("checked", !!(component && parseInt(component.permanent_only, 10)));
            $("#component_reduction").val(component && component.absence_reduction ? component.absence_reduction : "none");
            $("#component_reduction_rate").val(component && component.absence_reduction_rate !== null && component.absence_reduction_rate !== undefined ? component.absence_reduction_rate : "0.00");
            updateTaxableLabel();
            $("#component_default").val(component ? (component.default_amount ?? 0) : "0.00");
            $("#component_taxable").prop("checked", !!(component && component.taxable));
            $("#component_active").prop("checked", component ? !!component.is_active : true);
            $("#component_cutoff_adjustable").prop("checked", !!(component && component.is_cutoff_adjustable));
            $("#component_global").prop("checked", !!(component && component.is_global));

            const $base = $("#component_base");
            $base.empty().append('<option value="__BASIC_SALARY__">Basic Salary</option>');
            earnings.concat(deductions, employerShares)
                .filter(item => item.code !== editCode)
                .forEach(item => {
                    $base.append(`<option value="${item.code}">${item.code} - ${item.name}</option>`);
                });

            const calcType = component && component.calculation_type === "percentage" ? "percentage" : "fixed";
            $("#component_calc_type").val(calcType);

            const baseValue = component && component.base_type === "component"
                ? component.base_component_code
                : "__BASIC_SALARY__";
            $base.val(baseValue).trigger("change");

            $("#component_rate").val(component && component.rate_percent !== null && component.rate_percent !== undefined ? component.rate_percent : "0.00");

            toggleComputationFields();
            $("#component_modal").modal("show");
        }

        function saveComponent() {
            const code = ($("#component_code").val() || "").trim().toUpperCase();
            const name = ($("#component_name").val() || "").trim();
            const type = $("#component_type").val();
            const taxable = $("#component_taxable").is(":checked");
            const active = $("#component_active").is(":checked");
            const cutoffAdjustable = $("#component_cutoff_adjustable").is(":checked");
            const isGlobal = $("#component_global").is(":checked");
            const defaultRaw = $("#component_default").val();
            const defaultAmount = defaultRaw === "" ? 0 : parseFloat(defaultRaw);
            const calculationType = $("#component_calc_type").val() === "percentage" ? "percentage" : "fixed";
            const baseValue = $("#component_base").val();
            const ratePercent = parseFloat($("#component_rate").val());
            const reduction = type === "earning" ? $("#component_reduction").val() : "none";
            const reductionRate = parseFloat($("#component_reduction_rate").val());

            if (!code || !name) {
                toastr.error("Code and Name are required.", "Validation");
                return;
            }

            if (reduction === "per_day" && (isNaN(reductionRate) || reductionRate < 0)) {
                toastr.error("Enter a valid, non-negative amount per day off.", "Validation");
                return;
            }

            if (calculationType === "percentage" && (isNaN(ratePercent) || ratePercent < 0)) {
                toastr.error("Enter a valid, non-negative percentage rate.", "Validation");
                return;
            }

            const payload = {
                code: code,
                name: name,
                type: type,
                taxable: type === "employer_share" ? false : taxable,
                permanent_only: $("#component_permanent_only").is(":checked"),
                absence_reduction: reduction,
                absence_reduction_rate: reduction === "per_day" ? reductionRate : null,
                default_amount: isNaN(defaultAmount) ? 0 : defaultAmount,
                is_global: isGlobal,
                is_active: active,
                is_cutoff_adjustable: cutoffAdjustable,
                calculation_type: calculationType,
                base_type: calculationType === "percentage"
                    ? (baseValue === "__BASIC_SALARY__" ? "basic_salary" : "component")
                    : null,
                base_component_code: calculationType === "percentage" && baseValue !== "__BASIC_SALARY__" ? baseValue : null,
                rate_percent: calculationType === "percentage" ? ratePercent : null
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
            const list = type === "deduction" ? deductions : (type === "employer_share" ? employerShares : earnings);
            return list.find(item => item.code === code);
        }
    </script>
@stop
