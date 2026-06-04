<div class="modal fade" id="monetize_leave_modal" tabindex="-1" aria-labelledby="monetizeLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="monetizeLeaveModalLabel">Leave Monetization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Add one or more leave types you want to monetize. Credits will be deducted on submission.</p>

                <table class="table table-sm align-middle" id="monetize_rows_table">
                    <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th style="width:160px">Days to Monetize</th>
                            <th class="text-muted small" style="width:140px">Available</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="monetize_rows_body"></tbody>
                </table>

                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_add_monetize_row">
                    <i class="bi bi-plus-lg me-1"></i> Add Leave Type
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CLOSE</button>
                <button type="button" class="btn btn-success" id="btn_submit_monetization">SUBMIT</button>
            </div>
        </div>
    </div>
</div>
