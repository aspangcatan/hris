<form>
    <div class="text-white bg-danger rounded p-2 mt-5 d-none" id="address_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>
    <div id="residential">
        <h5 class="card-title fw-bold">Residential</h5>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">House/Block/Lot No.</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_house" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Street</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_street" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Subdivision/Village</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_subdivision_village" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Barangay</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_barangay" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">City/Municipality</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_city" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Province</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_province" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Zipcode</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="residential_zipcode" />
            </div>
        </div>
    </div>
    <div class="mt-3" id="permanent">
        <h5 class="card-title fw-bold">Permanent</h5>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">House/Block/Lot No.</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_house" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Street</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_street" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Subdivision/Village</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_subdivision_village" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Barangay</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_barangay" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">City/Municipality</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_city" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Province</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_province" />
            </div>
        </div>
        <div class="row mb-3">
            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Zipcode</label>
            <div class="col-md-8 col-lg-9">
                <input type="text" class="form-control" id="permanent_zipcode" />
            </div>
        </div>
    </div>

    <div class="text-end mt-5">
        <button type="button" class="btn btn-success" id="btn_save_address">SAVE CHANGES</button>
    </div>
</form>
