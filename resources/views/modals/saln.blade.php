<div class="modal fade premium-modal" id="children_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
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
                <div class="modal-title-text">Children Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="children_name" placeholder="">
                        <label>Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="children_birthdate" placeholder="">
                        <label>Birthdate</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_children">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="real_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="real_properties_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Real Properties Information</div>
                <div class="mt-3">
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
                        <input type="number" class="form-control" id="real_assessed_value" placeholder="">
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
                        <input type="number" class="form-control" id="real_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_real">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="spouse_real_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="spouse_real_properties_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Spouse Real Properties Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_real_description" placeholder="">
                        <label>Description</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_real_kind" placeholder="">
                        <label>Kind</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_real_location" placeholder="">
                        <label>Exact Location</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="spouse_real_assessed_value" placeholder="">
                        <label>Assessed value</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_real_current_fair" placeholder="">
                        <label>Current fair market value</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="spouse_real_acquisition_year" placeholder="">
                        <label>Acquisition year</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_real_acquisition_mode" placeholder="">
                        <label>Acquisition mode</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="spouse_real_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_spouse_real">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="personal_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="personal_properties_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Personal Properties Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="personal_gadget" placeholder="">
                        <label>Gadgets/Appliances</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="personal_various_years" placeholder="">
                        <label>Years Acquired</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="personal_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_personal">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="spouse_personal_properties_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="spouse_personal_properties_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Spouse Personal Properties Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_personal_gadget" placeholder="">
                        <label>Gadgets/Appliances</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_personal_various_years" placeholder="">
                        <label>Years Acquired</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="spouse_personal_acquisition_cost" placeholder="">
                        <label>Acquisition cost</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_spouse_personal">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="liabilities_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="liabilities_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Liabilities Information</div>
                <div class="mt-3">
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
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_liabilities">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="spouse_liabilities_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="spouse_liabilities_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Spouse Liabilities Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_liabilities_nature" placeholder="">
                        <label>Nature</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_liabilities_creditors_name" placeholder="">
                        <label>Name of creditors</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="spouse_liabilities_outstanding_balance" placeholder="">
                        <label>Outstanding balance</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_spouse_liabilities">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="business_interest_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="business_interest_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Business Interest Information</div>
                <div class="mt-3">
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
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_business_interest">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="spouse_business_interest_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="spouse_business_interest_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Spouse Business Interest Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_business_enterprise" placeholder="">
                        <label>Business enterprise</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_business_address" placeholder="">
                        <label>Business Address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_business_nature" placeholder="">
                        <label>Nature of business</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="spouse_business_date_acquisition" placeholder="">
                        <label>Date of acquisition</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_spouse_business_interest">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="government_relative_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
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
                <div class="modal-title-text">Government Relative Information</div>
                <div class="mt-3">
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
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_government_relative">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="marriages_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-text text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="marriages_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="modal-title-text">Spouse Information</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="spouse_name" placeholder="">
                        <label>Name</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_save_marriage">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="saln_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-printer text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">PRINT SALN</div>
                <div class="mt-3">
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="print_date" placeholder="">
                        <label>Date</label>
                    </div>
                </div>
                <div class="form-floating mb-3">
                    <input type="date" class="form-control" id="saln_sworn_date" placeholder="">
                    <label>Subscribe and Sworn Date</label>
                </div>
                <div class="form-floating mb-3">
                    <select class="form-control" id="spouse_signature">
                        <option value="0">No</option>
                        <option value="1" selected>Yes</option>
                    </select>
                    <label>Can the spouse able to sign?</label>
                </div>

                <div class="saln-print-rows">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <div class="saln-print-rows-title"><i class="bi bi-table me-1"></i>Rows per table</div>
                        <button type="button" class="btn btn-link btn-sm p-0" id="btn_reset_saln_rows">Reset to default</button>
                    </div>
                    <div class="saln-print-rows-hint mb-2">
                        Rows printed on the main form. Empty rows show N/A; entries beyond this move to Annex B.
                    </div>
                    <div class="row g-2">
                        @foreach([
                            'rows_real_properties'     => ['Real properties', 5],
                            'rows_personal_properties' => ['Personal properties', 7],
                            'rows_liabilities'         => ['Liabilities', 4],
                            'rows_business'            => ['Business interests', 3],
                            'rows_relatives'           => ['Relatives in government', 5],
                        ] as $rowId => [$rowLabel, $rowDefault])
                            <div class="col-6 {{ $loop->last ? 'col-sm-12' : '' }}">
                                <div class="form-floating">
                                    <input type="number" class="form-control saln-row-setting" id="{{ $rowId }}"
                                           min="1" max="30" step="1" value="{{ $rowDefault }}" data-default="{{ $rowDefault }}">
                                    <label for="{{ $rowId }}">{{ $rowLabel }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_confirm_print">PRINT</button>
                </div>
            </div>
        </div>
    </div>
</div>
