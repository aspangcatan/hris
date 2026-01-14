<div class="modal fade" id="civil_service_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-award-fill text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="civil_service_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="text-secondary font-weight-bold fs-4 text-center">Civil Service Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="civil_service_career_service" placeholder="">
                        <label>Career Service</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="civil_service_rating" placeholder="">
                        <label>Rating</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="civil_service_examination_date" placeholder="">
                        <label>Date of Examination (From)</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="civil_service_examination_end_date" placeholder="">
                        <label>Date of Examination (To)</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="civil_service_examination_place" placeholder="">
                        <label>Place of Examination</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="civil_service_license_no" placeholder="">
                        <label>License No.</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="civil_service_license_expiry" placeholder="">
                        <label>Date of Validity</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_civil_service">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
