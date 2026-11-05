{{--
    Address (PDS I), grouped into Residential and Permanent cards with labels above the fields.
    Shared by /profile and the Employee page; the field ids are what js/profile and js/personnel use.
--}}
<div class="pi-form">
    <div class="text-white bg-danger rounded p-2 mb-3 d-none" id="address_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>

    @foreach(['residential' => ['bi-house-door', 'Residential Address'], 'permanent' => ['bi-geo-alt', 'Permanent Address']] as $kind => [$icon, $title])
        <section class="form-section" id="{{ $kind }}">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="section-title mb-0"><i class="bi {{ $icon }}"></i> {{ $title }}</h6>
                @if($kind === 'permanent')
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_copy_residential"
                            title="Fill the permanent address with the residential address">
                        <i class="bi bi-files"></i> Copy from residential
                    </button>
                @endif
            </div>
            <div class="row g-3">
                <div class="col-md-4 col-xl-3">
                    <label class="form-label" for="{{ $kind }}_house">House / Block / Lot no.</label>
                    <input type="text" class="form-control" id="{{ $kind }}_house"/>
                </div>
                <div class="col-md-8 col-xl-5">
                    <label class="form-label" for="{{ $kind }}_street">Street</label>
                    <input type="text" class="form-control" id="{{ $kind }}_street"/>
                </div>
                <div class="col-md-12 col-xl-4">
                    <label class="form-label" for="{{ $kind }}_subdivision_village">Subdivision / Village</label>
                    <input type="text" class="form-control" id="{{ $kind }}_subdivision_village"/>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="{{ $kind }}_barangay">Barangay</label>
                    <input type="text" class="form-control" id="{{ $kind }}_barangay"/>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label" for="{{ $kind }}_city">City / Municipality <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="{{ $kind }}_city"/>
                </div>
                <div class="col-md-8 col-xl-4">
                    <label class="form-label" for="{{ $kind }}_province">Province <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="{{ $kind }}_province"/>
                </div>
                <div class="col-md-4 col-xl-2">
                    <label class="form-label" for="{{ $kind }}_zipcode">ZIP code</label>
                    <input type="text" class="form-control" id="{{ $kind }}_zipcode" inputmode="numeric" maxlength="10"/>
                </div>
            </div>
        </section>
    @endforeach

    @if(Route::currentRouteName() == "profile")
        <div class="modal-action-row">
            <button type="button" class="btn btn-success btn-modal-save" id="btn_save_address">
                <i class="bi bi-check2-circle me-1"></i>Save Changes
            </button>
        </div>
    @endif
</div>

<script>
    // "Copy from residential": fills the permanent fields only - nothing is saved until Save Changes.
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#btn_copy_residential')) return;
        ['house', 'street', 'subdivision_village', 'barangay', 'city', 'province', 'zipcode'].forEach(function (f) {
            var from = document.getElementById('residential_' + f);
            var to = document.getElementById('permanent_' + f);
            if (from && to) to.value = from.value;
        });
    });
</script>
