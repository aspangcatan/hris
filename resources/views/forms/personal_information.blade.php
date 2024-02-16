<form>
    <div class="text-white bg-danger rounded p-2 mt-5 d-none" id="personal_information_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>
    <h5 class="card-title fw-bold">Profile Details</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="fname">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mname">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="lname">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Extension Name (e.g. Jr,Sr)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="suffix">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date of birth</label>
        <div class="col-md-8 col-lg-9">
            <input type="date" class="form-control" id="birthdate">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Place of birth</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="birthplace">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Sex</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="sex">
                <option value="">Select sex</option>
                <option>MALE</option>
                <option>FEMALE</option>
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Civil Status</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="civil_status">
                <option value="">Select civil status</option>
                <option>SINGLE</option>
                <option>MARRIED</option>
                <option>WIDOWED</option>
                <option>SEPARATED</option>
                <option>OTHERS</option>
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Height (m)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="height">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Weight (kg)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="weight">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Blood type</label>
        <div class="col-md-8 col-lg-9">
            <select class="form-control" id="blood_type">
                <option value="" selected></option>
                <option>A+</option>
                <option>A-</option>
                <option>B+</option>
                <option>B-</option>
                <option>O+</option>
                <option>O-</option>
                <option>AB+</option>
                <option>AB-</option>
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">GSIS ID No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="gsis_id_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Pag-Ibig ID No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="pagibig_id_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">PhilHealth No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="philhealth_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">SSS No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="sss_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">TIN No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="tin_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued ID</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="issued_id" placeholder="(e.g. Voter's ID,License No.)">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Issued ID No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="issued_id_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date/Place of Issuance</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="issued_date">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency Employee No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="employee_no">
        </div>
    </div>
    @if(Route::currentRouteName() == "employee")
        <div class="row mb-3">
            <label class="col-md-4 col-lg-3 col-form-label">Date hired</label>
            <div class="col-md-8 col-lg-9">
                <input type="date" class="form-control" id="date_hired"/>
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-md-4 col-lg-3 col-form-label">Date resigned</label>
            <div class="col-md-8 col-lg-9">
                <input type="date" class="form-control" id="date_resigned"/>
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Employee Status</label>
            <div class="col-md-8 col-lg-9">
                <select class="form-control" id="employee_type">
                    <option value="" selected></option>
                    <option>Job Order</option>
                    <option>Permanent</option>
                    <option>Resigned</option>
                    <option>Retired</option>
                    <option>Temporary</option>
                    <option>EOT</option>
                    <option>COS</option>
                </select>
            </div>
        </div>
    @endif
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Telephone No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="telephone_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Mobile No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mobile_no">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Email Address</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="email_address">
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Citizenship</label>
        <div class="col-md-8 col-lg-9">

            <div class="form-check">
                <input type="radio" name="citizenship">
                <label class="form-check-label" for="flexCheckDefault">
                    Filipino
                </label>
            </div>
            <div class="form-check">
                <input type="radio" name="citizenship">
                <label class="form-check-label" for="flexCheckDefault">
                    Dual Citizenship
                </label>
            </div>

        </div>
    </div>
    <div class="row mb-3 dual-citizenship-container">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Citizenship Type</label>
        <div class="col-md-8 col-lg-9">
            <div class="form-check">
                <input type="radio" name="citizenship_type">
                <label class="form-check-label" for="flexCheckDefault">
                    by birth
                </label>
            </div>
            <div class="form-check">
                <input type="radio" name="citizenship_type">
                <label class="form-check-label" for="flexCheckDefault">
                    by naturalization
                </label>
            </div>
        </div>
    </div>
    <div class="row mb-3 dual-citizenship-container">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Country</label>
        <div class="col-md-8 col-lg-9">
            <div>
                <input type="text" class="form-control" id="citizenship_country"/>
            </div>
        </div>
    </div>

    <div class="text-end mt-5">
        <button type="button" class="btn btn-success" id="btn_save_personal">SAVE CHANGES</button>
    </div>
</form>
