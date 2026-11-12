@extends('master')
@section('content')
<style>
.promo-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.promo-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.promo-head-title h4{margin:0;font-weight:700;color:#0f172a}
.promo-head-title span{font-size:.9rem;color:#64748b}
.promo-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.promo-table-shell table{margin-bottom:0}
.promo-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.promo-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
.promo-table-shell tbody tr.promo-row{cursor:pointer}
.promo-table-shell tbody tr.promo-row:hover td{background:#f8fafc}
.promo-count{display:inline-block;min-width:1.9rem;padding:.15rem .55rem;border-radius:999px;background:#e0e7ff;color:#1e3a8a;font-size:.78rem;font-weight:700}
.promo-count.is-empty{background:#fef3c7;color:#92400e}
.promo-empty{padding:2.5rem 1rem;color:#64748b}
.promo-empty i{font-size:2rem;display:block;margin-bottom:.5rem;color:#94a3b8}
</style>

<div class="promo-board">
    <div class="promo-head">
        <div class="promo-head-title">
            <h4>Positions Applied</h4>
            <span>Record each position you apply for, then add the related work experience to print.</span>
        </div>
        <button class="btn btn-success btn-sm" id="btn_add_position">
            <i class="bi bi-plus-lg me-1"></i> New Application
        </button>
    </div>

    <div class="table-responsive promo-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
            <tr>
                <th class="text-start">Position Applied</th>
                <th style="width:16%">Date Applied</th>
                <th style="width:16%">Work Experience</th>
                <th style="width:14%">Actions</th>
            </tr>
            </thead>
            <tbody id="work_list"></tbody>
        </table>
    </div>
</div>

@include('modals.promotion_work_applied')
@stop
@section('js')
    @include('js.promotion_work_applied')
@stop
