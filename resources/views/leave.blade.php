@extends('master')
@section('content')
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
            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                <div id="page_items_count"></div>
                <nav aria-label="Leave history pagination">
                    <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                </nav>
                <div id="page_indicator" class="small text-muted"></div>
            </div>
        </div>
    </div>
    @include('modals.leave')
@stop
@section('js')
    @include('js.leave')
@stop
