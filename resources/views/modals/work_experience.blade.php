<div class="modal fade" id="work_experience_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-postcard-fill text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Work Experience Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="work_position" placeholder="">
                        <label>Job Title</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="work_agency" placeholder="">
                        <label>Company</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="work_from" placeholder="">
                        <label>From</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="work_to" placeholder="">
                        <label>To</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="work_salary" placeholder="">
                        <label>Salary</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="work_salary_grade" placeholder="">
                        <label>Salary Grade</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="work_appointment_status" placeholder="">
                        <label>Appointment Status</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="work_government">
                            <option value="" selected></option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <label>Government Service (Y/N)</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="work_current_work">
                            <option value="" selected></option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <label>Current Work (Y/N)</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_work">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
