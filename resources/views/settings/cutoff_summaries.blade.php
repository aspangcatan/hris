@extends('master')
@section('content')
    @include('layouts.saln_styles')
    <style>
        /* The type-warning modal opens on top of the Create Cutoff modal. */
        .cutoff-stacked-modal { z-index: 1065; }
        .modal-backdrop.cutoff-stacked-backdrop { z-index: 1060; }

        .saln-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .saln-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .saln-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .saln-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .saln-filter-lite {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
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
    </style>

    <div class="saln-board">
        <div class="saln-head">
            <div class="saln-head-title">
                <h4>Cutoff Summary</h4>
                <span>Review generated cutoff totals by employee and period.</span>
            </div>
            <button class="btn btn-edit" id="btn_create_cutoff">
                <i class="bi bi-plus-circle"></i>
                CREATE CUTOFF
            </button>
        </div>
        <div class="saln-filter-lite">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="saln-info-label mb-1">Filter by Title</label>
                    <input type="text" class="form-control" id="cutoff_title_filter" placeholder="Search cutoff title">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle text-center saln-table-premium">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Period Start</th>
                    <th>Period End</th>
                    <th style="width: 12%"></th>
                </tr>
                </thead>
                <tbody id="cutoff_period_body">
                <tr>
                    <td colspan="4" class="text-muted text-center">No cutoff summaries found.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="create_cutoff_modal" tabindex="-1" aria-labelledby="createCutoffLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="createCutoffLabel">Create Cutoff</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <div class="alert d-none" id="cutoff_feedback" role="alert"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="cutoff_title" placeholder="e.g. March 1-15 Cutoff">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="cutoff_modal_start_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="cutoff_modal_end_date">
                        </div>
                    </div>

                    <div class="saln-filter-lite">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Division</label>
                                <select class="form-control" id="cutoff_division"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Section</label>
                                <select class="form-control" id="cutoff_section"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search Name</label>
                                <input type="text" class="form-control" id="cutoff_search_name" placeholder="Search employee">
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-action btn-view w-100" id="btn_load_cutoff_employees">
                                    <i class="bi bi-search"></i>
                                    Load Employees
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle text-center saln-table-premium">
                            <thead>
                            <tr>
                                <th style="width: 5%">
                                    <input type="checkbox" class="form-check-input" id="cutoff_select_all">
                                </th>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Employee Type</th>
                            </tr>
                            </thead>
                            <tbody id="cutoff_employee_body">
                            <tr>
                                <td colspan="4" class="text-muted text-center">No employees loaded.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_save_cutoff">Generate Cutoff</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Shown over the Create Cutoff modal when the selection includes non-Permanent employees. --}}
    <div class="modal fade payroll-modal-theme cutoff-stacked-modal" id="cutoff_type_warning_modal" tabindex="-1"
         aria-labelledby="cutoffTypeWarningLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title text-white" id="cutoffTypeWarningLabel">Check the selected employees</h4>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Some selected employees are not <strong>Permanent</strong>:</p>
                    <div id="cutoff_type_warning_list"></div>
                    <p class="small text-muted mb-0 mt-2" id="cutoff_type_warning_note"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" id="btn_type_warning_back">Go back</button>
                    <button type="button" class="btn btn-outline-secondary" id="btn_type_warning_keep">Keep &amp; generate</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_type_warning_remove">Remove &amp; generate</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="cutoff_detail_modal" tabindex="-1" aria-labelledby="cutoffDetailLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="cutoffDetailLabel">Cutoff Summary Details</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <div class="fw-semibold" id="cutoff_detail_title">Title</div>
                            <div class="small text-muted">
                                <span id="cutoff_detail_start">-</span> to <span id="cutoff_detail_end">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle text-center saln-table-premium">
                            <thead>
                            <tr>
                                <th>Employee</th>
                                <th title="The employee's own work days in this cutoff (rest days and holidays excluded)">Scheduled Days</th>
                                <th>Days Present</th>
                                <th>Days Absent</th>
                                <th>Late (mins)</th>
                                <th>Undertime (mins)</th>
                                <th>Hospital Orders</th>
                                <th>Overtime (mins)</th>
                                <th title="Personal pass slip minutes inside working hours - deducted from VL earned credits only">Personal Pass Slips (mins)</th>
                                <th>Approved Leave</th>
                                <th>LWOP Days</th>
                            </tr>
                            </thead>
                            <tbody id="cutoff_detail_body">
                            <tr>
                                <td colspan="11" class="text-muted text-center">No cutoff summaries found.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_compute_leave_credits">
                        <span class="btn-label">Compute Leave Credits Earned</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade payroll-modal-theme" id="leave_credits_modal" tabindex="-1" aria-labelledby="leaveCreditsLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header sticky-top">
                    <h4 class="modal-title text-white" id="leaveCreditsLabel">Leave Credits Earned</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <div class="alert alert-warning d-none" id="leave_credits_notice" role="alert"></div>
                    <div class="table-responsive">
                        <table class="table align-middle text-center saln-table-premium">
                            <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Present / Work Days</th>
                                <th title="Late/undertime + personal pass slip minutes, all deducted from VL">Late/Undertime + Pass Slips (mins)</th>
                                <th>Earned Points</th>
                                <th>Deducted Points</th>
                                <th>Net Points</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody id="leave_credits_body">
                            <tr>
                                <td colspan="7" class="text-muted text-center">No data computed.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-view" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-edit" id="btn_save_leave_credits">
                        <span class="btn-label">Save</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let cutoffPeriods = [];
        let currentCutoffDetail = {id: null, start: null, end: null, title: null};
        let leaveCreditPreview = [];
        let leaveCreditsCanSave = false;
        $(document).ready(function () {
            loadCutoffPeriods();

            $("#btn_create_cutoff").click(function () {
                resetCutoffFeedback();
                resetCreateCutoffForm();
                $("#create_cutoff_modal").modal("show");
            });

            $("#btn_load_cutoff_employees").click(function () {
                loadCutoffEmployees();
            });

            $("#cutoff_search_name").on("input", function () {
                filterCutoffEmployees();
            });

            $("#cutoff_select_all").change(function () {
                const checked = $(this).is(":checked");
                $("#cutoff_employee_body input[type='checkbox']").prop("checked", checked);
            });

            $("#btn_save_cutoff").click(function () {
                createCutoffSummaries();
            });

            loadDivision();
        });

        function loadCutoffPeriods() {
            fetch(`{{ route('getCutoffPeriods') }}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        return;
                    }
                    cutoffPeriods = data.data || [];
                    renderCutoffPeriods();
                })
                .catch(err => console.error(err));
        }

        function renderCutoffPeriods() {
            const $tbody = $("#cutoff_period_body");
            $tbody.empty();

            if (!cutoffPeriods.length) {
                $tbody.append("<tr><td colspan='4' class='text-muted text-center'>No cutoff summaries found.</td></tr>");
                return;
            }

            cutoffPeriods.forEach(item => {
                const title = item.title || `Cutoff ${item.period_start} - ${item.period_end}`;
                const row = `
                    <tr data-title="${title.toLowerCase()}">
                        <td>${title}</td>
                        <td>${formatDisplayDate(item.period_start)}</td>
                        <td>${formatDisplayDate(item.period_end)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-action btn-view btn-view-cutoff"
                                data-id="${item.id}" data-start="${item.period_start}" data-end="${item.period_end}" data-title="${title}"
                                title="View cutoff summary">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>`;
                $tbody.append(row);
            });
        }

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}")
                .then(response => response.json())
                .then(data => {
                    $("#cutoff_division").empty().append("<option value=''>Select division</option>");
                    $("#cutoff_section").empty().append("<option value=''>Select section</option>");
                    data.forEach(it => {
                        $("#cutoff_division").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                })
                .catch(err => console.error(err));
        }

        $("#cutoff_division").change(function () {
            const division = $("#cutoff_division").val();
            $("#cutoff_section").empty().append("<option value=''>Select section</option>");
            if (!division) {
                return;
            }
            fetch("{{ route('getSectionList') }}?division=" + division)
                .then(response => response.json())
                .then(data => {
                    data.forEach(it => {
                        $("#cutoff_section").append(`<option value="${it.id}">${it.description}</option>`);
                    });
                })
                .catch(err => console.error(err));
        });

        function loadCutoffEmployees() {
            const division = $("#cutoff_division").val();
            const section = $("#cutoff_section").val();
            if (!division) {
                toastr.error("Please select a division first.", "Validation");
                return;
            }

            fetch(`{{ route('getEmployeeByDivisionSection') }}?division=${division}&section=${section}`)
                .then(response => response.json())
                .then(data => {
                    const $tbody = $("#cutoff_employee_body");
                    $tbody.empty();
                    $("#cutoff_select_all").prop("checked", false);

                    if (!data.results || data.results.length < 1) {
                        $tbody.append("<tr><td colspan='4' class='text-muted text-center'>No employees found.</td></tr>");
                        return;
                    }

                    data.results.forEach(emp => {
                        const middleInitial = emp.mname ? emp.mname.charAt(0) + '.' : '';
                        const suffix = emp.suffix ? ` ${emp.suffix}` : '';
                        const fullName = formatEmployeeName(`${emp.lname}, ${emp.fname} ${middleInitial}${suffix}`);
                        const type = (emp.employee_type || "").trim();
                        const row = `
                            <tr data-name="${fullName.toLowerCase()}">
                                <td><input type="checkbox" class="form-check-input cutoff-employee" value="${emp.user_id}"
                                           data-name="${fullName}" data-type="${type}"></td>
                                <td>${fullName}</td>
                                <td>${emp.designation ?? '-'}</td>
                                <td>${employeeTypeBadge(type)}</td>
                            </tr>`;
                        $tbody.append(row);
                    });

                    filterCutoffEmployees();
                })
                .catch(err => console.error(err));
        }

        function filterCutoffEmployees() {
            const search = ($("#cutoff_search_name").val() || "").toLowerCase();
            $("#cutoff_employee_body tr").each(function () {
                const name = ($(this).data("name") || "").toString();
                if (!search || name.includes(search)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        // Resigned/Retired are always left out by the server; the other
        // non-Permanent types are allowed but the user must confirm them.
        const SEPARATED_TYPES = ["Resigned", "Retired"];

        function employeeTypeBadge(type) {
            if (!type) {
                return `<span class="text-muted">—</span>`;
            }
            if (type === "Permanent") {
                return `<span class="badge bg-success">${type}</span>`;
            }
            const cls = SEPARATED_TYPES.includes(type) ? "bg-danger" : "bg-warning text-dark";
            return `<span class="badge ${cls}">${type}</span>`;
        }

        // Checked employees whose type is set and isn't Permanent.
        function flaggedSelectedEmployees() {
            return $("#cutoff_employee_body .cutoff-employee:checked").map(function () {
                const type = ($(this).data("type") || "").toString();
                return type && type !== "Permanent"
                    ? {id: parseInt($(this).val(), 10), name: $(this).data("name"), type: type}
                    : null;
            }).get();
        }

        function selectedEmployeeIds() {
            return $("#cutoff_employee_body .cutoff-employee:checked").map(function () {
                return parseInt($(this).val(), 10);
            }).get();
        }

        function showTypeWarning(flagged) {
            const byType = {};
            flagged.forEach(e => (byType[e.type] = byType[e.type] || []).push(e.name));

            const $list = $("#cutoff_type_warning_list").empty();
            Object.keys(byType).sort().forEach(type => {
                const names = byType[type].map(n => `<li>${n}</li>`).join("");
                $list.append(`
                    <div class="mb-2">
                        ${employeeTypeBadge(type)} <strong>${byType[type].length}</strong> selected
                        <ul class="mb-0 small">${names}</ul>
                    </div>`);
            });

            const separated = flagged.filter(e => SEPARATED_TYPES.includes(e.type)).length;
            const others = flagged.length - separated;
            $("#cutoff_type_warning_note").text(separated > 0
                ? `Resigned and retired employees are always left out of a cutoff, even if you keep them.`
                : "");
            // Nothing left to keep when every flagged employee is resigned/retired.
            $("#btn_type_warning_keep").toggle(others > 0);

            $("#cutoff_type_warning_modal").modal("show");
        }

        $("#cutoff_type_warning_modal").on("shown.bs.modal", function () {
            $(".modal-backdrop").last().addClass("cutoff-stacked-backdrop");
        });

        $("#btn_type_warning_back").click(function () {
            $("#cutoff_type_warning_modal").modal("hide");
        });

        $("#btn_type_warning_keep").click(function () {
            $("#cutoff_type_warning_modal").modal("hide");
            submitCutoff(selectedEmployeeIds());
        });

        $("#btn_type_warning_remove").click(function () {
            const flaggedIds = flaggedSelectedEmployees().map(e => e.id);
            flaggedIds.forEach(id => $(`#cutoff_employee_body .cutoff-employee[value='${id}']`).prop("checked", false));
            $("#cutoff_select_all").prop("checked", false);
            $("#cutoff_type_warning_modal").modal("hide");

            const remaining = selectedEmployeeIds();
            if (remaining.length < 1) {
                showCutoffFeedback("No employees left after removing them. Please select at least one employee.", "danger");
                return;
            }
            submitCutoff(remaining);
        });

        function createCutoffSummaries() {
            const title = ($("#cutoff_title").val() || "").trim();
            const startDate = $("#cutoff_modal_start_date").val();
            const endDate = $("#cutoff_modal_end_date").val();
            const selected = selectedEmployeeIds();

            if (!title || !startDate || !endDate) {
                showCutoffFeedback("Please fill in title, start date, and end date.", "danger");
                return;
            }

            if (selected.length < 1) {
                showCutoffFeedback("Please select at least one employee.", "danger");
                return;
            }

            const flagged = flaggedSelectedEmployees();
            if (flagged.length > 0) {
                showTypeWarning(flagged);
                return;
            }

            submitCutoff(selected);
        }

        function submitCutoff(selected) {
            const title = ($("#cutoff_title").val() || "").trim();
            const startDate = $("#cutoff_modal_start_date").val();
            const endDate = $("#cutoff_modal_end_date").val();

            showCutoffFeedback("Generating cutoff summaries, please wait...", "info");
            const $saveBtn = $("#btn_save_cutoff");
            $saveBtn.prop("disabled", true).text("Generating...");

            fetch('{{ route('createCutoffSummaries') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    title: title,
                    start_date: startDate,
                    end_date: endDate,
                    employees: selected
                })
            })
                .then(async res => {
                    const contentType = res.headers.get("content-type") || "";
                    const isJson = contentType.includes("application/json");
                    const payload = isJson ? await res.json() : await res.text();
                    if (!res.ok) {
                        const message = isJson
                            ? (payload.message || (res.status === 403 ? "You do not have permission to generate cutoffs." : payload.error) || "Request failed.")
                            : (payload || "Request failed.");
                        throw new Error(message);
                    }
                    return payload;
                })
                .then(data => {
                    if (data.success) {
                        showCutoffFeedback(data.message || "Cutoff generated.", "success");
                        toastr.success(data.message || "Cutoff generated.", "Information");
                        resetCreateCutoffForm();
                        $("#create_cutoff_modal").modal("hide");
                        loadCutoffPeriods();
                    } else {
                        showCutoffFeedback(data.message || "Failed to generate cutoff.", "danger");
                        toastr.error(data.message || "Failed to generate cutoff.", "Error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    const message = err?.message || "Failed to generate cutoff.";
                    showCutoffFeedback(message, "danger");
                    toastr.error(message, "Error");
                })
                .finally(() => {
                    $saveBtn.prop("disabled", false).text("Generate Cutoff");
                });
        }

        function showCutoffFeedback(message, type) {
            const $feedback = $("#cutoff_feedback");
            $feedback.removeClass("d-none alert-success alert-danger alert-info")
                .addClass("alert-" + type)
                .text(message);
        }

        function resetCutoffFeedback() {
            $("#cutoff_feedback").addClass("d-none").removeClass("alert-success alert-danger alert-info").text("");
        }

        function resetCreateCutoffForm() {
            $("#cutoff_title").val("");
            $("#cutoff_modal_start_date").val("");
            $("#cutoff_modal_end_date").val("");
            // Only last month can be generated (the server enforces this too).
            const today = new Date();
            const pad = n => String(n).padStart(2, "0");
            const firstPrev = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastPrev = new Date(today.getFullYear(), today.getMonth(), 0);
            const toIso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            $("#cutoff_modal_start_date, #cutoff_modal_end_date")
                .attr("min", toIso(firstPrev))
                .attr("max", toIso(lastPrev));
            $("#cutoff_search_name").val("");
            $("#cutoff_select_all").prop("checked", false);
            $("#cutoff_employee_body").empty()
                .append("<tr><td colspan='4' class='text-muted text-center'>No employees loaded.</td></tr>");
            loadDivision();
        }

        $("#cutoff_title_filter").on("input", function () {
            const value = ($(this).val() || "").toLowerCase();
            $("#cutoff_period_body tr").each(function () {
                const title = ($(this).data("title") || "").toString();
                if (!value || title.includes(value)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        $(document).on("click", ".btn-view-cutoff", function () {
            const id = $(this).data("id");
            const start = $(this).data("start");
            const end = $(this).data("end");
            const title = $(this).data("title");
            currentCutoffDetail = {id: id, start: start, end: end, title: title};
            $("#cutoff_detail_title").text(title);
            $("#cutoff_detail_start").text(formatDisplayDate(start));
            $("#cutoff_detail_end").text(formatDisplayDate(end));
            loadCutoffDetails(id);
            $("#cutoff_detail_modal").modal("show");
        });

        $("#btn_compute_leave_credits").click(function () {
            computeLeaveCredits();
        });

        $("#btn_save_leave_credits").click(function () {
            saveLeaveCredits();
        });

        function loadCutoffDetails(cutoffId) {
            const params = new URLSearchParams();
            params.append("cutoff_id", cutoffId);

            fetch(`{{ route('getCutoffSummaryPreview') }}?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#cutoff_detail_body");
                    $tbody.empty();
                    if (!data.success || !data.data || !data.data.length) {
                        $tbody.append("<tr><td colspan='11' class='text-muted text-center'>No cutoff summaries found.</td></tr>");
                        return;
                    }

                    data.data.forEach(item => {
                        const middleInitial = item.mname ? item.mname.charAt(0) + '.' : '';
                        const suffix = item.suffix ? ` ${item.suffix}` : '';
                        const fullName = formatEmployeeName(`${item.lname}, ${item.fname} ${middleInitial}${suffix}`);
                        const row = `
                            <tr>
                                <td>${fullName}</td>
                                <td>${Number(item.scheduled_work_days) > 0 ? item.scheduled_work_days : '<span class="text-muted" title="Regenerate this cutoff to fill this in">—</span>'}</td>
                                <td>${item.days_present ?? 0}</td>
                                <td>${item.days_absent ?? 0}</td>
                                <td>${item.late_minutes ?? 0}</td>
                                <td>${item.undertime_minutes ?? 0}</td>
                                <td>${item.hospital_order_days ?? 0}</td>
                                <td>${item.approved_overtime_minutes ?? 0}</td>
                                <td>${item.approved_pass_slips_minutes ?? 0}</td>
                                <td>${item.approved_leave_days ?? 0}</td>
                                <td>${Number(item.lwop_days ?? 0)}</td>
                            </tr>`;
                        $tbody.append(row);
                    });
                })
                .catch(err => {
                    console.error(err);
                });
        }

        function computeLeaveCredits() {
            if (!currentCutoffDetail.id) {
                toastr.error("Cutoff period is not set.", "Validation");
                return;
            }

            const params = new URLSearchParams();
            params.append("cutoff_id", currentCutoffDetail.id);
            const $button = $("#btn_compute_leave_credits");
            setButtonLoading($button, true, "Computing...");

            fetch(`{{ route('previewCutoffLeaveCredits') }}?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    const $tbody = $("#leave_credits_body");
                    $tbody.empty();
                    leaveCreditPreview = [];
                    leaveCreditsCanSave = false;

                    if (!data.success) {
                        toastr.error(data.message || data.error || "Failed to compute leave credits.", "Error");
                        return;
                    }

                    // Nothing to post (no employees, all already credited, or no attendance):
                    // tell the user and keep Save disabled.
                    const notice = data.message || "";
                    $("#leave_credits_notice").toggleClass("d-none", !notice).text(notice);
                    if (notice) {
                        toastr.warning(notice, "Nothing to credit");
                    }
                    leaveCreditsCanSave = !notice;

                    if (!data.data || !data.data.length) {
                        $tbody.append("<tr><td colspan='7' class='text-muted text-center'>No employees to credit.</td></tr>");
                    } else {
                        leaveCreditPreview = data.data;
                        data.data.forEach(item => {
                            const status = item.already_credited
                                ? `<span class="badge bg-secondary">Already credited</span>`
                                : `<span class="badge bg-success">To be credited</span>`;
                            const row = `
                                <tr class="${item.already_credited ? 'text-muted' : ''}">
                                    <td>${formatEmployeeName(item.name)}</td>
                                    <td>${item.total_present ?? 0} / ${item.work_days ?? 0}</td>
                                    <td>${item.total_late_undertime ?? 0}${Number(item.pass_slip_minutes) > 0 ? `<div class="small text-muted">${item.late_undertime_minutes} + ${item.pass_slip_minutes} slip</div>` : ''}</td>
                                    <td>${formatThreeDecimals(item.earned_points)}</td>
                                    <td>${formatThreeDecimals(item.deducted_points)}</td>
                                    <td>${formatThreeDecimals(item.net_points)}</td>
                                    <td>${status}</td>
                                </tr>`;
                            $tbody.append(row);
                        });
                    }

                    $("#btn_save_leave_credits").prop("disabled", !leaveCreditsCanSave);
                    $("#leave_credits_modal").modal("show");
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to compute leave credits.", "Error");
                })
                .finally(() => {
                    setButtonLoading($button, false, "Compute Leave Credits Earned");
                });
        }

        function saveLeaveCredits() {
            if (!currentCutoffDetail.id) {
                toastr.error("Cutoff period is not set.", "Validation");
                return;
            }

            if (!leaveCreditsCanSave || !leaveCreditPreview.length) {
                toastr.warning($("#leave_credits_notice").text() || "There is no one to credit in this cutoff.", "Nothing to credit");
                return;
            }

            const $button = $("#btn_save_leave_credits");
            setButtonLoading($button, true, "Saving...");

            fetch(`{{ route('applyCutoffLeaveCredits') }}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    cutoff_id: currentCutoffDetail.id,
                    employees: leaveCreditPreview.map(item => item.employee_id)
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        toastr.success(data.message || "Leave credits posted.", "Information");
                        $("#leave_credits_modal").modal("hide");
                    } else {
                        // e.g. everyone was already credited, or nobody has attendance.
                        toastr.warning(data.message || data.error || "No leave credits were posted.", "Nothing posted");
                    }
                })
                .catch(err => {
                    console.error(err);
                    toastr.error("Failed to update leave credits.", "Error");
                })
                .finally(() => {
                    setButtonLoading($button, false, "Save");
                    $button.prop("disabled", !leaveCreditsCanSave);
                });
        }

        function formatDisplayDate(dateString) {
            if (!dateString) {
                return "-";
            }

            const date = new Date(`${dateString}T00:00:00`);
            if (Number.isNaN(date.getTime())) {
                return dateString;
            }

            return date.toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric"
            });
        }

        function formatEmployeeName(name) {
            return (name || "-").toString().trim().toUpperCase();
        }

        function formatThreeDecimals(value) {
            const numeric = parseFloat(value ?? 0);
            return Number.isNaN(numeric) ? "0.000" : numeric.toFixed(3);
        }

        function setButtonLoading($button, isLoading, loadingText) {
            const $label = $button.find(".btn-label");
            const $spinner = $button.find(".spinner-border");
            const defaultText = $button.data("default-text") || $label.text().trim();

            if (!$button.data("default-text")) {
                $button.data("default-text", defaultText);
            }

            $button.prop("disabled", isLoading);
            $label.text(isLoading ? loadingText : defaultText);
            $spinner.toggleClass("d-none", !isLoading);
        }
    </script>
@stop
