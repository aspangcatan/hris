<div class="modal fade" id="learning_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-postcard-fill text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Learning & Development Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="learning_title" placeholder="">
                        <label>Title</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="learning_attendance_from" placeholder="">
                        <label>From</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="learning_attendance_to" placeholder="">
                        <label>To</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="learning_number_hours" placeholder="">
                        <label>Number hours</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="learning_type" placeholder="">
                        <label>Type</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="learning_conducted_by" placeholder="">
                        <label>Conducted/Sponsored by</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_learning">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
