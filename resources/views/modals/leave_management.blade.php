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
                <div class="modal-title-text">Manage Leave Types</div>
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
                    <div class="form-floating mb-3">
                        <select class="form-control" id="monetizable">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                        <label>Monetizable</label>
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
