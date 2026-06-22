<div class="modal fade" id="saln_form_modal" tabindex="-1" aria-labelledby="salnFormModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header saln-premium-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="saln-premium-header-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-800 text-white" id="salnFormModalLabel">
                            SALN Declaration
                        </h5>
                        <div class="text-white opacity-75" style="font-size:.85rem">
                            Sworn Statement of Assets, Liabilities &amp; Net Worth
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <!-- Premium Loader Overlay -->
                <div id="saln_loader_overlay" class="saln-loader-overlay" style="display: none;">
                    <div class="loader-content">
                        <div class="premium-spinner"></div>
                        <div class="loader-text fw-bold text-dark">Synchronizing Records</div>
                        <div class="loader-subtext text-muted">Optimizing your historical data...</div>
                    </div>
                </div>

                <div class="saln-form-container">
                    <div class="container-fluid">
                        <!-- Upper Section: Compliance & Filing Type -->
                        <div class="saln-section-card mb-4">
                            <div class="text-center">
                                <h4 class="fw-800 mb-4" style="letter-spacing: -0.5px; color: var(--saln-text-main);">COMPLIANCE CONFIGURATION</h4>
                                
                                <div class="row justify-content-center g-4">
                                    <div class="col-lg-5">
                                        <div class="p-4 rounded-4 bg-light border border-2 border-white shadow-sm">
                                            <div class="form-label-premium mb-3">Filing Context</div>
                                            <div class="d-flex justify-content-center gap-4 flex-wrap">
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Assumption of office" name="compliance_for" id="comp_assumption">
                                                    <label class="form-check-label fw-600" for="comp_assumption">Assumption</label>
                                                </div>
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Annual Filing" name="compliance_for" id="comp_annual" checked>
                                                    <label class="form-check-label fw-600" for="comp_annual">Annual</label>
                                                </div>
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Exit" name="compliance_for" id="comp_exit">
                                                    <label class="form-check-label fw-600" for="comp_exit">Exit</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="p-4 rounded-4 bg-light border border-2 border-white shadow-sm">
                                            <div class="form-label-premium mb-3">Filing Type</div>
                                            <div class="d-flex justify-content-center gap-4 flex-wrap">
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Joint Filing" name="filing_type" id="filing_joint">
                                                    <label class="form-check-label fw-600" for="filing_joint">Joint</label>
                                                </div>
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Separate Filing" name="filing_type" id="filing_separate">
                                                    <label class="form-check-label fw-600" for="filing_separate">Separate</label>
                                                </div>
                                                <div class="form-check custom-option">
                                                    <input class="form-check-input" type="radio" value="Not Applicable" name="filing_type" id="filing_na" checked>
                                                    <label class="form-check-label fw-600" for="filing_na">N/A</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <span class="text-muted fw-600">Effective Date: </span>
                                    <select class="underline mx-1" id="as_of_month">
                                        @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m)
                                            <option value="{{ $m }}" {{ $m == 'December' ? 'selected' : '' }}>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                    <select class="underline mx-1" id="as_of_day">
                                        @for($d = 1; $d <= 31; $d++) <option value="{{ $d }}" {{ $d == 31 ? 'selected' : '' }}>{{ $d }}</option> @endfor
                                    </select>
                                    <select class="underline mx-1" id="as_of_year">
                                        @for($y = 2020; $y <= date('Y'); $y++) <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option> @endfor
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Main Navigation Tabs -->
                        <div class="text-center">
                            <ul class="nav nav-pills saln-tab-nav mb-5" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#declarant_board" type="button">
                                        <i class="bi bi-person-fill me-2"></i>DECLARANT DATA
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_board" type="button">
                                        <i class="bi bi-people-fill me-2"></i>SPOUSE DATA
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="tab-content">
                            <!-- DECLARANT TAB -->
                            <div class="tab-pane fade show active" id="declarant_board">
                                <ul class="nav nav-tabs saln-sub-tab-nav" role="tablist">
                                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#personal-info">Profile & ID</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#children-info">Children</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#real-properties">Real Assets</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#personal-properties">Personal Assets</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#liabilities">Liabilities</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#business-interests">Business/Financial</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#relatives">Gov't Relatives</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#marriages">Marriages</button></li>
                                </ul>

                                <div class="tab-content">
                                    <!-- Personal Info Section -->
                                    <div class="tab-pane fade show active" id="personal-info">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title"><i class="bi bi-person-badge"></i>Personal & Government ID Info</div>
                                            <div class="row g-4">
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Firstname</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_fname" readonly/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Middlename</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_mname" readonly/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Lastname</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_lname" readonly/>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Position</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_position"/>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Agency/Office</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_agency"/>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label-premium">Office Address</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_address"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Government Issued ID</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_issued_id"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">ID No.</label>
                                                    <input type="text" class="form-control-premium w-100" id="declarant_id_no"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Date Issued</label>
                                                    <input type="date" class="form-control-premium w-100" id="declarant_date_issued"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Children List -->
                                    <div class="tab-pane fade" id="children-info">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-people"></i>
                                                Household Children Info
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_children">
                                                    <i class="bi bi-plus-lg me-0"></i>
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Full Name of Child</th>
                                                            <th style="width: 30%">Date of Birth</th>
                                                            <th style="width: 15%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="children_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Real Properties -->
                                    <div class="tab-pane fade" id="real-properties">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-house"></i>
                                                Real Properties
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_real_properties">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD ASSET
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Description <small class="d-block text-muted">(e.g. Lot, House)</small></th>
                                                            <th>Kind <small class="d-block text-muted">(e.g. Residential)</small></th>
                                                            <th>Location</th>
                                                            <th>Assessed Value</th>
                                                            <th>Market Value</th>
                                                            <th>Year</th>
                                                            <th>Mode</th>
                                                            <th>Acquisition Cost</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="real_properties_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Personal Properties -->
                                    <div class="tab-pane fade" id="personal-properties">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-laptop"></i>
                                                Personal Properties
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_personal_properties">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD ASSET
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Gadgets/Appliances/Others</th>
                                                            <th>Various Years</th>
                                                            <th>Acquisition Cost</th>
                                                            <th style="width: 15%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="personal_properties_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Liabilities -->
                                    <div class="tab-pane fade" id="liabilities">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-credit-card"></i>
                                                Liabilities
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_liabilities">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD LIABILITY
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Nature</th>
                                                            <th>Name of Creditors</th>
                                                            <th>Outstanding Balance</th>
                                                            <th style="width: 15%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="liabilities_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business Interests -->
                                    <div class="tab-pane fade" id="business-interests">
                                        <div class="saln-section-card text-center">
                                            <div class="saln-card-title justify-content-center"><i class="bi bi-briefcase"></i>Business Interests & Financial Connections</div>
                                            <div class="p-3 bg-light rounded-4 mb-4 d-inline-block">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" id="business_interest_checkbox">
                                                    <label class="form-check-label fw-bold" for="business_interest_checkbox">
                                                        I/We do not have any business interest or financial connection
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="text-end mb-3">
                                                <button class="btn btn-finalize btn-sm" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_business_interest">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD NEW
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Entity/Business</th>
                                                            <th>Address</th>
                                                            <th>Nature of Interest</th>
                                                            <th>Acquisition Date</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="business_interest_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Relatives in Gov't -->
                                    <div class="tab-pane fade" id="relatives">
                                        <div class="saln-section-card text-center">
                                            <div class="saln-card-title justify-content-center"><i class="bi bi-diagram-3"></i>Relatives in Government Service</div>
                                            <div class="p-3 bg-light rounded-4 mb-4 d-inline-block">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" id="government_relatives_checkbox" >
                                                    <label class="form-check-label fw-bold" for="government_relatives_checkbox">
                                                        I/We do not know of any relative/s in the government service
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="text-end mb-3">
                                                <button class="btn btn-finalize btn-sm" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_government_relative">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD NEW
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Name of Relative</th>
                                                            <th>Relationship</th>
                                                            <th>Position</th>
                                                            <th>Agency/Address</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="government_relative_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Marriages -->
                                    <div class="tab-pane fade" id="marriages">
                                        <div class="saln-section-card text-center">
                                            <div class="saln-card-title justify-content-center"><i class="bi bi-heart"></i>Multiple Marriages Information</div>
                                            <div class="text-end mb-3">
                                                <button class="btn btn-finalize btn-sm" style="padding: 8px 16px; border-radius: 10px;" id="btn_add_marriages">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD ENTRY
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Full Name of Spouse(s)</th>
                                                            <th style="width: 20%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="marriages_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SPOUSE TAB -->
                            <div class="tab-pane fade" id="spouse_board">
                                <ul class="nav nav-tabs saln-sub-tab-nav" role="tablist">
                                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#spouse_personal-info">Profile & ID</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_real-properties">Real Assets</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_personal-properties">Personal Assets</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_liabilities">Liabilities</button></li>
                                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_business-interests">Business/Financial</button></li>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="spouse_personal-info">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title"><i class="bi bi-person-heart"></i>Spouse's Profile & ID Information</div>
                                            <div class="row g-4">
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Firstname</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_fname"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Middlename</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_mname"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Lastname</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_lname"/>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Position</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_position"/>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Agency/Office</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_agency"/>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label-premium">Office Address</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_address"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Government Issued ID</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_issued_id"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">ID No.</label>
                                                    <input type="text" class="form-control-premium w-100" id="spouse_id_no"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label-premium">Date Issued</label>
                                                    <input type="date" class="form-control-premium w-100" id="spouse_date_issued"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Spouse Real Properties -->
                                    <div class="tab-pane fade" id="spouse_real-properties">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-house-heart"></i>
                                                Spouse's Real Properties
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="spouse_btn_add_real_properties">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD ASSET
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Description</th>
                                                            <th>Kind</th>
                                                            <th>Location</th>
                                                            <th>Assessed Value</th>
                                                            <th>Market Value</th>
                                                            <th>Year</th>
                                                            <th>Mode</th>
                                                            <th>Acquisition Cost</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="spouse_real_properties_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Spouse Personal -->
                                    <div class="tab-pane fade" id="spouse_personal-properties">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-item-heart"></i>
                                                Spouse's Personal Properties
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="spouse_btn_add_personal_properties">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD ASSET
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Item Description</th>
                                                            <th>Various Years</th>
                                                            <th>Acquisition Cost</th>
                                                            <th style="width: 15%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="spouse_personal_properties_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Spouse Liabilities -->
                                    <div class="tab-pane fade" id="spouse_liabilities">
                                        <div class="saln-section-card">
                                            <div class="saln-card-title">
                                                <i class="bi bi-credit-card-2-back"></i>
                                                Spouse's Liabilities
                                                <button class="btn btn-finalize btn-sm ms-auto" style="padding: 8px 16px; border-radius: 10px;" id="spouse_btn_add_liabilities">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD LIABILITY
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Nature</th>
                                                            <th>Name of Creditors</th>
                                                            <th>Outstanding Balance</th>
                                                            <th style="width: 15%">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="spouse_liabilities_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Spouse Business -->
                                    <div class="tab-pane fade" id="spouse_business-interests">
                                        <div class="saln-section-card text-center">
                                            <div class="saln-card-title justify-content-center"><i class="bi bi-briefcase"></i>Spouse's Business Interests</div>
                                            <div class="p-4 bg-light rounded-4 mb-4 d-inline-block">
                                                <div class="form-check m-0">
                                                    <input class="form-check-input" type="checkbox" id="spouse_business_interest_checkbox">
                                                    <label class="form-check-label fw-bold" for="spouse_business_interest_checkbox">
                                                        Spouse has no business interest or financial connection
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="text-end mb-3">
                                                <button class="btn btn-finalize btn-sm" style="padding: 8px 16px; border-radius: 10px;" id="spouse_btn_add_business_interest">
                                                    <i class="bi bi-plus-lg me-1"></i>ADD NEW
                                                </button>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table saln-table-premium text-center">
                                                    <thead>
                                                        <tr>
                                                            <th>Entity/Business</th>
                                                            <th>Address</th>
                                                            <th>Nature of Interest</th>
                                                            <th>Acquisition Date</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="spouse_business_interest_list"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Redesigned Confirmation Footer -->
            <div class="confirmation-footer">
                <div class="footer-info">
                   <div class="footer-info-item me-4 d-none d-md-flex">
                        <span class="footer-info-label">Current Filling Mode</span>
                        <span class="footer-info-value" id="footer_filing_mode_display">ANNUAL FILING</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4 py-3 rounded-4" data-bs-dismiss="modal">
                        CANCEL
                    </button>
                    <button class="btn btn-outline-success fw-bold px-4 py-3 rounded-4" id="btn_print_saln" style="border-width: 2px;">
                        <i class="bi bi-printer me-2"></i>PREVIEW PDF
                    </button>
                    <button type="button" class="btn-finalize" id="btn_save_and_finalize">
                        <i class="bi bi-shield-check"></i>
                        SAVE DECLARATION
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
