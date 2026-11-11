@extends('master')
@section('content')
    <div class="page-head">
        <div>
            <h1>Taxation</h1>
            <p class="page-sub">Withholding tax brackets by annual taxable income, rate and purpose. Payroll uses the active rules for the payroll's purpose.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btn_add_tax_rule">
            <i class="bi bi-plus-lg me-1"></i> Add rule
        </button>
    </div>

    <div class="tax-board">

        <div class="table-responsive tax-table-shell">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th style="width: 20%;">Purpose</th>
                    <th style="width: 12%;">Min Annual</th>
                    <th style="width: 12%;">Max Annual</th>
                    <th style="width: 10%;">Rate (%)</th>
                    <th style="width: 12%;">Mode</th>
                    <th style="width: 10%;">Active</th>
                    <th style="width: 10%;"></th>
                </tr>
                </thead>
                <tbody id="tax_rules_body"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="tax_rule_modal" tabindex="-1" aria-labelledby="taxRuleModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-percent"></i></div>
                        <div>
                            <h5 class="modal-title" id="taxRuleModalLabel">Add Tax Rule</h5>
                            <p class="modal-sub">One bracket of annual taxable income and its rate.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <input type="hidden" id="tax_rule_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="tax_purpose_input" placeholder="Payroll Monthly Salary">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Annual</label>
                            <input type="number" class="form-control" id="tax_min_annual" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Annual</label>
                            <input type="number" class="form-control" id="tax_max_annual" placeholder="Leave blank for no limit">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rate (%)</label>
                            <input type="number" class="form-control" id="tax_rate_percent" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mode</label>
                            <select class="form-control" id="tax_mode">
                                <option value="flat">Flat</option>
                                <option value="progressive">Progressive</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="tax_active" checked>
                                <label class="form-check-label" for="tax_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" id="tax_remarks" placeholder="Optional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-modal-save" id="btn_save_tax_rule">Save rule</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let taxRules = [];

        $(document).ready(function () {
            loadTaxRules();

            $("#btn_add_tax_rule").click(function () {
                openTaxRuleModal();
            });

            $("#btn_save_tax_rule").click(function () {
                saveTaxRule();
            });

            $(document).on("click", ".btn-edit-tax", function () {
                const id = $(this).data("id");
                const rule = taxRules.find(item => item.id === id);
                if (!rule) {
                    toastr.error("Tax rule not found.", "Error");
                    return;
                }
                openTaxRuleModal(rule);
            });

            $(document).on("click", ".btn-disable-tax", function () {
                const id = $(this).data("id");
                const rule = taxRules.find(item => item.id === id);
                if (!rule) {
                    return;
                }
                rule.is_active = 0;
                submitTaxRules([rule]);
            });
        });

        function loadTaxRules() {
            fetch('{{ route('getPayrollTaxRules') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    taxRules = data.data || [];
                    renderTaxRules();
                })
                .catch(err => console.error(err));
        }

        function renderTaxRules() {
            const $body = $("#tax_rules_body");
            $body.empty();

            if (!taxRules.length) {
                $body.append("<tr><td colspan='7' class='text-muted text-center py-3'>No tax rules configured.</td></tr>");
                return;
            }

            taxRules.forEach(rule => {
                const maxAnnual = rule.max_annual !== null ? parseFloat(rule.max_annual).toFixed(2) : "∞";
                const minAnnual = parseFloat(rule.min_annual || 0).toFixed(2);
                const rate = parseFloat(rule.rate_percent || 0).toFixed(2);
                const mode = rule.tax_mode ? rule.tax_mode.toUpperCase() : "FLAT";
                const active = rule.is_active ? "Yes" : "No";

                const row = `
                    <tr>
                        <td>${rule.purpose}</td>
                        <td>${minAnnual}</td>
                        <td>${maxAnnual}</td>
                        <td>${rate}</td>
                        <td>${mode}</td>
                        <td>${active}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-action btn-edit btn-edit-tax" data-id="${rule.id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-action btn-delete btn-disable-tax" data-id="${rule.id}" title="Deactivate">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    </tr>`;
                $body.append(row);
            });
        }

        function openTaxRuleModal(rule = null) {
            $("#taxRuleModalLabel").text(rule ? "Edit Tax Rule" : "Add Tax Rule");
            $("#tax_rule_id").val(rule ? rule.id : "");
            $("#tax_purpose_input").val(rule ? rule.purpose : "");
            $("#tax_min_annual").val(rule ? rule.min_annual : 0);
            $("#tax_max_annual").val(rule && rule.max_annual !== null ? rule.max_annual : "");
            $("#tax_rate_percent").val(rule ? rule.rate_percent : 0);
            $("#tax_mode").val(rule ? rule.tax_mode : "flat");
            $("#tax_active").prop("checked", rule ? !!rule.is_active : true);
            $("#tax_remarks").val(rule ? (rule.remarks || "") : "");
            $("#tax_rule_modal").modal("show");
        }

        function saveTaxRule() {
            const id = $("#tax_rule_id").val();
            const purpose = ($("#tax_purpose_input").val() || "").trim();
            const minAnnual = $("#tax_min_annual").val();
            const maxAnnual = $("#tax_max_annual").val();
            const ratePercent = $("#tax_rate_percent").val();
            const mode = $("#tax_mode").val();
            const active = $("#tax_active").is(":checked");
            const remarks = ($("#tax_remarks").val() || "").trim();

            if (!purpose) {
                toastr.error("Purpose is required.", "Validation");
                return;
            }

            const payload = [{
                id: id || null,
                purpose: purpose,
                min_annual: minAnnual === "" ? 0 : parseFloat(minAnnual),
                max_annual: maxAnnual === "" ? null : parseFloat(maxAnnual),
                rate_percent: ratePercent === "" ? 0 : parseFloat(ratePercent),
                tax_mode: mode,
                is_active: active,
                remarks: remarks
            }];

            submitTaxRules(payload);
        }

        function submitTaxRules(rules) {
            fetch('{{ route('savePayrollTaxRules') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({rules: rules})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Tax rule saved.", "Information");
                        $("#tax_rule_modal").modal("hide");
                        loadTaxRules();
                    } else {
                        toastr.error(data.message || "Failed to save tax rule.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to save tax rule.", "Error");
                });
        }
    </script>
@stop
