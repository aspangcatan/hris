@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        <div>
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
        </div>
        <div class="ms-2 d-flex align-items-center">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="filter_no_employee_no">
                <label class="form-check-label" for="filter_no_employee_no">
                    No Employee No.
                </label>
            </div>
        </div>
        <div class="ms-1">
            <button class="btn btn-success" id="btn_search">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Personnel List</h5>
            <div class="table-responsive">
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
                            <th style="width: 4%"></th>
                        </tr>
                    </thead>
                    <tbody id="employee_list"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-dark" id="pagination_container">
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
