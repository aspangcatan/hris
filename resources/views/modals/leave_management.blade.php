<div class="modal fade premium-modal" id="leave_type_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-event text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text" id="leave_type_modal_title">Add Leave Type</div>
                <input type="hidden" id="leave_type_id" value="">

                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="description" placeholder="">
                        <label>Description</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="code" placeholder="">
                        <label>Code</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="leave_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <label>Status</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="monthly_earned">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <label>Monthly Earned</label>
                    </div>
                    {{-- Hidden since 2026-11-11: earned credits are reduced by LWOP only (late/UT and personal pass slips are charged to salary). Kept so saving a leave type keeps its stored value. --}}
                    <div class="form-floating mb-3 d-none">
                        <select class="form-control" id="deduct_tardiness">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                        <label>Deduct Late/Undertime from Earned Credits</label>
                        <div class="form-text">Yes for Vacation Leave only (CSC). No = always earns the full monthly amount.</div>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="deduction_unit">
                            <option value="hours">Hours (8 hours = 1.000 point)</option>
                            <option value="days">Days (1 day = 1.000, half day = 0.500)</option>
                        </select>
                        <label>Deduct by</label>
                        <div class="form-text">Hours for VL, SL, CTO (a 10-hour day deducts 1.250). Days for leaves granted per day, such as Wellness or Special Privilege (filed as whole or half days, whatever the shift length).</div>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="monetizable">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                        <label>Monetizable</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="days_prior" step="1" placeholder="">
                        <label>Days Prior</label>
                        <div class="form-text">Working days of advance notice. Use a negative number (e.g. -1) to allow filing for past dates.</div>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="commutation">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                        <label>Commutation</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="default_credit" min="0" step="0.001" placeholder="">
                        <label>Default Credit</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="min_monetize_days" min="0" step="1" placeholder="">
                        <label>Min. Days per Monetization</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="min_retained_days" min="0" step="1" placeholder="">
                        <label>Min. Days Retained After Monetization</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" onclick="store()">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
