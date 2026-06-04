@extends('master')
@section('content')
<style>
.leave-hist-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.leave-hist-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.leave-hist-head-title h4{margin:0;font-weight:700;color:#0f172a}
.leave-hist-head-title span{font-size:.9rem;color:#64748b}
.leave-hist-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.leave-hist-table-shell table{margin-bottom:0}
.leave-hist-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.leave-hist-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
</style>

<div class="leave-hist-board">
    <div class="leave-hist-head">
        <div class="leave-hist-head-title">
            <h4>Leave History</h4>
            <span>Your filed leave applications and their approval status.</span>
        </div>
        <button class="btn btn-success btn-sm" id="btn_file_leave">
            <i class="bi bi-plus-lg me-1"></i> File Leave
        </button>
    </div>

    <div class="table-responsive leave-hist-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
            <tr>
                <th>Leave Type</th>
                <th>Details</th>
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
