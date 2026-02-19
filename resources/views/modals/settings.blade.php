<div class="modal fade premium-modal" id="settings_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <div class="text-end">
                    <button type="button" style="background: transparent !important;" class="btn btn-sm" data-bs-dismiss="modal" aria-label="Close">X</button>
                </div>
                <h5 class="modal-title-text">Change Password</h5>
                <div class="text-white bg-danger rounded p-2 error_container d-none">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="form-label">
                    <label>Old password</label>
                    <input type="password" class="form-control premium-input" id="old_password" />
                </div>
                <div class="form-label">
                    <label>New password</label>
                    <input type="password" class="form-control premium-input" id="new_password" />
                </div>
                <div class="form-label">
                    <label>Confirm password</label>
                    <input type="password" class="form-control premium-input" id="confirm_password"/>
                </div>

                <button class="btn btn-primary mt-2 float-end px-4" id="btn_change_password">Proceed</button>
            </div>
        </div>
    </div>
</div>
