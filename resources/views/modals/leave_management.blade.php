<div class="modal fade" id="leave_type_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-airplane text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center">Manage Leave Types</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="description" placeholder="">
                        <label>Description</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="code" placeholder="">
                        <label>Code</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="monthly_increment" placeholder="">
                        <label>Monthly Deduction</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="credit_deduction" placeholder="">
                        <label>Credit deduction</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" onclick="store()">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
