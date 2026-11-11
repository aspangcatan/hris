@extends('master')
@section('content')
    <style>
        /* Spacing only - colours, borders and table styling come from the theme. */
        .payroll-filter {
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .payroll-filter .btn {
            min-height: 100%;
        }
        .payroll-table-shell .table {
            margin-bottom: 0;
        }
        .payroll-employee-photo {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }
    </style>
    <div class="page-head">
        <div>
            <h1>Payroll Settings</h1>
            <p class="page-sub">Each employee's salary grade, step and basic salary, and which earnings and deductions apply to them.</p>
        </div>
    </div>

    <div class="payroll-board">

        <div class="payroll-filter">
            <div class="row g-2 align-items-stretch">
                <div class="col-md-5">
                    <input class="form-control" placeholder="Search by first name" id="search_fname"/>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" placeholder="Search by last name" id="search_lname"/>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100 h-100" id="btn_search">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive payroll-table-shell">
            <table class="table text-center">
                <thead>
                <tr>
                    <th></th>
                    <th>Firstname</th>
                    <th>Middlename</th>
                    <th>Lastname</th>
                    <th>Suffix</th>
                    <th>Designation</th>
                    <th>Section</th>
                    <th style="width: 16%">Actions</th>
                </tr>
                </thead>
                <tbody id="employee_list"></tbody>
            </table>
        </div>

        <div class="mt-3" id="pagination_container">
            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                <div id="page_items_count"></div>
                <nav aria-label="Payroll employee pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                </nav>
                <div id="page_indicator" class="small text-muted"></div>
            </div>
        </div>
    </div>

    @include('modals.payroll')
@stop
@section('js')
    <script>
        let page = 1;
        let payroll_last_page = 1;
        let employee_id = 0;
        let catalogEarnings = [];
        let catalogDeductions = [];
        let payrollCatalogLoaded = false;
        $(document).ready(function () {
            getEmployee({fname: "", lname: "", page: page});
            loadComponentCatalog();

            $("#btn_search").click(function () {
                page = 1;
                employee_id = 0;
                const fname = $("#search_fname").val().trim();
                const lname = $("#search_lname").val().trim();
                getEmployee({fname: fname, lname: lname, page: page});
            });

            $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
                e.preventDefault();
                const targetPage = parseInt($(this).attr("data-page"), 10);
                if (!targetPage || targetPage === page) return;
                if (targetPage < 1 || targetPage > payroll_last_page) return;
                page = targetPage;
                const fname = $("#search_fname").val().trim();
                const lname = $("#search_lname").val().trim();
                getEmployee({fname: fname, lname: lname, page: page});
            });

            $("#btn_save").click(function () {
                let isValid = true;
                let params = {};

                params["userid"] = employee_id;

                // Select all fixed input fields inside the modal
                $("#modal_salary_details input[id]").each(function () {
                    let input = $(this);
                    let name = input.attr("id");
                    let value = parseFloat(input.val());

                    // Check for invalid value (empty or less than 0)
                    if (isNaN(value) || value < 0) {
                        input.addClass("is-invalid"); // Optional: add red border
                        isValid = false;
                    } else {
                        input.removeClass("is-invalid");
                        if (name === 'salary_grade' || name === 'step') {
                            params[name] = parseInt(value); // keep as integer
                        } else {
                            params[name] = parseFloat(value).toFixed(2); // 2 decimal format
                        }
                    }
                });

                if (!isValid) {
                    alert("All input fields must have a valid number (0.00 or higher).");
                    return;
                }

                params["effective_date"] = $("#salary_effective_date").val() || null;
                params["components"] = collectDynamicComponents();

                fetch('{{ route('savePayrollSettings') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    }
                )
                    .then(res => res.json().then(data => ({ok: res.ok, data})))
                    .then(({ok, data}) => {
                        if (!ok) {
                            toastr.error(data.message || "Failed to save payroll settings.", "Error");
                            return;
                        }
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });

        });

        function editEmployee(id) {
            employee_id = id;
            $("#modal_salary_details input[id]").val(""); // Reset fixed inputs (placeholders show what to enter)
            renderDynamicComponents([]);
            $("#payroll_settings_modal").modal("show");

            // Fetch existing employee payroll data
            fetch('{{ route('getPayrollSettings') }}?userid=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fill inputs with the retrieved data
                        for (const [key, value] of Object.entries(data.data)) {
                            const input = $(`#${key}`); // Select input using ID
                            if (input.length) {
                                input.val(key === 'salary_grade' || key === 'step' ? parseInt(value) : parseFloat(value).toFixed(2));
                            }
                        }
                        ensureComponentCatalog().then(() => {
                            renderDynamicComponents(data.components || []);
                        });
                    }
                })
                .catch(error => {
                    console.error("Error fetching data:", error);
                    alert("An error occurred while fetching employee data.");
                });
        }

        function renderDynamicComponents(components) {
            const $earningsBody = $("#dynamic_components_earnings_body");
            const $deductionsBody = $("#dynamic_components_deductions_body");
            $earningsBody.empty();
            $deductionsBody.empty();

            const employeeComponentMap = {};
            (components || []).forEach(component => {
                employeeComponentMap[component.code] = component;
            });

            if (!catalogEarnings.length) {
                $earningsBody.append("<tr><td colspan='8' class='text-muted text-center py-3'>No earnings configured.</td></tr>");
            } else {
                catalogEarnings.forEach(component => {
                    const override = employeeComponentMap[component.code] || {};
                    addDynamicComponentRow(component, override, $earningsBody);
                });
            }

            if (!catalogDeductions.length) {
                $deductionsBody.append("<tr><td colspan='8' class='text-muted text-center py-3'>No deductions configured.</td></tr>");
                return;
            }

            catalogDeductions.forEach(component => {
                const override = employeeComponentMap[component.code] || {};
                addDynamicComponentRow(component, override, $deductionsBody);
            });
        }

        function addDynamicComponentRow(component = {}, override = {}, $body) {
            const code = component.code || "";
            const name = component.name || "";
            const isGlobal = !!component.is_global;
            const isPercentage = component.calculation_type === "percentage";
            const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
                ? parseFloat(component.default_amount).toFixed(2)
                : "0.00";
            const defaultDisplay = !isGlobal
                ? "&mdash;"
                : (isPercentage
                    ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                    : defaultAmount);
            const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
                ? parseFloat(override.amount_override).toFixed(2)
                : "";
            const rateOverride = override.rate_percent_override !== undefined && override.rate_percent_override !== null
                ? parseFloat(override.rate_percent_override)
                : "";
            const excluded = override.is_excluded ? "checked" : "";
            const effectiveFrom = override.effective_from || "";
            const effectiveTo = override.effective_to || "";
            const rateOverrideCell = isPercentage
                ? `<input type="number" class="form-control form-control-sm component-rate-override" step="0.001" value="${rateOverride}">`
                : "&mdash;";

            const row = `
                <tr data-code="${code}" data-default="${defaultAmount}">
                    <td>
                        <div class="fw-semibold">${name}</div>
                        <div class="small text-muted">${code}</div>
                    </td>
                    <td>${isGlobal ? "Yes" : "No"}</td>
                    <td>${defaultDisplay}</td>
                    <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                    <td>${rateOverrideCell}</td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                    </td>
                    <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                    <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
                </tr>`;

            $body.append(row);
        }

        function collectDynamicComponents() {
            const components = [];
            components.push(...collectDynamicOverrides($("#dynamic_components_earnings_body tr"), catalogEarnings));
            components.push(...collectDynamicOverrides($("#dynamic_components_deductions_body tr"), catalogDeductions));
            return components;
        }

        function collectDynamicOverrides($rows, sourceComponents) {
            const components = [];

            $rows.each(function () {
                const $row = $(this);
                const code = $row.data("code");
                if (!code) {
                    return;
                }
                const defaultAmount = parseFloat($row.data("default") || "0");
                const overrideRaw = $row.find(".component-amount").val();
                const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
                const rateOverrideRaw = $row.find(".component-rate-override").val();
                const rateOverride = (rateOverrideRaw === undefined || rateOverrideRaw === "") ? null : parseFloat(rateOverrideRaw);
                const excluded = $row.find(".component-exclude").is(":checked");
                const effectiveFrom = $row.find(".component-effective-from").val();
                const effectiveTo = $row.find(".component-effective-to").val();

                const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
                const hasRateOverride = rateOverride !== null && !isNaN(rateOverride);
                if (!excluded && !hasOverride && !hasRateOverride && !effectiveFrom && !effectiveTo) {
                    return;
                }

                const component = sourceComponents.find(item => item.code === code);
                if (!component) {
                    return;
                }

                components.push({
                    code: component.code,
                    name: component.name,
                    type: component.type,
                    taxable: component.taxable,
                    is_global: !!component.is_global,
                    default_amount: parseFloat(component.default_amount || 0),
                    amount_override: hasOverride ? overrideAmount : null,
                    rate_percent_override: hasRateOverride ? rateOverride : null,
                    is_excluded: excluded,
                    effective_from: effectiveFrom || null,
                    effective_to: effectiveTo || null
                });
            });

            return components;
        }

        function loadComponentCatalog() {
            return fetch('{{ route('getPayrollComponents') }}')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    const items = data.data || [];
                    catalogEarnings = items.filter(item => item.type === 'earning');
                    catalogDeductions = items.filter(item => item.type === 'deduction');
                    payrollCatalogLoaded = true;
                })
                .catch(err => {
                    console.error(err);
                });
        }

        function ensureComponentCatalog() {
            if (payrollCatalogLoaded) {
                return Promise.resolve();
            }
            return loadComponentCatalog();
        }


        function getEmployee(params) {
            $("#employee_list").empty();
            $("#employee_list").append("<tr><td colspan='8'>Loading data, please wait...</td></tr>");
            $("#pagination_links").empty();
            $("#page_indicator").text("");
            fetch('{{ route('getEmployee') }}?fname=' + params.fname +
                "&lname=" + params.lname +
                "&page=" + params.page, {
                    method: "GET"
                }
            )
                .then(res => res.json())
                .then(data => {
                    $("#pagination_container").addClass("d-none");
                    $("#employee_list").empty();
                    $("#pagination_links").empty();
                    $("#page_indicator").text("");

                    const pagination = data.pagination || null;
                    if (pagination && pagination.current_page) {
                        page = pagination.current_page;
                        payroll_last_page = pagination.last_page;
                    }

                    if (data.results.length < 1) {
                        $("#employee_list").append("<tr><td colspan='8'>No record found</td></tr>");
                        return;
                    }

                    $("#pagination_container").removeClass("d-none");
                    let max_visible = data.results.length;
                    if (pagination) {
                        $("#page_items_count").text(pagination.from + " - " + pagination.to + " of " + pagination.total);
                        $("#page_indicator").text("Page " + pagination.current_page + " of " + pagination.last_page);
                        renderPayrollPaginationLinks(pagination);
                    } else {
                        $("#page_items_count").text(((params.page - 1) * 10 + 1) + " - " + ((params.page - 1) * 10 + max_visible));
                        $("#page_indicator").text("Page " + params.page);
                    }
                    for (let i = 0; i < max_visible; i++) {
                        const it = data.results[i];
                        const path = '{{ url('/avatar/')}}';
                        const suffix = (it.suffix) ? it.suffix : "";
                        const mname = (it.mname) ? it.mname : "";
                        const image = (it.picture) ? (path + "/" + it.picture) : "assets/img/logo.png";
                        const tr = `
                    <tr id='employee_` + it.user_id + `'>
                        <td>
                            <img src="` + image + `" alt="" class="rounded-circle payroll-employee-photo" onerror="this.onerror=null;this.src='assets/img/logo.png';"/>
                        </td>
                        <td>` + it.fname + `</td>
                        <td>` + mname + `</td>
                        <td>` + it.lname + `</td>
                        <td>` + suffix + `</td>
                        <td>` + it.designation + `</td>
                        <td>` + it.section + `</td>
                        <td>
                            <button type='button' class="btn btn-action btn-edit" title="Edit payroll settings" onClick="editEmployee(` + it.user_id + `)">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>`;
                        $("#employee_list").append(tr);
                    }
                }).catch(err => {
                console.log(err);
            })
        }

        function renderPayrollPaginationLinks(pagination) {
            const $links = $("#pagination_links");
            if (!$links.length || !pagination || !pagination.last_page) return;

            const current = pagination.current_page;
            const last = pagination.last_page;
            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);

            $links.empty();
            $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

            if (start > 1) {
                $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
                if (start > 2) {
                    $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
                }
            }

            for (let i = start; i <= end; i++) {
                $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
            }

            if (end < last) {
                if (end < last - 1) {
                    $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
                }
                $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
            }

            $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
        }
    </script>
@stop
