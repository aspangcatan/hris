@extends('master')
@section('content')
<style>
.blk-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.blk-head{margin-bottom:1.25rem}
.blk-head h4{margin:0;font-weight:700;color:#0f172a}
.blk-head span{font-size:.9rem;color:#64748b}
.blk-filter{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1.25rem}
.blk-filter .form-control,.blk-filter .form-select{border-radius:12px;border:1px solid #d8e1ee;background:#fff}
.blk-step-panel{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1.25rem}
.blk-step-label{font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#607086;margin-bottom:.75rem}
.blk-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.blk-table-shell table{margin-bottom:0}
.blk-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.blk-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
</style>
<div class="blk-board">
    <div class="blk-head">
        <h4>Bulk Leave Credits</h4>
        <span>Assign leave credits to multiple employees at once. Supports multiple leave types per batch.</span>
    </div>

    {{-- Step 1: Filters --}}
    <div class="blk-filter">
        <div class="blk-step-label">Step 1 — Filter Employees</div>
        <div class="row g-2 align-items-end">
                <div class="fw-semibold mb-2">Step 1 — Filter Employees</div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Division</label>
                        <select class="form-select form-select-sm" id="filter_division">
                            <option value="">All Divisions</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Section</label>
                        <select class="form-select form-select-sm" id="filter_section">
                            <option value="">All Sections</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Employee Type</label>
                        <select class="form-select form-select-sm" id="filter_type">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date Hired — From</label>
                        <input type="date" class="form-control form-control-sm" id="filter_hired_from">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date Hired — To</label>
                        <input type="date" class="form-control form-control-sm" id="filter_hired_to">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-primary w-100 mt-3" id="btn_load_employees">
                            <i class="bi bi-search me-1"></i> Load Employees
                        </button>
                    </div>
                </div>
    </div>

    {{-- Step 2: Employee Selection --}}
    <div class="blk-step-panel">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="blk-step-label mb-0">Step 2 — Select Employees</div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="select_all_employees">
                        <label class="form-check-label small" for="select_all_employees">Select All</label>
                    </div>
                </div>
        <div class="table-responsive" style="max-height:350px;overflow-y:auto;">
            <table class="table table-sm table-bordered align-middle text-center">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:3%"></th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Division</th>
                                <th>Section</th>
                                <th>Type</th>
                                <th>Date Hired</th>
                            </tr>
                        </thead>
                        <tbody id="employee_list">
                            <tr><td colspan="7" class="text-muted">Use filters above and click Load Employees.</td></tr>
                        </tbody>
            </table>
        </div>
        <div class="mt-1 small text-muted"><span id="selected_count">0</span> employee(s) selected</div>
    </div>

    {{-- Step 3: Credits to Add --}}
    <div class="blk-step-panel">
        <div class="blk-step-label">Step 3 — Credits to Add</div>
        <table class="table table-sm align-middle" id="credits_table">
                    <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th style="width:200px">Credits to Add</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody id="credits_table_body"></tbody>
                </table>
        <button class="btn btn-sm btn-outline-secondary mt-1" id="btn_add_credit_row">
            <i class="bi bi-plus-lg me-1"></i> Add Leave Type
        </button>
    </div>

    {{-- Notes --}}
    <div class="mb-3">
        <label class="form-label small fw-semibold">Notes <span class="text-muted">(optional)</span></label>
        <input type="text" class="form-control form-control-sm" id="batch_notes" placeholder="e.g. Q2 2026 credit release">
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-success px-4" id="btn_submit_bulk">
            <i class="bi bi-check-lg me-1"></i> Submit
        </button>
    </div>
</div>

{{-- Batch History --}}
<div class="blk-board mt-3">
    <div class="blk-head">
        <h4>Previous Batches</h4>
        <span>History of all bulk credit assignments. Click Print to re-open any batch report.</span>
    </div>
    <div class="table-responsive blk-table-shell">
        <table class="table table-sm table-bordered align-middle text-center">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Processed By</th>
                    <th>Employees</th>
                    <th>Notes</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody id="batch_history_list">
                <tr><td colspan="6" class="text-muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="batch_pagination_container" class="d-none mt-3">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="batch_page_items_count" class="small text-muted"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="batch_pagination_links"></ul></nav>
            <div id="batch_page_indicator" class="small text-muted"></div>
        </div>
    </div>
</div>
@stop
@section('js')
<script>
    const ROUTE_DIVISIONS     = "{{ route('bulk-leave-credits.divisions') }}";
    const ROUTE_SECTIONS      = "{{ route('bulk-leave-credits.sections') }}";
    const ROUTE_EMPLOYEES     = "{{ route('bulk-leave-credits.employees') }}";
    const ROUTE_EMP_TYPES     = "{{ route('bulk-leave-credits.employee-types') }}";
    const ROUTE_LEAVE_TYPES   = "{{ route('bulk-leave-credits.leave-types') }}";
    const ROUTE_BATCHES       = "{{ route('bulk-leave-credits.batches') }}";
    const ROUTE_STORE         = "{{ route('bulk-leave-credits.store') }}";

    let ALL_LEAVE_TYPES = [];

    let batchPage = 1, batchLastPage = 1;

    $(async function () {
        await Promise.all([loadDivisions(), loadEmployeeTypes(), loadLeaveTypes()]);
        addCreditRow();
        loadBatches();

        $('#filter_division').on('change', function () {
            loadSections($(this).val());
        });

        $('#btn_load_employees').on('click', loadEmployees);

        $('#select_all_employees').on('change', function () {
            $('#employee_list input[type="checkbox"]').prop('checked', $(this).is(':checked'));
            updateSelectedCount();
        });

        $(document).on('change', '#employee_list input[type="checkbox"]', updateSelectedCount);

        $('#btn_add_credit_row').on('click', addCreditRow);

        $('#btn_submit_bulk').on('click', handleSubmit);
    });

    async function loadDivisions() {
        const data = await fetchJson(ROUTE_DIVISIONS);
        data.forEach(d => $('#filter_division').append(`<option value="${d.id}">${d.description}</option>`));
    }

    async function loadSections(divisionId) {
        $('#filter_section').find('option:not(:first)').remove();
        const url  = divisionId ? `${ROUTE_SECTIONS}?division_id=${divisionId}` : ROUTE_SECTIONS;
        const data = await fetchJson(url);
        data.forEach(s => $('#filter_section').append(`<option value="${s.id}">${s.description}</option>`));
    }

    async function loadEmployeeTypes() {
        const data = await fetchJson(ROUTE_EMP_TYPES);
        data.forEach(t => $('#filter_type').append(`<option value="${t}">${t}</option>`));
    }

    async function loadLeaveTypes() {
        const data = await fetchJson(ROUTE_LEAVE_TYPES);
        ALL_LEAVE_TYPES = Array.isArray(data) ? data : [];
    }

    async function loadEmployees() {
        const divisionId   = $('#filter_division').val();
        const sectionId    = $('#filter_section').val();
        const employeeType = $('#filter_type').val();
        const hiredFrom    = $('#filter_hired_from').val();
        const hiredTo      = $('#filter_hired_to').val();

        const params = new URLSearchParams();
        if (divisionId)   params.append('division_id', divisionId);
        if (sectionId)    params.append('section_id', sectionId);
        if (employeeType) params.append('employee_type', employeeType);
        if (hiredFrom)    params.append('hired_from', hiredFrom);
        if (hiredTo)      params.append('hired_to', hiredTo);

        $('#employee_list').html('<tr><td colspan="6">Loading...</td></tr>');
        $('#select_all_employees').prop('checked', false);

        const data = await fetchJson(`${ROUTE_EMPLOYEES}?${params.toString()}`);

        if (!data.length) {
            $('#employee_list').html('<tr><td colspan="7" class="text-muted">No employees found.</td></tr>');
            updateSelectedCount();
            return;
        }

        $('#employee_list').empty();
        data.forEach(emp => {
            const dateHired = emp.date_hired
                ? new Date(emp.date_hired).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' })
                : '—';
            $('#employee_list').append(`
            <tr>
                <td><input type="checkbox" class="form-check-input emp-checkbox" value="${emp.user_id}"></td>
                <td class="text-start">${emp.lastname}, ${emp.firstname}</td>
                <td>${emp.designation_name ?? '—'}</td>
                <td>${emp.division_name ?? '—'}</td>
                <td>${emp.section_name ?? '—'}</td>
                <td>${emp.employee_type ?? '—'}</td>
                <td>${dateHired}</td>
            </tr>`);
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const count = $('#employee_list input.emp-checkbox:checked').length;
        $('#selected_count').text(count);
    }

    function buildLeaveTypeOptions(excludeIds = []) {
        return ALL_LEAVE_TYPES
            .map(lt => `<option value="${lt.id}" ${excludeIds.includes(lt.id) ? 'disabled' : ''}>${lt.description}</option>`)
            .join('');
    }

    function addCreditRow() {
        const row = $(`
        <tr>
            <td>
                <select class="form-select form-select-sm credit-leave-type">
                    <option value="">-- Select --</option>
                    ${buildLeaveTypeOptions()}
                </select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm credit-value"
                       min="0.001" step="0.001" placeholder="e.g. 1.250">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCreditRow(this)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>`);
        $('#credits_table_body').append(row);
    }

    function removeCreditRow(btn) {
        if ($('#credits_table_body tr').length <= 1) {
            toastr.error('At least one leave type is required.', 'Required');
            return;
        }
        $(btn).closest('tr').remove();
    }

    async function handleSubmit() {
        const userIds = $('#employee_list input.emp-checkbox:checked').map(function () {
            return parseInt($(this).val(), 10);
        }).get();

        if (!userIds.length) {
            toastr.error('Please select at least one employee.', 'Required');
            return;
        }

        const credits = [];
        let valid = true;
        $('#credits_table_body tr').each(function () {
            const leaveTypeId = parseInt($(this).find('.credit-leave-type').val(), 10);
            const value       = parseFloat($(this).find('.credit-value').val());
            if (!leaveTypeId || !value || value <= 0) {
                valid = false;
                return false;
            }
            credits.push({ leave_type_id: leaveTypeId, value });
        });

        if (!valid) {
            toastr.error('Please fill in all leave type and credits fields.', 'Required');
            return;
        }

        // Check for duplicate leave types
        const typeIds = credits.map(c => c.leave_type_id);
        if (new Set(typeIds).size !== typeIds.length) {
            toastr.error('Duplicate leave types detected. Each leave type can only appear once.', 'Error');
            return;
        }

        const notes = $('#batch_notes').val().trim();
        const btn   = $('#btn_submit_bulk').prop('disabled', true).text('Submitting...');

        try {
            const response = await fetch(ROUTE_STORE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ user_ids: userIds, credits, notes }),
            });

            const body = await response.json();
            if (!response.ok) {
                toastr.error(body.message || 'Failed to apply credits.', 'Error');
                return;
            }

            toastr.success(body.message, 'Success');

            if (body.print_url) {
                setTimeout(() => window.open(body.print_url, '_blank'), 800);
            }

            batchPage = 1;
            loadBatches();

            // Reset
            $('#employee_list input.emp-checkbox').prop('checked', false);
            $('#select_all_employees').prop('checked', false);
            updateSelectedCount();
            $('#credits_table_body').empty();
            addCreditRow();
            $('#batch_notes').val('');
        } catch {
            toastr.error('An error occurred.', 'Error');
        } finally {
            $('#btn_submit_bulk').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Submit');
        }
    }

    async function fetchJson(url) {
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return await r.json();
        } catch {
            return [];
        }
    }

    /** -- Batch History -- */
    async function loadBatches() {
        $('#batch_history_list').html('<tr><td colspan="6" class="text-muted">Loading...</td></tr>');
        const payload = await fetchJson(`${ROUTE_BATCHES}?page=${batchPage}`);
        const data    = payload.results    || [];
        const pg      = payload.pagination || null;

        $('#batch_history_list').empty();

        if (!data.length) {
            $('#batch_pagination_container').addClass('d-none');
            $('#batch_history_list').html('<tr><td colspan="6" class="text-muted">No batches yet.</td></tr>');
            return;
        }

        $('#batch_pagination_container').removeClass('d-none');

        data.forEach(b => {
            $('#batch_history_list').append(`
            <tr>
                <td>${b.id}</td>
                <td>${b.date}</td>
                <td>${b.admin}</td>
                <td>${b.emp_count}</td>
                <td class="text-start">${b.notes}</td>
                <td>
                    <a href="${b.print_url}" target="_blank" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-printer"></i> Print
                    </a>
                </td>
            </tr>`);
        });

        if (pg) {
            batchLastPage = pg.last_page;
            $('#batch_page_items_count').text(`${pg.from} - ${pg.to} of ${pg.total}`);
            $('#batch_page_indicator').text(`Page ${pg.current_page} of ${pg.last_page}`);
            renderBatchPaginationLinks(pg);
        }
    }

    function renderBatchPaginationLinks(pg) {
        const $links  = $('#batch_pagination_links');
        const current = pg.current_page;
        const last    = pg.last_page;
        $links.empty();
        $links.append(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeBatchPage(${current - 1})">Prev</a></li>`);
        for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
            $links.append(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeBatchPage(${i})">${i}</a></li>`);
        }
        $links.append(`<li class="page-item ${current >= last ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changeBatchPage(${current + 1})">Next</a></li>`);
    }

    function changeBatchPage(p) {
        if (p < 1 || p > batchLastPage) return;
        batchPage = p;
        loadBatches();
    }
</script>
@stop
