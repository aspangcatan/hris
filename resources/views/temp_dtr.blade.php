@extends('master')
@section('content')
<style>
.tempdtr-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.tempdtr-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.tempdtr-head-title h4{margin:0;font-weight:700;color:#0f172a}
.tempdtr-head-title span{font-size:.9rem;color:#64748b}
.tempdtr-filter{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1.25rem}
.tempdtr-filter .form-control{border-radius:12px;border:1px solid #d8e1ee;background:#fff}
.tempdtr-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;background:#fff}
.tempdtr-table-shell table{margin-bottom:0}
.tempdtr-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.tempdtr-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
</style>

<div class="tempdtr-board">
    <div class="tempdtr-head">
        <div class="tempdtr-head-title">
            <h4>Personnel List</h4>
            <span>Search and manage employee DTR records.</span>
        </div>
    </div>

    <div class="tempdtr-filter">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <input class="form-control form-control-sm" placeholder="Search by firstname" id="search_fname"/>
            </div>
            <div class="col-md-4">
                <input class="form-control form-control-sm" placeholder="Search by lastname" id="search_lname"/>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" id="btn_search">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive tempdtr-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
            <tr>
                <th></th>
                <th>Firstname</th>
                <th>Middlename</th>
                <th>Lastname</th>
                <th>Suffix</th>
                <th>Designation</th>
                <th>Section</th>
                <th style="width:4%"></th>
            </tr>
            </thead>
            <tbody id="employee_list"></tbody>
        </table>
    </div>

    <div class="mt-3" id="pagination_container">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="page_items_count" class="small text-muted"></div>
            <nav aria-label="Temp dtr personnel pagination">
                <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
            </nav>
            <div id="page_indicator" class="small text-muted"></div>
        </div>
    </div>
</div>

@include('modals.temp_dtr')
@stop
@section('js')
    @include('js.temp_dtr')
@stop
