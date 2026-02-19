<div class="modal fade premium-modal" id="learning_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-postcard-fill text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="learning_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Learning & Development Information</div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Title</label>
                        <input type="text" class="form-control premium-input" id="learning_title" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">From</label>
                        <input type="date" class="form-control premium-input" id="learning_attendance_from" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">To</label>
                        <input type="date" class="form-control premium-input" id="learning_attendance_to" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Number Hours</label>
                        <input type="number" class="form-control premium-input" id="learning_number_hours" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Type</label>
                        <input type="text" class="form-control premium-input" id="learning_type" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Conducted / Sponsored By</label>
                        <input type="text" class="form-control premium-input" id="learning_conducted_by" placeholder="">
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary rounded-xl border-0 px-4 py-2" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success rounded-xl border-0 px-4 py-2 font-semibold" id="btn_save_learning">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
