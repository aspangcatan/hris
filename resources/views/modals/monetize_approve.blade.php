<div class="modal fade premium-modal" id="monetize_approve_modal" tabindex="-1" aria-labelledby="monetizeApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-cash-coin text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Monetization Request</div>
                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="fw-semibold text-muted small">Employee</div>
                            <div id="ma_employee" class="fw-bold">—</div>
                        </div>
                        <div class="col-6">
                            <div class="fw-semibold text-muted small">Leave Type</div>
                            <div id="ma_leave_type" class="fw-bold">—</div>
                        </div>
                        <div class="col-6">
                            <div class="fw-semibold text-muted small">Days Requested</div>
                            <div id="ma_days" class="fw-bold">—</div>
                        </div>
                        <div class="col-6">
                            <div class="fw-semibold text-muted small">Filed Date</div>
                            <div id="ma_filed_date" class="fw-bold">—</div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Remarks <span class="text-muted small">(optional)</span></label>
                    <textarea class="form-control" id="ma_remarks" rows="3" placeholder="Enter remarks..."></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">CLOSE</button>
                <button type="button" class="btn btn-danger" id="btn_reject_monetization">REJECT</button>
                <button type="button" class="btn btn-success btn-modal-save" id="btn_approve_monetization">APPROVE</button>
            </div>
        </div>
    </div>
</div>
