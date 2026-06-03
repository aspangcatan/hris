@extends('master')
@section('content')
    {{-- Credit Balances + Legends (shared above tabs) --}}
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

    {{-- Tabs --}}
    <div class="card mt-2">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="leaveTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-leave-applications" data-bs-toggle="tab"
                            data-bs-target="#pane-leave-applications" type="button" role="tab">
                        Leave Applications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-monetizations" data-bs-toggle="tab"
                            data-bs-target="#pane-monetizations" type="button" role="tab">
                        Monetizations
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="leaveTabContent">
                {{-- Tab 1: Leave Applications --}}
                <div class="tab-pane fade show active" id="pane-leave-applications" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold card-title mb-0">Leave Application</h5>
                        <button class="btn btn-sm btn-success" id="btn_file_leave">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table text-center">
                            <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Dates Applied</th>
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
                </div>

                {{-- Tab 2: Monetizations --}}
                <div class="tab-pane fade" id="pane-monetizations" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold card-title mb-0">Leave Monetization</h5>
                        <button class="btn btn-sm btn-success" id="btn_file_monetization">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table text-center">
                            <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Days Requested</th>
                                <th>Filed Date</th>
                                <th>Status</th>
                                <th style="width: 4%"></th>
                            </tr>
                            </thead>
                            <tbody id="monetization_history_list"></tbody>
                        </table>
                        <div id="monetization_pagination_container">
                            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                <div id="monetization_page_items_count"></div>
                                <nav aria-label="Monetization pagination">
                                    <ul class="pagination pagination-sm mb-0" id="monetization_pagination_links"></ul>
                                </nav>
                                <div id="monetization_page_indicator" class="small text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('modals.leave')
    @include('modals.monetize_leave')
@stop
@section('js')
    @include('js.leave_application')
@stop
