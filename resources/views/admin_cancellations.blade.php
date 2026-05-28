@extends('master')
@section('content')
<style>
    .cancel-board { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:1.5rem; box-shadow:0 18px 38px rgba(15,23,42,.08); }
    .cancel-head  { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .cancel-head h4 { margin:0; font-weight:700; color:#0f172a; }
    .cancel-head span { font-size:.9rem; color:#64748b; }
    .cancel-filter { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1rem; margin-bottom:1.25rem; }
    .cancel-filter .form-control { border-radius:12px; border:1px solid #d8e1ee; background:#fff; }
    .cancel-table-shell { border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#fff; }
    .cancel-table-shell table { margin-bottom:0; }
    .cancel-table-shell thead th { background:#f8fafc; color:#0f172a; font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; }
    .cancel-table-shell tbody td { color:#1e293b; font-weight:500; vertical-align:middle; }
</style>

<div class="cancel-board">
    <div class="cancel-head">
        <div>
            <h4>All Cancellation Requests</h4>
            <span>View all leave cancellation forms filed by employees.</span>
        </div>
    </div>

    <div class="cancel-filter">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <div class="form-floating">
                    <input type="text" class="form-control form-control-sm" id="adm_search_employee" placeholder="Search employee">
                    <label>Search Employee</label>
                </div>
            </div>
        </div>
    </div>

    <div class="cancel-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Dates to Cancel</th>
                    <th>Reason</th>
                    <th>Filed On</th>
                    <th>Status</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="adm_list">
                <tr><td colspan="6" class="text-muted">No records.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-3" id="adm_pagination_container" style="display:none;">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="adm_page_count" class="small text-muted"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="adm_pagination_links"></ul></nav>
        </div>
    </div>
</div>

{{-- View Modal --}}
<div class="modal fade" id="viewAdminCancellationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border border-dark">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title">Cancellation Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                @include('partials.cancellation_steps')
                <p class="mt-2"><strong>Employee:</strong> <span id="adm_name"></span></p>
                <p><strong>Reason:</strong> <span id="adm_reason"></span></p>
                <p><strong>Dates to Cancel:</strong></p>
                <ul id="adm_dates" class="list-group list-group-flush mb-3"></ul>
                <div>
                    <label class="form-label fw-bold">Remarks History:</label>
                    <div id="adm_remarks" class="border rounded p-3 bg-light" style="max-height:160px;overflow-y:auto;"></div>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@stop
@section('js')
    @include('js.admin_cancellations')
@stop
