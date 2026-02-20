<div class="modal fade" id="service_records_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-postcard-fill text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="service_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="text-secondary font-weight-bold fs-4 text-center">Service Records Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="service_from" placeholder="">
                        <label>Date from</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="service_to" placeholder="">
                        <label>Date to</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="service_designation" placeholder="">
                        <label>Designation</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="service_status" placeholder="">
                        <label>Status</label>
                    </div>
                    <div class="form-floating mb-3" id="to_container">
                        <input type="number" class="form-control" id="service_salary" placeholder="">
                        <label>Salary</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="service_office" placeholder="">
                        <label>Office of Assignment</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="service_leave" placeholder="">
                        <label>Leave of absence w/o pay</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="service_separation" placeholder="">
                        <label>Separation</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-modal-close" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-modal-save" id="btn_save_service">
                        <i class="bi bi-check2-circle me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
