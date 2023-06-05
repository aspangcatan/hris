<div class="modal fade" id="children_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Children Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="children_name" placeholder="">
                        <label>Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="children_birthdate" placeholder="">
                        <label>Birthdate</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_children">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="real_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Real Properties Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_description" placeholder="">
                        <label>Description</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_kind" placeholder="">
                        <label>Kind</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_location" placeholder="">
                        <label>Exact Location</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_assessed_value" placeholder="">
                        <label>Assessed value</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_current_fair" placeholder="">
                        <label>Current fair market value</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="real_acquisition_year" placeholder="">
                        <label>Acquisition year</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_acquisition_mode" placeholder="">
                        <label>Acquisition mode</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="real_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_real">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="personal_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Personal Properties Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="personal_gadget" placeholder="">
                        <label>Gadgets/Appliances</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="personal_various_years" placeholder="">
                        <label>Various Years</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="personal_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_personal">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="liabilities_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Liabilities Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="liabilities_nature" placeholder="">
                        <label>Nature</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="liabilities_creditors_name" placeholder="">
                        <label>Name of creditors</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="liabilities_outstanding_balance" placeholder="">
                        <label>Outstanding balance</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_liabilities">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="business_interest_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Business Interest Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="business_enterprise" placeholder="">
                        <label>Business enterprise</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="business_address" placeholder="">
                        <label>Business Address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="business_nature" placeholder="">
                        <label>Nature of business</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="business_date_acquisition" placeholder="">
                        <label>Date of acquisition</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_business_interest">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="government_relative_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-fill-add text-white" style="font-size: 80px;"></i>
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
                <div class="text-secondary font-weight-bold fs-4 text-center">Government Relative Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="relative_name" placeholder="">
                        <label>Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="relative_relationship" placeholder="">
                        <label>Relationship</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="relative_position" placeholder="">
                        <label>Position</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="relative_agency" placeholder="">
                        <label>Agency/Office & Address</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_government_relative">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="saln_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-printer text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center">PRINT SALN</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="print_date" placeholder="">
                        <label>Date</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_confirm_print">PRINT</button>
                </div>
            </div>
        </div>
    </div>
</div>
