<div class="modal fade premium-modal" id="monetize_leave_modal" tabindex="-1" aria-labelledby="monetizeLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-cash-stack text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Leave Monetization</div>
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
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">CLOSE</button>
                <button type="button" class="btn btn-success btn-modal-save" id="btn_submit_monetization">SUBMIT</button>
            </div>
        </div>
    </div>
</div>
