@extends('master')
@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold card-title">Credit Balances</div>
                        <div>
                            <button class="btn btn-sm btn-primary" id="btn_reload">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2" id="credit_balances"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="fw-bold card-title">Legends</div>
                    <div class="row mt-2" id="legend_list"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="fw-bold card-title">Leave Application</h5>
                @if(true)
                    <div>
                        <button class="btn btn-sm btn-success" id="btn_file_leave">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                @endif
            </div>
            @if(true)
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
                    <div id="pagination_container">
                        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                            <div id="page_items_count"></div>
                            <nav aria-label="Leave application pagination">
                                <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                            </nav>
                            <div id="page_indicator" class="small text-muted"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="d-flex flex-column align-items-center justify-content-center">
                    <img src="{{ asset('img/bg_maintenance.png') }}" alt="Maintenance" height="150" width="150">
                    <p class="fs-4">Under development</p>
                </div>
            @endif
        </div>
    </div>
    @include('modals.leave')
@stop
@section('js')
    @include('js.leave_application')
@stop
