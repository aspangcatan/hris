{{--
    Personal Information (PDS I), grouped by category.
    Shared by /profile and the Employee page. Scripts depend on:
      - the field ids below;
      - the citizenship radios staying in this order (filled by position from
        CITIZENSHIP / CITIZENSHIP_TYPE in js/personnel) with each label right after its
        radio (read with .next().text());
      - .dual-citizenship-container, shown only for "Dual Citizenship".
--}}
<form class="pi-form">
    <div class="text-white bg-danger rounded p-2 mb-3 d-none" id="personal_information_error">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div>There are some errors, please correct them below:</div>
        </div>
        <ul class="error_list"></ul>
    </div>

    {{-- Name --}}
    <section class="form-section">
        <h6 class="section-title"><i class="bi bi-person-badge"></i> Name</h6>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="fname">First name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="fname" autocomplete="given-name">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="mname">Middle name</label>
                <input type="text" class="form-control" id="mname" autocomplete="additional-name">
            </div>
            <div class="col-md-6 col-xl-4">
                <label class="form-label" for="lname">Last name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="lname" autocomplete="family-name">
            </div>
            <div class="col-md-6 col-xl-2">
                <label class="form-label" for="suffix">Extension</label>
                <select class="form-control" id="suffix">
                    <option value="">None</option>
                    <option value="Jr.">Jr</option>
                    <option value="Sr.">Sr.</option>
                    <option value="I">I</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                    <option value="V">V</option>
                    <option value="VII">VII</option>
                </select>
            </div>
        </div>
    </section>

    {{-- Birth & personal details --}}
    <section class="form-section">
        <h6 class="section-title"><i class="bi bi-calendar-heart"></i> Birth &amp; Personal Details</h6>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="birthdate">Date of birth</label>
                <input type="date" class="form-control" id="birthdate">
            </div>
            <div class="col-md-6 col-xl-5">
                <label class="form-label" for="birthplace">Place of birth</label>
                <input type="text" class="form-control" id="birthplace" placeholder="City / Municipality, Province">
            </div>
            <div class="col-md-6 col-xl-2">
                <label class="form-label" for="sex">Sex</label>
                <select class="form-control" id="sex">
                    <option value="">Select sex</option>
                    <option>MALE</option>
                    <option>FEMALE</option>
                </select>
            </div>
            <div class="col-md-6 col-xl-2">
                <label class="form-label" for="civil_status">Civil status</label>
                <select class="form-control" id="civil_status">
                    <option value="">Select status</option>
                    <option>SINGLE</option>
                    <option>MARRIED</option>
                    <option>WIDOWED</option>
                    <option>SEPARATED</option>
                    <option>OTHERS</option>
                </select>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-3">
        {{-- Physical --}}
        <div class="col-lg-5">
            <section class="form-section h-100">
                <h6 class="section-title"><i class="bi bi-heart-pulse"></i> Physical</h6>
                <div class="row g-3">
                    <div class="col-sm-4">
                        <label class="form-label" for="height">Height (m)</label>
                        <input type="number" class="form-control" id="height" step="0.01" min="0" placeholder="e.g. 1.65">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="weight">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" step="0.1" min="0" placeholder="e.g. 60">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="blood_type">Blood type</label>
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
            </section>
        </div>

        {{-- Citizenship --}}
        <div class="col-lg-7">
            <section class="form-section h-100">
                <h6 class="section-title"><i class="bi bi-flag"></i> Citizenship</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="choice-group" role="radiogroup" aria-label="Citizenship">
                            <div class="choice"><input type="radio" name="citizenship" id="pi_cit_filipino"><label for="pi_cit_filipino">Filipino</label></div>
                            <div class="choice"><input type="radio" name="citizenship" id="pi_cit_dual"><label for="pi_cit_dual">Dual Citizenship</label></div>
                        </div>
                    </div>
                    <div class="col-md-6 dual-citizenship-container d-none">
                        <label class="form-label d-block">Dual citizenship by</label>
                        <div class="choice-group" role="radiogroup" aria-label="Dual citizenship by">
                            <div class="choice"><input type="radio" name="citizenship_type" id="pi_cit_birth"><label for="pi_cit_birth">by birth</label></div>
                            <div class="choice"><input type="radio" name="citizenship_type" id="pi_cit_naturalization"><label for="pi_cit_naturalization">by naturalization</label></div>
                        </div>
                    </div>
                    <div class="col-md-6 dual-citizenship-container d-none">
                        <label class="form-label" for="citizenship_country">Other country</label>
                        <input type="text" class="form-control" id="citizenship_country" placeholder="Country">
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- Contact --}}
    <section class="form-section">
        <h6 class="section-title"><i class="bi bi-telephone"></i> Contact</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="mobile_no">Mobile no.</label>
                <input type="text" class="form-control" id="mobile_no" inputmode="tel" autocomplete="tel" placeholder="09XX XXX XXXX">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="telephone_no">Telephone no.</label>
                <input type="text" class="form-control" id="telephone_no" inputmode="tel">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email_address">Email address</label>
                <input type="text" class="form-control" id="email_address" inputmode="email" autocomplete="email" placeholder="name@example.com">
            </div>
        </div>
    </section>

    {{-- Government IDs --}}
    <section class="form-section">
        <h6 class="section-title"><i class="bi bi-person-vcard"></i> Government ID Numbers</h6>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="gsis_id_no">GSIS ID no.</label>
                <input type="text" class="form-control" id="gsis_id_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="umid_id_no">UMID ID no.</label>
                <input type="text" class="form-control" id="umid_id_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="pagibig_id_no">Pag-IBIG ID no.</label>
                <input type="text" class="form-control" id="pagibig_id_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="philhealth_no">PhilHealth no.</label>
                <input type="text" class="form-control" id="philhealth_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="sss_no">SSS no.</label>
                <input type="text" class="form-control" id="sss_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="philsys_no">PhilSys no. (PSN)</label>
                <input type="text" class="form-control" id="philsys_no">
            </div>
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="tin_no">TIN</label>
                <input type="text" class="form-control" id="tin_no">
            </div>
        </div>
    </section>

    <div class="row g-3 mb-3">
        {{-- Other government-issued ID --}}
        <div class="col-lg-7">
            <section class="form-section h-100">
                <h6 class="section-title"><i class="bi bi-card-heading"></i> Government-Issued ID</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="issued_id">ID type</label>
                        <input type="text" class="form-control" id="issued_id" placeholder="e.g. Voter's ID, Driver's License">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="issued_id_no">ID no.</label>
                        <input type="text" class="form-control" id="issued_id_no">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="issued_date">Date / place of issuance</label>
                        <input type="text" class="form-control" id="issued_date" placeholder="e.g. 01/15/2024, Cebu City">
                    </div>
                </div>
            </section>
        </div>

        {{-- Employment --}}
        <div class="col-lg-5">
            <section class="form-section h-100">
                <h6 class="section-title"><i class="bi bi-briefcase"></i> Employment</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="employee_no">Agency employee no.</label>
                        <input type="text" class="form-control" id="employee_no">
                    </div>
                    @if(Route::currentRouteName() == "employee")
                        <div class="col-md-6">
                            <label class="form-label" for="date_hired">Date hired</label>
                            <input type="date" class="form-control" id="date_hired"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="date_resigned">Date resigned</label>
                            <input type="date" class="form-control" id="date_resigned"/>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="employee_type">Employee status</label>
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
                    @endif
                </div>
            </section>
        </div>
    </div>

    <div class="modal-action-row">
        {{-- On /profile this form is a tab, not a dialog, so there is nothing to close. --}}
        @if(Route::currentRouteName() != "profile")
            <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal" aria-label="Close">Close</button>
        @endif
        <button type="button" class="btn btn-success btn-modal-save" id="btn_save_personal">
            <i class="bi bi-check2-circle me-1"></i>Save Changes
        </button>
    </div>
</form>
