@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        <div>
            <input class="form-control" placeholder="Search firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search lastname" id="search_lname"/>
        </div>
        <div class="ms-1">
            <button class="btn btn-success" id="btn_search_employee">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title">Leave Credits Management</h5>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm" data-bs-toggle="dropdown"
                            style="background: transparent !important;">
                        <span class="bi bi-three-dots-vertical"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" id="btn_bulk_update">Bulk update</a></li>
                        <li><a class="dropdown-item" href="#" id="btn_generate_report">Generate Report</a></li>
                    </ul>
                </div>

            </div>

            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th style="width: 8%">VL BAL</th>
                        <th style="width: 8%">SL BAL</th>
                        <th style="width: 8%">CTO BAL</th>
                        <th style="width: 8%">FL BAL</th>
                        <th style="width: 8%">SPL BAL</th>
                        <th style="width: 5%"></th>
                    </tr>
                    </thead>
                    <tbody id="employee_list"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-dark" id="pagination_container">
            <div class="d-flex align-items-center justify-content-center">
                <button class="btn btn-sm d-none" style="background: transparent !important;" id="btn_prev">
                    <i class="bi bi-caret-left"></i>
                </button>
                <div id="page_items_count"></div>
                <button class="btn btn-sm" style="background: transparent !important;" id="btn_next">
                    <i class="bi bi-caret-right"></i>
                </button>
            </div>
        </div>
    </div>

    @include('modals.leave_management')
@stop
@section('js')
    @include('js.leave_management')
@stop
