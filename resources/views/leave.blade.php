@extends('master')
@section('content')
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Leaves Applied</h5>
            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Leave Type</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Date applied</th>
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

    @include('modals.leave')
@stop
@section('js')
    @include('js.leave')
@stop
