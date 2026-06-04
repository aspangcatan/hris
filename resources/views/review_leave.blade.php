@extends('master')
@section('content')
<style>
.review-leave-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.review-leave-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.review-leave-head-title h4{margin:0;font-weight:700;color:#0f172a}
.review-leave-head-title span{font-size:.9rem;color:#64748b}
.review-leave-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.review-leave-table-shell table{margin-bottom:0}
.review-leave-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.review-leave-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
</style>

<div class="review-leave-board">
    <div class="review-leave-head">
        <div class="review-leave-head-title">
            <h4>Review Leave Applications</h4>
            <span>Pending leave applications awaiting review.</span>
        </div>
    </div>

    <div class="table-responsive review-leave-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Division</th>
                <th>Unit</th>
                <th>Designation</th>
                <th>Leave Type</th>
                <th>Date Applied</th>
                <th style="width:4%"></th>
            </tr>
            </thead>
            <tbody id="leave_requests"></tbody>
        </table>
    </div>

    <div class="mt-3" id="pagination_container">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="page_items_count" class="small text-muted"></div>
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
