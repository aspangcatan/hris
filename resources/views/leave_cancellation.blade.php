@extends('master')
@section('content')
<style>
    .cancel-board { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:1.5rem; box-shadow:0 18px 38px rgba(15,23,42,.08); }
    .cancel-head  { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .cancel-head h4 { margin:0; font-weight:700; color:#0f172a; }
    .cancel-head span { font-size:.9rem; color:#64748b; }
    .cancel-table-shell { border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#fff; }
    .cancel-table-shell table { margin-bottom:0; }
    .cancel-table-shell thead th { background:#f8fafc; color:#0f172a; font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; }
    .cancel-table-shell tbody td { color:#1e293b; font-weight:500; vertical-align:middle; }
</style>

<div class="cancel-board">
    <div class="cancel-head">
        <div>
            <h4>Leave Cancellation</h4>
            <span>File a cancellation for your approved leave dates.</span>
        </div>
        <button class="btn btn-sm btn-danger" id="btn_file_cancellation">
            <i class="bi bi-plus-lg"></i> File Cancellation
        </button>
    </div>

    <div class="cancel-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead>
                <tr>
                    <th>Dates to Cancel</th>
                    <th>Reason</th>
                    <th>Filed On</th>
                    <th>Status</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="cancellation_list">
                <tr><td colspan="5" class="text-muted">No cancellations filed.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-3" id="cancel_pagination_container" style="display:none;">
        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
            <div id="cancel_page_count" class="small text-muted"></div>
            <nav><ul class="pagination pagination-sm mb-0" id="cancel_pagination_links"></ul></nav>
        </div>
    </div>
</div>

{{-- Filing Modal --}}
<div class="modal fade" id="fileCancellationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">File Leave Cancellation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Dates to Cancel
                        <small class="text-muted fw-normal">(from approved leaves)</small>
                    </label>
                    <div id="cancel_dates_loading" class="text-muted small">Loading dates...</div>
                    <div id="cancel_dates_container" style="max-height:260px; overflow-y:auto; border:1px solid #dee2e6; border-radius:8px; padding:.75rem; display:none;"></div>
                    <div id="cancel_dates_empty" class="text-muted small" style="display:none;">No approved leave dates available to cancel.</div>
                </div>
                <div class="mb-3">
                    <label for="cancel_reason" class="form-label fw-bold">Reason for Cancellation</label>
                    <textarea class="form-control" id="cancel_reason" rows="3" placeholder="State your reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-danger" id="btn_submit_cancellation">Submit</button>
            </div>
        </div>
    </div>
</div>

{{-- View Modal --}}
<div class="modal fade" id="viewCancellationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border border-dark">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title">Cancellation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                @include('partials.cancellation_steps')
                <p class="mt-4"><strong>Reason:</strong> <span id="vc_reason"></span></p>
                <p><strong>Dates to Cancel:</strong></p>
                <ul id="vc_dates" class="list-group list-group-flush mb-3"></ul>
                <div>
                    <label class="form-label fw-bold">Remarks History:</label>
                    <div id="vc_remarks" class="border rounded p-3 bg-light" style="max-height:160px;overflow-y:auto;"></div>
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
    @include('js.leave_cancellation')
@stop
