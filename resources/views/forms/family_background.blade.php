{{--
    Family Background (PDS II), grouped into cards with labels above the fields.
    Shared by /profile and the Employee page; the field ids and #children_list are what
    js/profile and js/personnel use. Children are added/edited through their own dialog.
--}}
<div class="pi-form">
    <div class="text-white bg-danger rounded p-2 mb-3 d-none" id="family_background_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>

    {{-- Spouse --}}
    <section class="form-section" id="spouse">
        <h6 class="section-title"><i class="bi bi-heart"></i> Spouse</h6>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="spouse_fname">First name</label>
                <input type="text" class="form-control" id="spouse_fname"/>
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="spouse_mname">Middle name</label>
                <input type="text" class="form-control" id="spouse_mname"/>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label" for="spouse_lname">Last name</label>
                <input type="text" class="form-control" id="spouse_lname"/>
            </div>
            <div class="col-md-6 col-xl-2">
                <label class="form-label" for="spouse_ext_name">Extension</label>
                <input type="text" class="form-control" id="spouse_ext_name" placeholder="e.g. Jr., Sr."/>
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label" for="spouse_occupation">Occupation</label>
                <input type="text" class="form-control" id="spouse_occupation"/>
            </div>
            <div class="col-md-6 col-xl-5">
                <label class="form-label" for="spouse_employer">Employer / Business name</label>
                <input type="text" class="form-control" id="spouse_employer"/>
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="spouse_contact_no">Telephone no.</label>
                <input type="text" class="form-control" id="spouse_contact_no" inputmode="tel"/>
            </div>
            <div class="col-md-6 col-xl-12">
                <label class="form-label" for="spouse_business_address">Business address</label>
                <input type="text" class="form-control" id="spouse_business_address"/>
            </div>
        </div>
    </section>

    {{-- Parents --}}
    <div class="row g-3 mb-3">
        @foreach(['father' => ['bi-person', 'Father'], 'mother' => ['bi-person-heart', "Mother's Maiden Name"]] as $parent => [$icon, $title])
            <div class="col-xl-6">
                <section class="form-section h-100" id="{{ $parent }}">
                    <h6 class="section-title"><i class="bi {{ $icon }}"></i> {{ $title }}</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $parent }}_fname">First name</label>
                            <input type="text" class="form-control" id="{{ $parent }}_fname"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $parent }}_mname">Middle name</label>
                            <input type="text" class="form-control" id="{{ $parent }}_mname"/>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="{{ $parent }}_lname">{{ $parent === 'mother' ? 'Maiden last name' : 'Last name' }}</label>
                            <input type="text" class="form-control" id="{{ $parent }}_lname"/>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $parent }}_ext_name">Extension</label>
                            <input type="text" class="form-control" id="{{ $parent }}_ext_name" placeholder="e.g. Jr., Sr."/>
                        </div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    {{-- Children --}}
    <section class="form-section" id="children">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h6 class="section-title mb-0"><i class="bi bi-people"></i> Children</h6>
            @if(Route::currentRouteName() == "profile")
                <button type="button" class="btn btn-sm btn-primary" id="btn_add_children">
                    <i class="bi bi-plus-lg"></i> Add child
                </button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table text-center mb-0">
                <thead>
                <tr>
                    <th>Full name (list all children)</th>
                    <th style="width: 20%">Date of birth</th>
                    <th style="width: 16%">Actions</th>
                </tr>
                </thead>
                <tbody id="children_list"></tbody>
            </table>
        </div>
    </section>

    @if(Route::currentRouteName() == "profile")
        <div class="modal-action-row align-items-center">
            <span class="form-text me-auto mt-0">
                <i class="bi bi-info-circle"></i> Saves spouse and parents. Children are saved when you add or edit them.
            </span>
            <button type="button" class="btn btn-success btn-modal-save" id="btn_save_family">
                <i class="bi bi-check2-circle me-1"></i>Save Changes
            </button>
        </div>
    @endif
</div>
