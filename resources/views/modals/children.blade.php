<div class="modal fade premium-modal" id="children_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-person-fill-add text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="children_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Children Information</div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Name</label>
                        <input type="text" class="form-control premium-input" id="children_name" placeholder="Enter complete name">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Birthdate</label>
                        <input type="date" class="form-control premium-input" id="children_birthdate" placeholder="">
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary rounded-xl border-0 px-4 py-2" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success rounded-xl border-0 px-4 py-2 font-semibold" id="btn_save_children">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
