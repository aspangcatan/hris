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
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="filter_no_employee_no">
                        <label class="form-check-label" for="filter_no_employee_no">
                            No Employee No.
                        </label>
                    </div>
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
    @include('modals.print')
@stop
@section('js')
    @include('js.employee')
@stop
