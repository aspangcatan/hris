@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        <div>
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
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
    @include('modals.temp_dtr')
@stop
@section('js')
    @include('js.temp_dtr')
@stop
