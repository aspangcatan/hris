<div class="modal fade premium-modal" id="work_experience_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-postcard-fill text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="work_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Work Experience Information</div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Job Title</label>
                        <input type="text" class="form-control premium-input" id="work_position" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Company</label>
                        <input type="text" class="form-control premium-input" id="work_agency" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Is this your current work? (Y/N)</label>
                        <select class="form-control premium-select" id="work_current_work">
                            <option value="" selected></option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">From</label>
                        <input type="date" class="form-control premium-input" id="work_from" placeholder="">
                    </div>
                    <div id="to_container">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">To</label>
                        <input type="date" class="form-control premium-input" id="work_to" placeholder="">
                    </div>
                    <div class="d-none">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Salary</label>
                        <input type="number" class="form-control premium-input" id="work_salary" placeholder="">
                    </div>
                    <div class="d-none">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Salary Grade (e.g. 00-0)</label>
                        <input type="text" class="form-control premium-input" id="work_salary_grade" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Appointment Status</label>
                        <input type="text" class="form-control premium-input" id="work_appointment_status" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Is this government service (Y/N)</label>
                        <select class="form-control premium-select" id="work_government">
                            <option value="" selected></option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary rounded-xl border-0 px-4 py-2" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success rounded-xl border-0 px-4 py-2 font-semibold" id="btn_save_work">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
