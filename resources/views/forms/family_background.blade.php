<div id="spouse">
    <div class="text-white bg-danger rounded p-2 mt-5 d-none" id="family_background_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>
    <h5 class="card-title fw-bold">Spouse Information</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_fname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_mname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_lname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Extenstion Name (e.g. Jr.,Sr.)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_ext_name"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Occupation</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_occupation"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Employer/Business name</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_employer"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Business Address</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_business_address"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Telephone No.</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="spouse_contact_no"/>
        </div>
    </div>
</div>
<hr/>
<div class="mt-3" id="father">
    <h5 class="card-title fw-bold">Father Information</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="father_fname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="father_mname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="father_lname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Extension Name (e.g. Jr.,Sr.)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="father_ext_name"/>
        </div>
    </div>
</div>
<hr/>
<div class="mt-3" id="mother">
    <h5 class="card-title fw-bold">Mother's Maiden Name</h5>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mother_fname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mother_mname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mother_lname"/>
        </div>
    </div>
    <div class="row mb-3">
        <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Extension Name (e.g. Jr.,Sr.)</label>
        <div class="col-md-8 col-lg-9">
            <input type="text" class="form-control" id="mother_ext_name"/>
        </div>
    </div>
    @if(Route::currentRouteName() == "profile")
        <div class="text-end mt-5">
            <button type="button" class="btn btn-success" id="btn_save_family">SAVE CHANGES</button>
        </div>
    @endif
</div>
<hr/>
<div class="mt-3" id="children">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold">Children(s) Information</h5>
        @if(Route::currentRouteName() == "profile")
            <div>
                <button class="btn btn-sm btn-success" id="btn_add_children">New</button>
            </div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table text-center">
            <thead>
            <tr>
                <th>Name of children (Write full name and list all)</th>
                <th style="width: 20%">Date of birth</th>
                <th colspan="2" style="width: 10%"></th>
            </tr>
            </thead>
            <tbody id="children_list"></tbody>
        </table>
    </div>
</div>
