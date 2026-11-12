@extends('master')
@section('content')
<style>
    .reoans-board { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:1.5rem; box-shadow:0 18px 38px rgba(15,23,42,.08); }
    .reoans-head  { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .reoans-head h4 { margin:0; font-weight:700; color:#0f172a; }
    .reoans-head span { font-size:.9rem; color:#64748b; }

    .reoans-tabs { gap:.35rem; border-bottom:1px solid #e2e8f0; margin-bottom:1rem; flex-wrap:wrap; }
    .reoans-tabs .nav-link { border:0; border-bottom:3px solid transparent; border-radius:0; color:#64748b; font-weight:600; padding:.6rem 1rem; text-align:left; }
    .reoans-tabs .nav-link small { display:block; font-size:.72rem; font-weight:500; color:#94a3b8; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .reoans-tabs .nav-link.active { color:#00236f; border-bottom-color:#00236f; background:transparent; }

    .reoans-filter { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1rem; margin-bottom:1rem; }
    .reoans-filter .form-control { border-radius:12px; border:1px solid #d8e1ee; background:#fff; }
    .reoans-status .btn { border-radius:999px !important; margin-right:.35rem; font-weight:600; }
    .reoans-status .badge { margin-left:.25rem; }

    .reoans-bulk { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .reoans-bulk-count { font-size:.9rem; color:#475569; }

    .reoans-table-shell { border:1px solid #e2e8f0; border-radius:16px; overflow:auto; background:#fff; }
    .reoans-table-shell table { margin-bottom:0; font-size:13px; }
    .reoans-table-shell thead th { background:#f8fafc; color:#0f172a; font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; white-space:nowrap; }
    .reoans-table-shell tbody td { color:#1e293b; vertical-align:middle; }
    .reoans-table-shell .form-check-input { cursor:pointer; width:1.1em; height:1.1em; }
    .reoans-remarks { max-width:260px; white-space:pre-wrap; color:#64748b; }
    .reoans-empty { text-align:center; color:#94a3b8; padding:2rem 1rem !important; }
</style>

<div class="reoans-board">
    <div class="reoans-head">
        <div>
            <h4>Reorientation Answers</h4>
            <span>Check the employees' post test answers per module and mark them Passed or Failed.</span>
        </div>
    </div>

    {{-- Module tabs --}}
    <ul class="nav reoans-tabs" id="reoans_tabs" role="tablist">
        @foreach($modules as $no => $module)
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-module="{{ $no }}" role="tab">
                    Module {{ $no }} <span class="badge bg-warning text-dark reoans-tab-pending d-none" data-module="{{ $no }}"></span>
                    <small>{{ $module['title'] }}</small>
                </button>
            </li>
        @endforeach
    </ul>

    {{-- Filters --}}
    <div class="reoans-filter">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <div class="btn-group reoans-status flex-wrap" role="group" aria-label="Status filter">
                    <input type="radio" class="btn-check" name="reoans_status" id="reoans_status_not_started" value="not_started">
                    <label class="btn btn-sm btn-outline-secondary" for="reoans_status_not_started">Not yet <span class="badge bg-secondary" data-count="not_started">0</span></label>

                    <input type="radio" class="btn-check" name="reoans_status" id="reoans_status_submitted" value="submitted" checked>
                    <label class="btn btn-sm btn-outline-warning" for="reoans_status_submitted">Pending <span class="badge bg-warning text-dark" data-count="submitted">0</span></label>

                    <input type="radio" class="btn-check" name="reoans_status" id="reoans_status_passed" value="passed">
                    <label class="btn btn-sm btn-outline-success" for="reoans_status_passed">Passed <span class="badge bg-success" data-count="passed">0</span></label>

                    <input type="radio" class="btn-check" name="reoans_status" id="reoans_status_failed" value="failed">
                    <label class="btn btn-sm btn-outline-danger" for="reoans_status_failed">Failed <span class="badge bg-danger" data-count="failed">0</span></label>
                </div>
            </div>
            <div class="col-lg-4">
                <input type="text" class="form-control form-control-sm" id="reoans_search" placeholder="Search employee name">
            </div>
        </div>
    </div>

    {{-- Bulk actions (hidden for "Not yet": there are no answers to check) --}}
    <div class="reoans-bulk" id="reoans_bulk">
        <div class="reoans-bulk-count"><strong id="reoans_selected_count">0</strong> selected</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-danger reoans-action-btn" data-status="failed" disabled>
                <i class="bi bi-x-circle"></i> Mark as Failed
            </button>
            <button type="button" class="btn btn-sm btn-success reoans-action-btn" data-status="passed" disabled>
                <i class="bi bi-check-circle"></i> Mark as Passed
            </button>
        </div>
    </div>

    <div class="reoans-table-shell">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width:3%" class="text-center reoans-check-col">
                        <input type="checkbox" class="form-check-input" id="reoans_select_all" title="Select all">
                    </th>
                    <th>Employee</th>
                    <th>Section</th>
                    <th>Submitted</th>
                    <th>Attempt</th>
                    <th>Checked By</th>
                    <th>Remarks</th>
                    <th style="width:6%" class="text-center reoans-view-col">Answers</th>
                </tr>
            </thead>
            <tbody id="reoans_list">
                <tr><td colspan="8" class="reoans-empty">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-3" id="reoans_pagination_container" style="display:none;">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="reoans_page_count" class="small text-muted"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="reoans_pagination_links"></ul></nav>
        </div>
    </div>
</div>

{{-- Answers modal --}}
<div class="modal fade" id="reoansViewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="reoans_view_name"></h5>
                    <div class="small text-muted" id="reoans_view_title"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reoans_view_answers"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger reoans-single-btn" data-status="failed"><i class="bi bi-x-circle"></i> Mark as Failed</button>
                <button type="button" class="btn btn-success reoans-single-btn" data-status="passed"><i class="bi bi-check-circle"></i> Mark as Passed</button>
            </div>
        </div>
    </div>
</div>

{{-- Pass / Fail confirmation modal --}}
<div class="modal fade" id="reoansActionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reoans_action_title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="reoans_action_text"></p>
                <label class="form-label fw-semibold" for="reoans_action_remarks">Remarks <span id="reoans_action_required" class="text-danger">*</span></label>
                <textarea class="form-control" id="reoans_action_remarks" rows="3"></textarea>
                <div class="form-text" id="reoans_action_hint"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" id="btn_reoans_action_confirm">Confirm</button>
            </div>
        </div>
    </div>
</div>
@stop
@section('js')
    @include('js.reorientation_answers')
@stop
