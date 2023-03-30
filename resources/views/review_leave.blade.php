@extends('master')
@section('content')
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Review Leave Application</h5>
            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Division</th>
                        <th>Unit</th>
                        <th>Designation</th>
                        <th>Leave Type</th>
                        <th>Date Applied</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="leave_requests"></tbody>
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
    @include('js.leave')
@stop
