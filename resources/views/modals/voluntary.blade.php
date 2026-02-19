<div class="modal fade premium-modal" id="voluntary_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-postcard-fill text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="voluntary_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Voluntary Work Information</div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Name & Address of Organization</label>
                        <input type="text" class="form-control premium-input" id="voluntary_organization_address" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Position / Nature of Work</label>
                        <input type="text" class="form-control premium-input" id="voluntary_position" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">From</label>
                        <input type="date" class="form-control premium-input" id="voluntary_working_from" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">To</label>
                        <input type="date" class="form-control premium-input" id="voluntary_working_to" placeholder="">
                    </div>

                    <div class="form-check rounded-xl bg-slate-100 px-3 py-2">
                        <input class="form-check-input" type="checkbox" id="voluntary_currently_working">
                        <label class="form-check-label" for="voluntary_currently_working">
                            Check if currently working
                        </label>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Number Hours</label>
                        <input type="number" class="form-control premium-input" id="voluntary_number_hours" placeholder="">
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary rounded-xl border-0 px-4 py-2" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success rounded-xl border-0 px-4 py-2 font-semibold" id="btn_save_voluntary">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
