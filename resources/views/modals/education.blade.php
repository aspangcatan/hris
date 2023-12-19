<div class="modal fade" id="education_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-postcard-fill text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-white bg-danger rounded p-2 mb-2 d-none" id="education_error">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div>There are some errors, please correct them below:</div>
                    </div>
                    <ul class="error_list"></ul>
                </div>
                <div class="text-secondary font-weight-bold fs-4 text-center">Educational Background</div>
                <div class="mt-3">
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Level</label>
                        <div class="col-md-8 col-lg-9">
                            <select class="form-control" id="education_level">
                                <option value="">Select level</option>
                                <option value="Elementary">Elementary</option>
                                <option value="Secondary">Secondary</option>
                                <option value="Vocational/Trade Course">Vocational/Trade Course</option>
                                <option value="College">College</option>
                                <option value="Graduate Studies">Graduate Studies</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
                        <div class="col-md-8 col-lg-9">
                            <input type="text" class="form-control" id="school_name"/>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
                        <div class="col-md-8 col-lg-9">
                            <input type="text" class="form-control" id="education_degree"/>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
                        <div class="col-md-8 col-lg-9">
                            <div class="row">
                                <div class="col-md-6 col-lg-6">
                                    <select class="form-control" id="attendance_from">
                                        <option value="" selected>From</option>
                                        @for($year=1950; $year<=date("Y"); $year++)
                                            <option>{{ $year }}</option>
                                        @endfor
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-6">
                                    <select class="form-control" id="attendance_to">
                                        <option value="" selected>To</option>
                                        @for($year=1950; $year<=date("Y"); $year++)
                                            <option>{{ $year }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not graduated)</label>
                        <div class="col-md-8 col-lg-9">
                            <input type="text" class="form-control" id="highest_level"/>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
                        <div class="col-md-8 col-lg-9">
                            <select class="form-control" id="year_graduated">
                                <option value="" selected>Select year</option>
                                @for($year=1950; $year<=date("Y"); $year++)
                                    <option>{{ $year }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Scholarship/Academic honors received</label>
                        <div class="col-md-8 col-lg-9">
                            <input type="text" class="form-control" id="scholarship"/>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_save_education">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
