@extends('master')
@section('content')
    <div class="row">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <div>SL Balance</div>
                    <div class="fw-bold">8.50</div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end">
        <button class="btn btn-sm btn-success" id="btn_file_leave">FILE LEAVE <i class="bi bi-plus-lg"></i></button>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <h5 class="card-title">Leave History</h5>
            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Details</th>
                        <th>Specific</th>
                        <th>Dates applied</th>
                        <th>Status</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="leave_history_list"></tbody>
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
    @include('modals.leave')
@stop
@section('js')
    @include('js.leave_application')
@stop
