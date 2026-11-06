@extends('master')
@section('content')
    <style>
        .employee-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .employee-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .employee-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .employee-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .employee-filter {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .employee-filter .form-control {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .employee-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .employee-table-shell table {
            margin-bottom: 0;
        }

        .employee-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .employee-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        .leave-credit-note {
            padding: 0.85rem 1rem;
            border: 1px solid #d6e4f0;
            border-radius: 14px;
            background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%);
            color: #4b5563;
            font-size: 0.85rem;
        }

        .leave-credit-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .leave-credit-table {
            margin-bottom: 0;
        }

        .leave-credit-table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid #e2e8f0;
        }

        .leave-credit-table tbody td {
            vertical-align: middle;
            border-color: #eef2f7;
        }

        .leave-credit-title {
            font-weight: 700;
            color: #0f172a;
        }

        .leave-credit-code {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .leave-credit-display {
            display: inline-flex;
            align-items: baseline;
            gap: 0.35rem;
            padding: 0.55rem 0.85rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #dbe5f0;
        }

        .leave-credit-value {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .leave-credit-input-group .form-control {
            min-width: 110px;
            border-radius: 12px 0 0 12px;
            border-color: #cbd5e1;
            font-weight: 600;
        }

        .leave-credit-input-group .btn {
            min-width: 42px;
        }

        .leave-credit-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .leave-credit-actions .btn {
            border-radius: 10px;
        }

        .leave-credit-status {
            font-size: 0.75rem;
            color: #64748b;
        }

        .leave-credit-row.is-editing .leave-credit-display {
            display: none;
        }

        .leave-credit-row.is-editing .leave-credit-input-group {
            display: flex !important;
        }

        .leave-credit-row.is-editing .credit-edit-btn {
            display: none;
        }

        .leave-credit-empty {
            text-align: center;
            color: #64748b;
            font-weight: 500;
        }
    </style>

    <div class="employee-board">
        <div class="employee-head">
            <div class="employee-head-title">
                <h4>Personnel List</h4>
                <span>Manage employee profiles, records, and actions.</span>
            </div>
        </div>

        <div class="employee-filter">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
                </div>
                <div class="col-md-3 d-flex flex-column justify-content-center gap-1">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="filter_no_employee_no">
                        <label class="form-check-label" for="filter_no_employee_no">
                            No Employee No.
                        </label>
                    </div>
                    @permission('reorientation.check')
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="filter_reo_pending">
                        <label class="form-check-label" for="filter_reo_pending">
                            Re-Orientation for checking
                        </label>
                    </div>
                    @endpermission
                </div>
                <div class="col-md-3">
                    <button class="btn btn-action btn-view w-100" id="btn_search">
                        <i class="bi bi-search"></i>
                        Search
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive employee-table-shell">
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
                <nav aria-label="Employee list pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                </nav>
                <div id="page_indicator" class="small text-muted"></div>
            </div>
        </div>
    </div>
    @include('modals.employee')
    @include('modals.service_record')
    @include('modals.service_record_upload')
    @include('modals.print')
@stop
@section('js')
    @include('js.employee')
@stop
