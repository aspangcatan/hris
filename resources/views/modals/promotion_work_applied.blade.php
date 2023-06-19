<div class="modal fade" id="promotion_applied_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-printer text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center">Work Information</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="position_applied" placeholder="">
                        <label>Position applied</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="date_applied" placeholder="">
                        <label>Date applied</label>
                    </div>
                </div>
                <div id="work_experience_container">
                    <hr/>
                    <div class="d-flex justify-content-between mb-2">
                        <div class="fs-5">Related Work Experience</div>
                        <button class="btn btn-sm btn-success" id="btn_add_related">ADD NEW</button>
                    </div>
                    <div id="related_work_list"></div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_confirm_save">SAVE</button>
                    <button class="btn btn-lg btn-danger" id="btn_print_work">PRINT</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="edit_promotion_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-person-workspace text-white" style="font-size: 80px;"></i>
                </div>
                <div class="text-secondary font-weight-bold fs-4 text-center">Work Information</div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center"></div>
                <div class="mt-3 text-secondary">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="work_from" placeholder="">
                                <label>From</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-1">
                                <input type="date" class="form-control" id="work_to" placeholder="">
                                <label>To</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="work_checkbox">
                                <label class="form-check-label" for="flexCheckDefault">
                                    Present
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="work_position" placeholder="">
                                <label>Position</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="work_office" placeholder="">
                                <label>Name of Office/Unit</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="work_supervisor" placeholder="">
                                <label>Immediate Supervisor</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="work_agency" placeholder="">
                                <label>Name of Agency/Organization and Location</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label>List of Accomplishments and Contributions (if any)</label>
                            <select id="work_accomplishment"></select>
                        </div>
                        <div class="col-md-12 mt-2">
                            <label>Summary of Actual Duties</label>
                            <select id="work_duties"></select>
                        </div>
                    </div>

                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_related">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
