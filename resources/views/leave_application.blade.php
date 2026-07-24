@extends('master')
@section('content')
<style>
.leave-app-info-strip{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
.leave-app-info-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;box-shadow:0 8px 20px rgba(15,23,42,.05)}
.leave-app-info-panel .panel-label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#607086;margin-bottom:.6rem}
.leave-app-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.leave-app-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.leave-app-table-shell table{margin-bottom:0}
.leave-app-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.leave-app-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
@media(max-width:767.98px){.leave-app-info-strip{grid-template-columns:1fr}}
</style>

{{-- Credit Balances + Legends --}}
<div class="leave-app-info-strip">
    <div class="leave-app-info-panel">
        <div class="d-flex justify-content-between align-items-center">
            <div class="panel-label">Credit Balances</div>
            <button class="btn btn-sm btn-primary" id="btn_reload">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="row mt-1" id="credit_balances"></div>
    </div>
    <div class="leave-app-info-panel">
        <div class="panel-label">Legends</div>
        <div class="row mt-1" id="legend_list"></div>
    </div>
</div>

{{-- Main board with tabs --}}
<div class="leave-app-board">
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
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-lwop" data-bs-toggle="tab"
                    data-bs-target="#pane-lwop" type="button" role="tab">
                LWOP
            </button>
        </li>
    </ul>

    <div class="tab-content" id="leaveTabContent">

        {{-- Tab 1: Leave Applications --}}
        <div class="tab-pane fade show active" id="pane-leave-applications" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#0f172a">Leave Applications</h5>
                    <span style="font-size:.9rem;color:#64748b">Your filed leave requests and approval status.</span>
                </div>
                <button class="btn btn-success btn-sm" id="btn_file_leave">
                    <i class="bi bi-plus-lg me-1"></i> File Leave
                </button>
            </div>
            <div class="table-responsive leave-app-table-shell">
                <table class="table text-center table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates Applied</th>
                        <th>Status</th>
                        <th style="width:4%"></th>
                    </tr>
                    </thead>
                    <tbody id="leave_history_list"></tbody>
                </table>
            </div>
            <div class="mt-3" id="pagination_container">
                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <div id="page_items_count" class="small text-muted"></div>
                    <nav aria-label="Leave application pagination">
                        <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                    </nav>
                    <div id="page_indicator" class="small text-muted"></div>
                </div>
            </div>
        </div>

        {{-- Tab 2: Monetizations --}}
        <div class="tab-pane fade" id="pane-monetizations" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#0f172a">Leave Monetizations</h5>
                    <span style="font-size:.9rem;color:#64748b">Convert unused leave credits to cash.</span>
                </div>
                <button class="btn btn-success btn-sm" id="btn_file_monetization">
                    <i class="bi bi-plus-lg me-1"></i> Monetize
                </button>
            </div>
            <div class="table-responsive leave-app-table-shell">
                <table class="table text-center table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Days Requested</th>
                        <th>Filed Date</th>
                        <th>Status</th>
                        <th style="width:4%"></th>
                    </tr>
                    </thead>
                    <tbody id="monetization_history_list"></tbody>
                </table>
            </div>
            <div class="mt-3" id="monetization_pagination_container">
                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <div id="monetization_page_items_count" class="small text-muted"></div>
                    <nav aria-label="Monetization pagination">
                        <ul class="pagination pagination-sm mb-0" id="monetization_pagination_links"></ul>
                    </nav>
                    <div id="monetization_page_indicator" class="small text-muted"></div>
                </div>
            </div>
        </div>

        {{-- Tab 3: LWOP --}}
        <div class="tab-pane fade" id="pane-lwop" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0" style="color:#0f172a">LWOP Applications</h5>
                    <span style="font-size:.9rem;color:#64748b">Leave Without Pay — files even without available credits.</span>
                </div>
                <button class="btn btn-success btn-sm" id="btn_file_lwop">
                    <i class="bi bi-plus-lg me-1"></i> File LWOP
                </button>
            </div>
            <div class="table-responsive leave-app-table-shell">
                <table class="table text-center table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Dates Applied</th>
                        <th>Status</th>
                        <th style="width:4%"></th>
                    </tr>
                    </thead>
                    <tbody id="lwop_history_list"></tbody>
                </table>
            </div>
            <div class="mt-3" id="lwop_pagination_container">
                <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                    <div id="lwop_page_items_count" class="small text-muted"></div>
                    <nav aria-label="LWOP pagination">
                        <ul class="pagination pagination-sm mb-0" id="lwop_pagination_links"></ul>
                    </nav>
                    <div id="lwop_page_indicator" class="small text-muted"></div>
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
