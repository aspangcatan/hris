<div class="text-white bg-danger rounded p-2 mt-5 d-none" id="education_error">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>There are some errors, please correct them below:</div>
    </div>
    <ul class="error_list"></ul>
</div>
<div>
    <h5 class="card-title fw-bold">Elementary</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="elementary_school_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="elementary_education_degree"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
        <div class="col-md-8 col-lg-9">
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="elementary_attendance_from">
                        <option value="" selected>From</option>
                        @for($year=1950; $year<=date("Y"); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="elementary_attendance_to">
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
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not
            graduated)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="elementary_highest_level"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="elementary_year_graduated">
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
            <input type="text" class="form-control" id="elementary_scholarship"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success btn-save-education" value="Elementary">SAVE CHANGES</button>
        </div>
    @endif
</div>
<hr/>
<div class="mt-3">
    <h5 class="card-title fw-bold">Secondary</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="secondary_school_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="secondary_education_degree"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
        <div class="col-md-8 col-lg-9">
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="secondary_attendance_from">
                        <option value="" selected>From</option>
                        @for($year=1950; $year<=date("Y"); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="secondary_attendance_to">
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
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not
            graduated)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="secondary_highest_level"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="secondary_year_graduated">
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
            <input type="text" class="form-control" id="secondary_scholarship"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success btn-save-education" value="Secondary">SAVE CHANGES</button>
        </div>
    @endif
</div>
<hr/>
<div class="mt-3">
    <h5 class="card-title fw-bold">Vocational/Trade Course</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="vocational_school_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="vocational_education_degree"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
        <div class="col-md-8 col-lg-9">
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="vocational_attendance_from">
                        <option value="" selected>From</option>
                        @for($year=1950; $year<=date("Y"); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="vocational_attendance_to">
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
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not
            graduated)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="vocational_highest_level"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="vocational_year_graduated">
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
            <input type="text" class="form-control" id="vocational_scholarship"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success btn-save-education" value="Vocational/Trade Course">SAVE
                CHANGES
            </button>
        </div>
    @endif
</div>
<hr/>
<div class="mt-3">
    <h5 class="card-title fw-bold">College</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="college_school_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="college_education_degree"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
        <div class="col-md-8 col-lg-9">
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="college_attendance_from">
                        <option value="" selected>From</option>
                        @for($year=1950; $year<=date("Y"); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="college_attendance_to">
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
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not
            graduated)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="college_highest_level"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="college_year_graduated">
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
            <input type="text" class="form-control" id="college_scholarship"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success btn-save-education" value="College">SAVE CHANGES</button>
        </div>
    @endif
</div>
<hr/>
<div class="mt-3">
    <h5 class="card-title fw-bold">Graduate Studies</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">School name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="graduate_school_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Basic Education/Degree/Course</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="graduate_education_degree"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Period of attendance</label>
        <div class="col-md-8 col-lg-9">
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="graduate_attendance_from">
                        <option value="" selected>From</option>
                        @for($year=1950; $year<=date("Y"); $year++)
                            <option>{{ $year }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-6 col-lg-6">
                    <select class="form-control" id="graduate_attendance_to">
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
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Highest Level/Units Earned (if not
            graduated)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="graduate_highest_level"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Year graduated</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="graduate_year_graduated">
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
            <input type="text" class="form-control" id="graduate_scholarship"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success btn-save-education" value="Graduate Studies">SAVE CHANGES
            </button>
        </div>
    @endif
</div>
