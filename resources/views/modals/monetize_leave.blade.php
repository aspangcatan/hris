<div class="modal fade" id="monetize_leave_modal" tabindex="-1" aria-labelledby="monetizeLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="monetizeLeaveModalLabel">Leave Monetization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="monetize_leave_form">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Leave Type</label>
                        <select class="form-select" id="monetize_leave_type" name="leave_type_id" required>
                            <option value="">-- Select Leave Type --</option>
                        </select>
                    </div>

                    <div class="mb-1 d-none" id="monetize_balance_hint">
                        <small class="text-muted">
                            Available: <span id="monetize_available_days" class="fw-bold text-success">0.000</span> days
                            (<span id="monetize_available_credits" class="fw-bold">0.000</span> credits)
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Days to Monetize</label>
                        <input type="number" class="form-control" id="monetize_days" name="days_requested"
                               min="0.001" step="0.001" placeholder="e.g. 5.000" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">CLOSE</button>
                <button type="button" class="btn btn-success" id="btn_submit_monetization">SUBMIT</button>
            </div>
        </div>
    </div>
</div>
