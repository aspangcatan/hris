@extends('master')
@section('content')
    <div class="d-flex justify-content-end">
        <div>
            <button class="btn btn-success" id="btn_print_saln">
                <i class="bi bi-printer"></i>
                PRINT SALN
            </button>
        </div>
    </div>

    <section class="section profile mt-3">
        <div class="card">
            <div class="card-body">
                <div class="pagetitle text-center mt-4">
                    <h1>SWORN STATEMENT OF ASSETS, LIABILITIES AND NET WORTH</h1>
                    <div class="fw-bold mt-3">COMPLIANCE FOR:</div>
                    <div class="d-flex justify-content-center">
                        <div class="form-check me-4">
                            <input class="form-check-input" type="radio" value="Assumption of office"
                                   name="compliance_for">
                            <label class="form-check-label">
                                Assumption of office
                            </label>
                        </div>
                        <div class="form-check me-2 ms-2">
                            <input class="form-check-input" type="radio" value="Annual Filing" name="compliance_for">
                            <label class="form-check-label">
                                Annual Filing
                            </label>
                        </div>
                        <div class="form-check ms-4">
                            <input class="form-check-input" type="radio" value="Exit" name="compliance_for">
                            <label class="form-check-label">
                                Exit as of
                            </label>
                        </div>
                    </div>
                    <span>As of
    <select class="underline" id="as_of_month">
        @foreach([
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ] as $month)
            <option value="{{ $month }}" {{ $month == 'December' ? 'selected' : '' }}>
                {{ $month }}
            </option>
        @endforeach
    </select>

    <select class="underline" id="as_of_day">
        @for($day = 1; $day <= 31; $day++)
            <option value="{{ $day }}" {{ $day == 31 ? 'selected' : '' }}>
                {{ $day }}
            </option>
        @endfor
    </select>,

    <select class="underline" id="as_of_year">
        @for($year = 2023; $year <= \Carbon\Carbon::now()->format('Y'); $year++)
            <option value="{{ $year }}" {{ $year == \Carbon\Carbon::now()->format('Y') ? 'selected' : '' }}>
                {{ $year }}
            </option>
        @endfor
    </select>
</span>


                    <div class="mt-3">
                        <strong>Note:</strong>
                        <span class="fw-bold">
                            <i>SPOUSES, WHO ARE BOTH PUBLIC OFFICIALS OR EMPLOYEES, MAY FILE THE SALN JOINTLY OR SEPARATELY.</i>
                        </span>
                        <div class="fw-bold">
                            <i>THE DECLARANT SHALL CHECK THE APPROPRIATE BOX</i>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div class="form-check me-4">
                                <input class="form-check-input" type="radio" value="Joint Filing" name="filing_type">
                                <label class="form-check-label">
                                    Joint Filing
                                </label>
                            </div>
                            <div class="form-check me-2 ms-2">
                                <input class="form-check-input" type="radio" value="Separate Filing" name="filing_type">
                                <label class="form-check-label">
                                    Separate Filing
                                </label>
                            </div>
                            <div class="form-check ms-4">
                                <input class="form-check-input" type="radio" value="Not Applicable" name="filing_type">
                                <label class="form-check-label">
                                    Not Applicable
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Tabs -->
                <ul class="nav nav-tabs nav-tabs-bordered mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#declarant_board"
                                type="button" role="tab"><strong>DECLARANT</strong></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_board" type="button"
                                role="tab"><strong>SPOUSE</strong></button>
                    </li>
                </ul>

                <div class="tab-content pt-2">
                    <div class="tab-pane fade show active" id="declarant_board" role="tabpanel">
                        <ul class="nav nav-tabs nav-tabs-bordered mt-" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#personal-info"
                                        type="button" role="tab">Personal Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#children-info"
                                        type="button"
                                        role="tab">Children Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#real-properties"
                                        type="button"
                                        role="tab">Real Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#personal-properties"
                                        type="button" role="tab">Personal Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#liabilities"
                                        type="button"
                                        role="tab">Liabilities
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#business-interests"
                                        type="button"
                                        role="tab">Business Interests
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#relatives" type="button"
                                        role="tab">Relatives in Government
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#marriages" type="button"
                                        role="tab">Multiple Marriages
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#annex_c" type="button"
                                        role="tab">Annex C
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-2">
                            <div class="tab-pane fade show active" id="personal-info" role="tabpanel">

                                <h5 class="card-title fw-bold">Declarant</h5>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_fname" readonly/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_mname" readonly/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_lname" readonly/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_position"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_agency"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office
                                        Address</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_address"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued
                                        ID</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_issued_id"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="declarant_id_no"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="date" class="form-control" id="declarant_date_issued"/>
                                    </div>
                                </div>
                                <div class="float-end">
                                    <button class="btn btn-success" id="btn_save_declarant">SAVE CHANGES</button>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="children-info" role="tabpanel">
                                <h5 class="card-title fw-bold">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>Unmarried children below eighteen (18) years of age living in declarant's
                                            household
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success" id="btn_add_children">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </h5>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th style="width: 25%">Date of birth</th>
                                            <th colspan="2" style="width: 10%"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="children_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="real-properties" role="tabpanel">

                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Real Properties</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="btn_add_real_properties">ADD NEW
                                        </button>
                                    </div>
                                </div>
                                <table class="table text-center">
                                    <thead>
                                    <tr>
                                        <th rowspan="2">
                                            <div>Description</div>
                                            <div style="font-size: 12px">(e.g. lot, house and lot, condominium and
                                                improvements)
                                            </div>
                                        </th>
                                        <th rowspan="2">
                                            <div>Kind</div>
                                            <div style="font-size: 12px">
                                                (e.g. residential, commercial, industrial, agricultural and mixed use)
                                            </div>
                                        </th>
                                        <th rowspan="2">Exact location</th>
                                        <th>Assessed value</th>
                                        <th>Current fair market value</th>
                                        <th>Acquisition Year</th>
                                        <th>Acquisition Mode</th>
                                        <th>Acquisition cost</th>
                                        <th rowspan="2" colspan="2" style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="real_properties_list"></tbody>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="personal-properties" role="tabpanel">
                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Personal Properties</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="btn_add_personal_properties">ADD
                                            NEW
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Gadgets/Appliances</th>
                                            <th>Various Years</th>
                                            <th>Acquisition Cost/Amount</th>
                                            <th colspan="2" style="width: 10%"></th>
                                        </tr>

                                        </thead>
                                        <tbody id="personal_properties_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="liabilities" role="tabpanel">
                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Liabilities</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="btn_add_liabilities">ADD NEW</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Nature</th>
                                            <th>Name of Creditors</th>
                                            <th>Outstanding Balance</th>
                                            <th style="width: 10%"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="liabilities_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="business-interests" role="tabpanel">
                                <div id="business_interest">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div></div>
                                        <div>
                                            <h5 class="card-title fw-bold text-center">
                                                Business Interests and Financial Connections
                                                <div class="text-black" style="font-size: 12px">(of Declarant
                                                    /Declarant’s
                                                    spouse/
                                                    Unmarried Children Below Eighteen (18) years of Age Living in
                                                    Declarant’s
                                                    Household)
                                                </div>
                                            </h5>
                                            <div class="text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value=""
                                                           id="business_interest_checkbox" style="float:unset">
                                                    <label class="form-check-label" for="flexCheckDefault">
                                                        I/We do not have any business interest or financial connection
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success" id="btn_add_business_interest">ADD
                                                NEW
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table text-center">
                                            <thead>
                                            <tr>
                                                <th>Name of Entity/Business Enterprise</th>
                                                <th>Business Address</th>
                                                <th>Nature of Business Interest &/or Financial Connection</th>
                                                <th>Date of acquisition of interest or connection</th>
                                                <th style="width: 10%"></th>
                                            </tr>
                                            </thead>
                                            <tbody id="business_interest_list"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="relatives" role="tabpanel">
                                <div id="government_relatives">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div></div>
                                        <div>
                                            <h5 class="card-title fw-bold text-center">
                                                Relatives in the government service
                                                <div class="text-black" style="font-size: 12px">(Within the Fourth
                                                    Degree of
                                                    Consanguinity or Affinity. Include also Bilas, Balae and Inso)
                                                </div>
                                            </h5>
                                            <div class="text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value=""
                                                           id="government_relatives_checkbox" style="float:unset">
                                                    <label class="form-check-label" for="flexCheckDefault">
                                                        I/We do not know of any relative/s in the government service)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success" id="btn_add_government_relative">ADD
                                                NEW
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table text-center">
                                            <thead>
                                            <tr>
                                                <th>Name of relative</th>
                                                <th>Relationship</th>
                                                <th>Position</th>
                                                <th>Agency/Office & Address</th>
                                                <th style="width: 10%"></th>
                                            </tr>
                                            </thead>
                                            <tbody id="government_relative_list">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="marriages" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div></div>
                                    <div>
                                        <h5 class="card-title fw-bold text-center">
                                            IF WITH MULTIPLE MARRIAGES, INDICATE NAME(S) OF SPOUSES
                                        </h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="btn_add_marriages">ADD NEW
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Name of Spouses</th>
                                            <th style="width: 10%"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="marriages_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="annex_c" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div></div>
                                    <div>
                                        <h5 class="card-title fw-bold text-center">
                                            (Additional sheet/s for the exclusive properties of the declarant's spouse
                                            and
                                            unmarried children below eighteen (18) years of age living in declarant's
                                            household)
                                        </h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="btn_add_marriages">ADD NEW
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Name of Spouses</th>
                                            <th style="width: 10%"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="marriages_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="spouse_board" role="tabpanel">
                        <ul class="nav nav-tabs nav-tabs-bordered mt-" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab"
                                        data-bs-target="#spouse_personal-info"
                                        type="button" role="tab">Personal Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_real-properties"
                                        type="button"
                                        role="tab">Real Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#spouse_personal-properties"
                                        type="button" role="tab">Personal Properties
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#spouse_liabilities"
                                        type="button"
                                        role="tab">Liabilities
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab"
                                        data-bs-target="#spouse_business-interests" type="button"
                                        role="tab">Business Interests
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-2">
                            <div class="tab-pane fade show active" id="spouse_personal-info" role="tabpanel">
                                <h5 class="card-title fw-bold">Spouse</h5>
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
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="spouse_position"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="spouse_agency"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office
                                        Address</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="spouse_address"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued
                                        ID</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="spouse_issued_id"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="text" class="form-control" id="spouse_id_no"/>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                                    <div class="col-md-8 col-lg-9">
                                        <input type="date" class="form-control" id="spouse_date_issued"/>
                                    </div>
                                </div>
                                <div class="float-end">
                                    <button class="btn btn-success" id="spouse_btn_save_declarant">SAVE CHANGES</button>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="spouse_real-properties" role="tabpanel">

                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Real Properties</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="spouse_btn_add_real_properties">ADD
                                            NEW
                                        </button>
                                    </div>
                                </div>
                                <table class="table text-center">
                                    <thead>
                                    <tr>
                                        <th rowspan="2">
                                            <div>Description</div>
                                            <div style="font-size: 12px">(e.g. lot, house and lot, condominium and
                                                improvements)
                                            </div>
                                        </th>
                                        <th rowspan="2">
                                            <div>Kind</div>
                                            <div style="font-size: 12px">
                                                (e.g. residential, commercial, industrial, agricultural and mixed use)
                                            </div>
                                        </th>
                                        <th rowspan="2">Exact location</th>
                                        <th>Assessed value</th>
                                        <th>Current fair market value</th>
                                        <th>Acquisition Year</th>
                                        <th>Acquisition Mode</th>
                                        <th>Acquisition cost</th>
                                        <th rowspan="2" colspan="2" style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="spouse_real_properties_list"></tbody>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="spouse_personal-properties" role="tabpanel">
                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Personal Properties</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="spouse_btn_add_personal_properties">
                                            ADD NEW
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Gadgets/Appliances</th>
                                            <th>Various Years</th>
                                            <th>Acquisition Cost/Amount</th>
                                            <th colspan="2" style="width: 10%"></th>
                                        </tr>

                                        </thead>
                                        <tbody id="spouse_personal_properties_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="spouse_liabilities" role="tabpanel">
                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h5 class="card-title fw-bold">Liabilities</h5>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="spouse_btn_add_liabilities">ADD NEW
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-center">
                                        <thead>
                                        <tr>
                                            <th>Nature</th>
                                            <th>Name of Creditors</th>
                                            <th>Outstanding Balance</th>
                                            <th style="width: 10%"></th>
                                        </tr>
                                        </thead>
                                        <tbody id="spouse_liabilities_list">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="spouse_business-interests" role="tabpanel">
                                <div id="spouse_business_interest">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div></div>
                                        <div>
                                            <h5 class="card-title fw-bold text-center">
                                                Business Interests and Financial Connections
                                                <div class="text-black" style="font-size: 12px">(of Declarant
                                                    /Declarant’s
                                                    spouse/
                                                    Unmarried Children Below Eighteen (18) years of Age Living in
                                                    Declarant’s
                                                    Household)
                                                </div>
                                            </h5>
                                            <div class="text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value=""
                                                           id="spouse_business_interest_checkbox" style="float:unset">
                                                    <label class="form-check-label" for="flexCheckDefault">
                                                        I/We do not have any business interest or financial connection
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success"
                                                    id="spouse_btn_add_business_interest">ADD NEW
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table text-center">
                                            <thead>
                                            <tr>
                                                <th>Name of Entity/Business Enterprise</th>
                                                <th>Business Address</th>
                                                <th>Nature of Business Interest &/or Financial Connection</th>
                                                <th>Date of acquisition of interest or connection</th>
                                                <th style="width: 10%"></th>
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
    </section>
    @include('modals.saln')
@stop
@section('js')
    @include('js.saln')
@stop
