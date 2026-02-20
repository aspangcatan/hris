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
            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                <div id="page_items_count"></div>
                <nav aria-label="Leave review pagination">
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
